<?php

namespace Plugins\Blog\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RedirectsOnSlugChange;
use App\Sitemap\SitemapGenerator;
use App\Traits\HasRevisions;
use App\Traits\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Plugins\ActivityLog\Traits\LogsActivity;

class Post extends Model
{
    use HasRevisions;
    use HasTranslations;
    use HasUniqueSlug;
    use LogsActivity;
    use RedirectsOnSlugChange;
    use SoftDeletes;

    protected static function booted(): void
    {
        // Published, changed or deleted: the sitemap is rebuilt
        static::saved(fn () => SitemapGenerator::forget());
        static::deleted(fn () => SitemapGenerator::forget());
        static::restored(fn () => SitemapGenerator::forget());
    }

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

    /**
     * The permission a post needs to end up in this state: publish-post when
     * it is live, schedule-post when it waits for its date, none for a draft.
     */
    public static function statusPermission(bool $isActive, mixed $publishedAt): ?string
    {
        if (! $isActive) {
            return null;
        }

        return ! empty($publishedAt) && Carbon::parse($publishedAt)->isFuture() ? 'schedule-post' : 'publish-post';
    }

    /**
     * One approval rule for every way a post changes (backend form, API,
     * restoring a revision): whoever leaves a post published or scheduled
     * needs the matching permission, edit-post alone is not enough.
     */
    public static function authorizeStatus(bool $isActive, mixed $publishedAt): void
    {
        $permission = self::statusPermission($isActive, $publishedAt);

        abort_if(
            $permission !== null && ! cms()->permissions()->userHasPermission($permission),
            403,
            __('blog::blog.no_permission_status'),
        );
    }
}
