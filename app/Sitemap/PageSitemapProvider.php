<?php

namespace App\Sitemap;

use App\Contracts\SitemapProvider;
use App\Models\Page;
use App\Support\Seo\SeoContext;
use App\Support\Seo\Urls;

/**
 * Pages for the sitemap: active pages with their own content. External links
 * (menu items like "Blog"), pages with "noindex" and pages whose canonical
 * address names another page are left out; the home page appears under the
 * home URL, not under its slug. Social and header image go along as images.
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

            $loc = $page->is_root ? route('home') : route('page.show', $page->slug);

            // The canonical address names another page: only that one belongs in the sitemap
            if (self::pointsElsewhere($page->canonical_url, $loc)) {
                continue;
            }

            yield [
                'loc' => $loc,
                'lastmod' => $page->updated_at,
                'model' => $page,
                'images' => array_values(array_unique(array_map(
                    fn (string $image) => Urls::absolute($image),
                    array_filter([$page->og_image, $page->header_image], fn ($image) => is_string($image) && trim($image) !== ''),
                ))),
            ];
        }
    }

    /** Does an explicit canonical address point to another address than $loc? */
    public static function pointsElsewhere(?string $canonical, string $loc): bool
    {
        if ($canonical === null || trim($canonical) === '') {
            return false;
        }

        return SeoContext::normalizeUrl(rtrim(Urls::absolute($canonical), '/')) !== SeoContext::normalizeUrl(rtrim($loc, '/'));
    }

    /** "noindex" or "none" (= noindex, nofollow) in the page's robots field */
    public static function isNoindex(?string $robots): bool
    {
        return $robots !== null && preg_match('/\b(noindex|none)\b/i', $robots) === 1;
    }
}
