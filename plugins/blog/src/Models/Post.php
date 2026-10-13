<?php

namespace Plugins\Blog\Models;

use App\Models\Concerns\HasTranslations;
use App\Traits\HasRevisions;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plugins\ActivityLog\Traits\LogsActivity;

class Post extends Model
{
    use HasRevisions;
    use HasTranslations;
    use HasUniqueSlug;
    use LogsActivity;
    use SoftDeletes;

    /** Fields that differ per language (Multilingual plugin) */
    protected array $translatable = [
        'title', 'slug', 'excerpt', 'content', 'blocks', 'meta_title', 'meta_description',
    ];

    public function getSlugSource(): string
    {
        return 'title';
    }

    protected $table = 'posts';

    protected $fillable = [
        'title', 'slug', 'content', 'content_type', 'blocks',
        'excerpt', 'featured_image', 'author_id', 'published_at',
        'is_active', 'meta_title', 'meta_description',
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'author_id');
    }

    public function categories()
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_post', 'post_id', 'blog_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Visible to visitors — the same conditions as scopePublished(). */
    public function isPublished(): bool
    {
        return $this->is_active && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** Active, but with a publication date in the future. */
    public function isScheduled(): bool
    {
        return $this->is_active && $this->published_at !== null && $this->published_at->isFuture();
    }
}
