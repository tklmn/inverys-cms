<?php

namespace App\Models;

use App\Helpers\StorageHelper;
use App\Models\Concerns\HasTranslations;
use App\Services\MediaImageProcessor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    /** Editable per language (multilingual plugin): what visitors read or have read aloud */
    protected array $translatable = ['alt_text', 'description'];

    /**
     * Remove the physical files only when the record is permanently deleted,
     * so soft-deleted media in the trash can still be restored.
     */
    protected static function booted(): void
    {
        static::forceDeleted(function (Media $media) {
            if ($media->path && Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }

            if ($media->thumbnail_path
                && $media->thumbnail_path !== $media->path
                && Storage::disk('public')->exists($media->thumbnail_path)) {
                Storage::disk('public')->delete($media->thumbnail_path);
            }

            // Scaled copies for srcset (MediaImageProcessor)
            app(MediaImageProcessor::class)->deleteVariants($media);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'file_name',
        'path',
        'thumbnail_path',
        'mime_type',
        'size',
        'width',
        'height',
        'variants',
        'focal_x',
        'focal_y',
        'alt_text',
        'is_decorative',
        'description',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
            'focal_x' => 'float',
            'focal_y' => 'float',
            'is_decorative' => 'boolean',
        ];
    }

    /**
     * Validation rules for the focal point (percent from the left / top).
     * Shared by the backend form and the API.
     *
     * @return array<string, list<string>>
     */
    public static function focalPointRules(): array
    {
        return [
            'focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'focal_y' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * The stored values for a submitted focal point: both null (center) when
     * neither is given, otherwise both set — a missing half is the center.
     *
     * @return array{focal_x: ?float, focal_y: ?float}
     */
    public static function focalPointFrom(mixed $x, mixed $y): array
    {
        $x = is_numeric($x) ? round(max(0, min(100, (float) $x)), 2) : null;
        $y = is_numeric($y) ? round(max(0, min(100, (float) $y)), 2) : null;

        if ($x === null && $y === null) {
            return ['focal_x' => null, 'focal_y' => null];
        }

        return ['focal_x' => $x ?? 50.0, 'focal_y' => $y ?? 50.0];
    }

    /** True when the editor moved the focal point away from the center */
    public function hasFocalPoint(): bool
    {
        if ($this->focal_x === null && $this->focal_y === null) {
            return false;
        }

        return (float) ($this->focal_x ?? 50) !== 50.0 || (float) ($this->focal_y ?? 50) !== 50.0;
    }

    /**
     * CSS position of the focal point ("30% 70%") for object-position or
     * background-position; null for the center (the browser default).
     */
    public function focalPosition(): ?string
    {
        if (! $this->hasFocalPoint()) {
            return null;
        }

        return self::percent($this->focal_x ?? 50).' '.self::percent($this->focal_y ?? 50);
    }

    /** 30.5 → "30.5%", 30.0 → "30%" (always a plain number: no locale, no exponent) */
    private static function percent(float|int $value): string
    {
        $number = rtrim(rtrim(number_format(max(0, min(100, (float) $value)), 2, '.', ''), '0'), '.');

        return ($number === '' ? '0' : $number).'%';
    }

    /** An image that is not marked decorative but has no alt text (in the current language) */
    public function needsAltText(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/')
            && ! $this->is_decorative
            && trim((string) $this->alt_text) === '';
    }

    /**
     * The alt attribute for this image: empty for decorative images, otherwise
     * the alt text in the current language ('' when none was entered).
     */
    public function altAttribute(): string
    {
        return $this->is_decorative ? '' : trim((string) $this->alt_text);
    }

    /**
     * Find a library entry by what content stores: "media/…", "storage/media/…"
     * or the full asset URL.
     */
    public static function findByPath(?string $path): ?self
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        $storageUrl = rtrim(asset('storage'), '/').'/';
        if (str_starts_with($path, $storageUrl)) {
            $path = substr($path, strlen($storageUrl));
        }
        $path = preg_replace('#^/?storage/#', '', $path);

        return static::where('path', $path)->first();
    }

    /**
     * Scope: images that are neither decorative nor have an alt text. Checks
     * the original values — translations are maintained per language.
     */
    public function scopeMissingAltText($query)
    {
        return $query->where('mime_type', 'like', 'image/%')
            ->where(fn ($q) => $q->where('is_decorative', false)->orWhereNull('is_decorative'))
            ->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''));
    }

    /**
     * Scope: filter by media type (image, document, video, audio).
     */
    public function scopeOfType($query, ?string $type)
    {
        $map = [
            'image' => 'image/%',
            'document' => 'application/%',
            'video' => 'video/%',
            'audio' => 'audio/%',
            // Caption files for videos (WebVTT)
            'captions' => 'text/vtt',
        ];

        if ($type && isset($map[$type])) {
            $query->where('mime_type', 'like', $map[$type]);
        }

        return $query;
    }

    /**
     * Scope: search by name, alt_text, or description.
     */
    public function scopeSearch($query, ?string $search)
    {
        if ($search === null || $search === '') {
            return $query;
        }

        // Escape LIKE wildcards with a driver-portable escape character and an
        // explicit ESCAPE clause, so that '%' and '_' are matched literally on
        // every driver — including SQLite, which has no default LIKE escape
        // character. Backslash cannot be used here because MySQL treats it
        // specially inside string literals (ESCAPE '\' breaks); '=' is
        // literal-safe on MySQL, MariaDB, PostgreSQL and SQLite. The escape
        // character itself is escaped first.
        $escaped = str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search);
        $like = "%{$escaped}%";

        return $query->where(function ($q) use ($like) {
            $q->whereRaw("name LIKE ? ESCAPE '='", [$like])
                ->orWhereRaw("alt_text LIKE ? ESCAPE '='", [$like])
                ->orWhereRaw("description LIKE ? ESCAPE '='", [$like]);
        });
    }

    /**
     * Get the user that owns the media.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the URL for the media.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * Get the thumbnail URL for the media.
     */
    public function getThumbnailUrlAttribute(): string
    {
        if (! $this->thumbnail_path) {
            return $this->getPlaceholderUrl();
        }

        return asset('storage/'.$this->thumbnail_path);
    }

    /**
     * Get a placeholder URL based on the mime type.
     */
    public function getPlaceholderUrl(): string
    {
        $type = explode('/', $this->mime_type)[0];

        switch ($type) {
            case 'image':
                return $this->url; // Use the actual image
            case 'video':
                return asset('images/placeholders/video.svg');
            case 'audio':
                return asset('images/placeholders/audio.svg');
            case 'application':
                if (str_contains($this->mime_type, 'pdf')) {
                    return asset('images/placeholders/pdf.svg');
                } elseif (str_contains($this->mime_type, 'excel') || str_contains($this->mime_type, 'spreadsheet') || str_contains($this->mime_type, 'sheet')) {
                    return asset('images/placeholders/xls.svg');
                } elseif (str_contains($this->mime_type, 'powerpoint') || str_contains($this->mime_type, 'presentation')) {
                    return asset('images/placeholders/ppt.svg');
                } elseif (str_contains($this->mime_type, 'word') || str_contains($this->mime_type, 'msword')) {
                    return asset('images/placeholders/doc.svg');
                } else {
                    return asset('images/placeholders/file.svg');
                }
            default:
                return asset('images/placeholders/file.svg');
        }
    }

    /**
     * Format the file size in a human-readable way.
     */
    public function getFormattedSizeAttribute(): string
    {
        return StorageHelper::formatFileSize($this->size);
    }

    /**
     * Get the type of the media.
     */
    public function getTypeAttribute(): string
    {
        $type = explode('/', $this->mime_type)[0];

        switch ($type) {
            case 'image':
                return 'image';
            case 'video':
                return 'video';
            case 'audio':
                return 'audio';
            case 'application':
                if (str_contains($this->mime_type, 'pdf')) {
                    return 'pdf';
                } elseif (str_contains($this->mime_type, 'excel') || str_contains($this->mime_type, 'spreadsheet') || str_contains($this->mime_type, 'sheet')) {
                    return 'xls';
                } elseif (str_contains($this->mime_type, 'powerpoint') || str_contains($this->mime_type, 'presentation')) {
                    return 'ppt';
                } elseif (str_contains($this->mime_type, 'word') || str_contains($this->mime_type, 'msword')) {
                    return 'doc';
                } else {
                    return 'file';
                }
            default:
                return 'file';
        }
    }
}
