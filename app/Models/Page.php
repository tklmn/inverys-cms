<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Support\BlockText;
use App\Support\Locales;
use App\Traits\HasRevisions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, HasRevisions, SoftDeletes;
    use HasTranslations {
        saveTranslations as saveTranslationsFromTrait;
    }

    /**
     * Fields that can differ per language (multilingual plugin).
     * Everything else — status, parent, template, images — applies to all languages.
     */
    protected array $translatable = [
        'title', 'slug', 'subtitle', 'nav_title', 'content', 'blocks',
        'meta_title', 'meta_description', 'og_title', 'og_description', 'aria_label',
    ];

    /**
     * The attributes that are mass assignable.
     * All new fields are included and should be translatable if needed.
     */
    protected $fillable = [
        'title', 'slug', 'content', 'blocks', 'content_type',
        'parent_id', 'level', 'order',
        'is_active', 'hide_in_menu', 'is_root',
        'meta_title', 'meta_description', 'meta_robots',
        'og_title', 'og_description', 'og_image', 'canonical_url',
        'aria_label',
        'template', 'subtitle', 'nav_title', 'external_url',
        'external_url_new_window', 'meta_author', 'include_in_search',
        'header_image', 'extra_css',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hide_in_menu' => 'boolean',
        'is_root' => 'boolean',
        'external_url_new_window' => 'boolean',
        'include_in_search' => 'boolean',
        'blocks' => 'array',
    ];

    /**
     * An external link pointing to this site (e.g. the blog menu item
     * "/blog") is treated like an internal one in the frontend: with the
     * language prefix of the current language. The automatic prefix logic
     * does not cover absolute URLs otherwise — on a German page the blog link
     * led back to the default language.
     */
    public function getExternalUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        // Path on this site ("/blog"): always via url() — so subdirectory,
        // domain and (in the frontend) the language prefix are correct
        if (self::isSitePath($value)) {
            return url($value);
        }

        if (app(Locales::class)->visitor() === null) {
            return $value;
        }

        // Older entries with the site's own full URL
        $path = self::sitePathOf($value);

        return $path !== null ? url($path) : $value;
    }

    /**
     * Store links to the site itself as a path ("/blog" instead of
     * "https://example.com/blog") — otherwise the menu item breaks after a
     * domain or directory change. External URLs stay as they are.
     */
    public function setExternalUrlAttribute(?string $value): void
    {
        $value = $value !== null ? trim($value) : null;

        $this->attributes['external_url'] = $value === null || $value === ''
            ? null
            : (self::sitePathOf($value) ?? $value);
    }

    private static function isSitePath(string $value): bool
    {
        return str_starts_with($value, '/') && ! str_starts_with($value, '//');
    }

    /** Path of a URL on this site (with the request's base URL or APP_URL), otherwise null */
    private static function sitePathOf(string $value): ?string
    {
        if (self::isSitePath($value)) {
            return $value;
        }

        $roots = [rtrim((string) config('app.url'), '/')];
        if (app()->bound('request') && ! app()->runningInConsole()) {
            $request = request();
            $roots[] = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        }

        foreach (array_filter(array_unique($roots)) as $root) {
            if ($value === $root || str_starts_with($value, $root.'/')) {
                return substr($value, strlen($root)) ?: '/';
            }
        }

        return null;
    }

    public function getRevisionableFields(): array
    {
        return [
            'title', 'slug', 'content', 'blocks', 'content_type',
            'subtitle', 'nav_title', 'is_active', 'hide_in_menu', 'is_root',
            'template', 'header_image', 'extra_css',
            'meta_title', 'meta_description', 'meta_robots', 'meta_author',
            'og_title', 'og_description', 'og_image', 'canonical_url',
            'aria_label', 'include_in_search',
            'external_url', 'external_url_new_window',
        ];
    }

    /**
     * The categories that belong to the page.
     */
    public function categories()
    {
        return $this->belongsToMany(PageCategory::class, 'page_category_page', 'page_id', 'page_category_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = $page->is_root ? '/' : Str::slug($page->title);
            }
        });

        static::saving(function ($page) {
            // Set level from parent
            if ($page->parent_id) {
                $parent = self::find($page->parent_id);
                $page->level = $parent ? $parent->level + 1 : 0;
            } else {
                $page->level = 0;
            }

            // Ensure slug is never empty
            if (empty($page->slug) && ! $page->is_root) {
                $page->slug = Str::slug($page->title);
            }

            // When becoming homepage: release "/" from old homepage first, then claim it
            if ($page->is_root && $page->isDirty('is_root')) {
                $oldHome = self::where('id', '!=', $page->id)
                    ->where('slug', '/')
                    ->first();

                if ($oldHome) {
                    $newSlug = Str::slug($oldHome->title);
                    // Ensure unique slug (including trashed rows, which still occupy the unique index)
                    $base = $newSlug;
                    $i = 2;
                    while (self::withTrashed()->where('slug', $newSlug)->where('id', '!=', $oldHome->id)->exists()) {
                        $newSlug = $base.'-'.$i++;
                    }
                    $oldHome->is_root = false;
                    $oldHome->slug = $newSlug;
                    $oldHome->saveQuietly();
                }

                $page->slug = '/';
            } elseif ($page->is_root) {
                $page->slug = '/';
            }

            $page->normalizeContent();
        });

        static::saved(function ($page) {
            if ($page->is_root) {
                // Clear is_root on all others
                self::where('id', '!=', $page->id)
                    ->where('is_root', true)
                    ->update(['is_root' => false]);
            }
        });
    }

    /**
     * Pages consist only of blocks — there is one editor, not two.
     *
     * HTML that arrives as content (REST API, custom scripts) becomes a
     * text block. "content" then holds the readable
     * text of the blocks for search and short descriptions (BlockText).
     */
    public function normalizeContent(): void
    {
        $attributes = $this->getAttributes();
        $blocks = json_decode((string) ($attributes['blocks'] ?? ''), true);
        $blocks = is_array($blocks) ? $blocks : [];

        // Blocks without content_type (e.g. set via code or plugin) are
        // blocks — do not replace them with the empty HTML content
        if (($attributes['content_type'] ?? null) !== 'blocks' && $blocks !== [] && $this->isDirty('blocks') && ! $this->isDirty('content')) {
            $this->attributes['content_type'] = $attributes['content_type'] = 'blocks';
        }

        // HTML arrives: as an HTML page (the site showed the HTML, not the
        // blocks) or as new content without blocks. Only when "content"
        // changed — otherwise, after deleting all blocks, their old search
        // text would come back as a text block.
        if (($attributes['content_type'] ?? null) !== 'blocks' || ($blocks === [] && $this->isDirty('content'))) {
            $html = (string) ($attributes['content'] ?? '');
            $blocks = self::hasVisibleHtml($html) ? [self::textBlock($html)] : [];

            $this->attributes['content_type'] = 'blocks';
            $this->blocks = $blocks;
        }

        if ($this->isDirty(['blocks', 'content', 'content_type']) || ! $this->exists) {
            $this->attributes['content'] = BlockText::extract($blocks);
        }
    }

    /** A text block with this HTML — at readable line width */
    public static function textBlock(string $html, ?string $id = null): array
    {
        return [
            'id' => $id ?? 'block-'.Str::lower(Str::random(9)),
            'type' => 'text',
            'data' => ['content' => $html],
            'settings' => ['class' => '', 'measure' => 'readable'],
        ];
    }

    /** Does the HTML contain anything visible (text, image, video, table)? */
    public static function hasVisibleHtml(?string $html): bool
    {
        $html = (string) $html;

        if (preg_match('/<(img|video|iframe|embed|object|table)\b/i', $html)) {
            return true;
        }

        return trim(str_replace("\u{00A0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) !== '';
    }

    /**
     * Designed page (hero, sections, columns …) instead of plain text?
     * The automatic subpage list only appears below plain pages —
     * designed ones usually link their subpages themselves.
     */
    public function usesLayoutBlocks(): bool
    {
        $layout = ['hero', 'section', 'container', 'columns', 'tabs', 'accordion', 'slider', 'pricing', 'stats', 'logos'];

        return collect($this->blocks ?? [])->contains(fn ($block) => in_array($block['type'] ?? '', $layout, true));
    }

    /** Translated blocks bring their search text along */
    public function saveTranslations(string $locale, array $values, bool $reviewed = true): void
    {
        if (array_key_exists('blocks', $values)) {
            $blocks = is_string($values['blocks']) ? json_decode($values['blocks'], true) : $values['blocks'];
            $values['content'] = is_array($blocks) && $blocks !== [] ? BlockText::extract($blocks) : null;
        }

        $this->saveTranslationsFromTrait($locale, $values, $reviewed);
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('order');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Build the URL path from the page's ancestry.
     * Guards against a missing/soft-deleted parent and against parent cycles.
     */
    public function getPath(): string
    {
        if ($this->is_root) {
            return '/';
        }

        $path = [$this->slug];
        $page = $this;
        $seen = [$page->id => true];

        while ($page->parent_id) {
            $parent = $page->parent;
            if (! $parent || isset($seen[$parent->id]) || $parent->is_root) {
                break;
            }
            $seen[$parent->id] = true;
            array_unshift($path, $parent->slug);
            $page = $parent;
        }

        return '/'.implode('/', $path);
    }

    public function moveTo($newParentId = null, $newOrder = null): self
    {
        $oldParentId = $this->parent_id;

        $this->parent_id = $newParentId;

        if ($newOrder !== null) {
            $this->order = $newOrder;
        }

        if ($newParentId) {
            $parent = self::find($newParentId);
            $this->level = $parent ? $parent->level + 1 : 0;
        } else {
            $this->level = 0;
        }

        $this->save();

        // Keep the whole subtree's level consistent after the move.
        $this->updateDescendantLevels();

        // Reorder siblings at old parent
        if ($oldParentId) {
            $this->reorderSiblings($oldParentId);
        }

        // Reorder siblings at new parent if different
        if ($newParentId && $oldParentId !== $newParentId) {
            $this->reorderSiblings($newParentId);
        }

        return $this;
    }

    /**
     * Recursively set each descendant's level relative to this page.
     */
    protected function updateDescendantLevels(): void
    {
        foreach (self::where('parent_id', $this->id)->get() as $child) {
            $child->level = $this->level + 1;
            $child->saveQuietly();
            $child->updateDescendantLevels();
        }
    }

    protected function reorderSiblings($parentId)
    {
        $siblings = self::where('parent_id', $parentId)
            ->orderBy('order')
            ->pluck('id');

        if ($siblings->isEmpty()) {
            return;
        }

        $cases = [];
        $bindings = [];
        foreach ($siblings as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index;
        }

        $ids = $siblings->all();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            'UPDATE pages SET `order` = CASE id '.
            implode(' ', $cases).
            ' END WHERE id IN ('.$placeholders.')',
            array_merge($bindings, $ids)
        );
    }
}
