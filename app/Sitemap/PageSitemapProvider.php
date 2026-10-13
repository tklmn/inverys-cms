<?php

namespace App\Sitemap;

use App\Contracts\SitemapProvider;
use App\Models\Page;

/**
 * Pages for the sitemap: active pages with their own content. External links
 * (menu items like "Blog") and pages with "noindex" are left out; the
 * home page appears under the home URL, not under its slug.
 */
class PageSitemapProvider implements SitemapProvider
{
    public function key(): string
    {
        return 'pages';
    }

    /** Pages are always included — no toggle */
    public function label(): ?string
    {
        return null;
    }

    public function entries(): iterable
    {
        $pages = Page::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('external_url')->orWhere('external_url', ''))
            ->orderByDesc('is_root')
            ->orderBy('level')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            if (self::isNoindex($page->meta_robots)) {
                continue;
            }

            yield [
                'loc' => $page->is_root ? route('home') : route('page.show', $page->slug),
                'lastmod' => $page->updated_at,
                'model' => $page,
            ];
        }
    }

    /** "noindex" or "none" (= noindex, nofollow) in the page's robots field */
    public static function isNoindex(?string $robots): bool
    {
        return $robots !== null && preg_match('/\b(noindex|none)\b/i', $robots) === 1;
    }
}
