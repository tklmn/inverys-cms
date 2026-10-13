<?php

namespace Plugins\Gallery\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\Locales;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GalleryAlbum extends Model
{
    use HasTranslations, HasUniqueSlug, SoftDeletes;

    /**
     * Multilingual: name, description and captions.
     * `captions` is not a column: { "<media ID>": "<caption>" } per language.
     */
    protected array $translatable = ['name', 'description', 'captions'];

    /**
     * Caption in the current language — falling back to the
     * original's caption and finally to the alt text.
     */
    public function captionFor($image): string
    {
        if (! $image) {
            return '';
        }

        if (app(Locales::class)->content() !== null) {
            $captions = $this->getAttribute('captions');
            $captions = is_string($captions) ? json_decode($captions, true) : $captions;
            $translated = is_array($captions) ? ($captions[(string) $image->id] ?? null) : null;

            if (filled($translated)) {
                return (string) $translated;
            }
        }

        return (string) ($image->pivot->caption ?? $image->alt_text ?? '');
    }

    protected $table = 'gallery_albums';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'settings',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function getSlugSource(): string
    {
        return 'name';
    }

    public function images()
    {
        return $this->belongsToMany(config('cms.models.media'), 'gallery_album_images', 'album_id', 'media_id')
            ->withPivot('sort_order', 'caption')
            ->orderByPivot('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(config('cms.models.user'), 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(config('cms.models.user'), 'updated_by');
    }
}
