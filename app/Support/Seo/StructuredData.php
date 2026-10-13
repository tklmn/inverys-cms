<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Support\SeoSettings;
use DateTimeInterface;

/**
 * JSON-LD (schema.org) for the current page as one @graph:
 *
 *   home page   WebSite (+ SearchAction when guests may search), Organization, WebPage
 *   page        WebPage + BreadcrumbList
 *   blog list   CollectionPage + BreadcrumbList
 *   blog post   BlogPosting + BreadcrumbList
 */
final class StructuredData
{
    /** @return array<string, mixed>|null the JSON-LD document, null if there is nothing to say */
    public static function build(SeoContext $seo, string $title, ?string $description, ?string $image): ?array
    {
        $home = self::homeUrl();
        $url = $seo->canonicalUrl();
        $locale = LanguageCodes::hreflang($seo->contentLocale());
        $siteName = (string) setting('site_name', __('translation.app_name'));
        $graph = [];

        if ($seo->home) {
            $website = [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'url' => $home,
                'name' => $siteName,
                'inLanguage' => $locale,
                'publisher' => ['@id' => $home.'#organization'],
            ];

            if (setting('search.guest_access', 'on') !== 'off') {
                $website['potentialAction'] = [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => route('search').'?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ];
            }

            $graph[] = $website;
            $graph[] = self::organization($home, $siteName);
        }

        $breadcrumb = self::breadcrumb($seo, $url, $title);

        if ($seo->schemaType === 'BlogPosting') {
            $graph[] = array_filter([
                '@type' => 'BlogPosting',
                '@id' => $url.'#article',
                'mainEntityOfPage' => $url,
                'url' => $url,
                'headline' => mb_substr($title, 0, 110),
                'description' => $description,
                'image' => $image !== null ? [$image] : null,
                'datePublished' => self::date($seo->article['published_time'] ?? null),
                'dateModified' => self::date($seo->article['modified_time'] ?? $seo->article['published_time'] ?? null),
                'author' => ! empty($seo->article['author']) ? ['@type' => 'Person', 'name' => (string) $seo->article['author']] : null,
                'publisher' => self::publisher($home, $siteName),
                'articleSection' => $seo->article['section'] ?? null,
                'inLanguage' => $locale,
                'isPartOf' => ['@id' => $home.'#website'],
            ], fn ($value) => $value !== null && $value !== '');
        } elseif ($seo->schemaType !== null) {
            $graph[] = array_filter([
                '@type' => $seo->schemaType,
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $title,
                'description' => $description,
                'inLanguage' => $locale,
                'isPartOf' => ['@id' => $home.'#website'],
                'primaryImageOfPage' => $image !== null ? ['@type' => 'ImageObject', 'url' => $image] : null,
                'breadcrumb' => $breadcrumb !== null ? ['@id' => $url.'#breadcrumb'] : null,
            ], fn ($value) => $value !== null && $value !== '');
        }

        if ($breadcrumb !== null) {
            $graph[] = $breadcrumb;
        }

        return $graph === [] ? null : ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * Encoded for a <script> element: "<", ">", "&" and quotes as \u escapes,
     * so no content can close the script or break out of it.
     */
    public static function encode(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private static function organization(string $home, string $siteName): array
    {
        $logo = SeoSettings::organizationLogo();

        return array_filter([
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => $siteName,
            'url' => $home,
            'logo' => $logo !== null ? ['@type' => 'ImageObject', 'url' => Urls::absolute($logo)] : null,
            'sameAs' => SeoSettings::socialProfiles() ?: null,
        ], fn ($value) => $value !== null);
    }

    private static function publisher(string $home, string $siteName): array
    {
        $logo = SeoSettings::organizationLogo();

        return array_filter([
            '@type' => 'Organization',
            '@id' => $home.'#organization',
            'name' => $siteName,
            'logo' => $logo !== null ? ['@type' => 'ImageObject', 'url' => Urls::absolute($logo)] : null,
        ], fn ($value) => $value !== null);
    }

    /** Home → … → this page; nothing on the home page itself */
    private static function breadcrumb(SeoContext $seo, string $url, string $title): ?array
    {
        if ($seo->home || ! $seo->filled || $seo->schemaType === null) {
            return null;
        }

        $items = [['name' => __('translation.pages.home'), 'url' => self::homeUrl()]];
        foreach ($seo->breadcrumbs as $crumb) {
            $items[] = $crumb;
        }
        $items[] = ['name' => $title, 'url' => $url];

        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => (string) $item['name'],
                'item' => (string) $item['url'],
            ];
        }

        return ['@type' => 'BreadcrumbList', '@id' => $url.'#breadcrumb', 'itemListElement' => $list];
    }

    private static function homeUrl(): string
    {
        return SeoContext::normalizeUrl(route('home'));
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
