<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * The <title> of a frontend page from the page title and the site name,
 * following Settings → SEO (order and separator). Default: "Title – Site".
 *
 * Paginated lists (blog, search) get "– Page N" after the title, so pages
 * 2, 3 … do not have the same title as page 1.
 *
 * Themes use it in their layout: <title>{{ seo_title($title ?? null) }}</title>
 */
final class DocumentTitle
{
    public const ORDER_TITLE_FIRST = 'title_site';

    public const ORDER_SITE_FIRST = 'site_title';

    public const ORDER_TITLE_ONLY = 'title';

    public const ORDERS = [self::ORDER_TITLE_FIRST, self::ORDER_SITE_FIRST, self::ORDER_TITLE_ONLY];

    public const SEPARATORS = ['–', '—', '|', '·', '/', ':', '-'];

    public const DEFAULT_SEPARATOR = '–';

    public static function make(?string $pageTitle): string
    {
        $siteName = (string) setting('site_name', __('translation.app_name'));
        $title = trim(html_entity_decode((string) $pageTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $separator = self::separator();

        $page = app(SeoContext::class)->page;
        if ($title !== '' && $page > 1) {
            $title .= ' '.$separator.' '.__('translation.seo.page_number', ['page' => $page]);
        }

        if ($title === '') {
            return $siteName;
        }

        // Do not repeat the site name in titles that already contain it ("About us | Inverys CMS")
        if ($siteName === '' || str_contains($title, $siteName)) {
            return $title;
        }

        return match (self::order()) {
            self::ORDER_SITE_FIRST => $siteName.' '.$separator.' '.$title,
            self::ORDER_TITLE_ONLY => $title,
            default => $title.' '.$separator.' '.$siteName,
        };
    }

    public static function separator(): string
    {
        $separator = (string) setting('seo.title_separator', self::DEFAULT_SEPARATOR);

        return in_array($separator, self::SEPARATORS, true) ? $separator : self::DEFAULT_SEPARATOR;
    }

    public static function order(): string
    {
        $order = (string) setting('seo.title_order', self::ORDER_TITLE_FIRST);

        return in_array($order, self::ORDERS, true) ? $order : self::ORDER_TITLE_FIRST;
    }
}
