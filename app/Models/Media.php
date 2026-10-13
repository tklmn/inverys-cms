<?php

namespace App\Models;

use App\Helpers\StorageHelper;
use App\Models\Concerns\HasTranslations;
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
        'alt_text',
        'description',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
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
