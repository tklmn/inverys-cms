<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Settings for the sitemap and robots.txt (Settings → SEO).
 *
 * Both apply per website, not per language — so the values are not
 * translatable, only the interface is.
 */
final class SeoSettings
{
    /** Allowed sitemap cache durations in minutes (0 = no cache) */
    public const CACHE_MINUTES = [0, 15, 60, 360, 1440];

    public const DEFAULT_CACHE_MINUTES = 60;

    /** Maximum length of the additional robots.txt rules */
    public const ROBOTS_EXTRA_MAX = 5000;

    /** Search engine tools for submitting the sitemap (pings no longer exist) */
    public const GOOGLE_SEARCH_CONSOLE_URL = 'https://search.google.com/search-console';

    public const BING_WEBMASTER_URL = 'https://www.bing.com/webmasters';

    /** robots.txt modes: generated automatically, custom content, no robots.txt */
    public const ROBOTS_AUTO = 'auto';

    public const ROBOTS_CUSTOM = 'custom';

    public const ROBOTS_OFF = 'off';

    public const ROBOTS_MODES = [self::ROBOTS_AUTO, self::ROBOTS_CUSTOM, self::ROBOTS_OFF];

    /** Maximum length of the custom robots.txt content */
    public const ROBOTS_CUSTOM_MAX = 10000;

    /** Directives allowed in the additional rules (lowercase) */
    public const ROBOTS_DIRECTIVES = ['user-agent', 'allow', 'disallow', 'crawl-delay', 'host'];

    public static function sitemapEnabled(): bool
    {
        return setting('seo.sitemap_enabled', 'on') === 'on';
    }

    /** Is the sitemap provider with this key included? Default: yes */
    public static function sitemapIncludes(string $key): bool
    {
        return setting('seo.sitemap_include_'.$key, 'on') === 'on';
    }

    public static function sitemapCacheMinutes(): int
    {
        $minutes = (int) setting('seo.sitemap_cache_ttl', (string) self::DEFAULT_CACHE_MINUTES);

        return in_array($minutes, self::CACHE_MINUTES, true) ? $minutes : self::DEFAULT_CACHE_MINUTES;
    }

    /**
     * Search engines allowed? Off ("block", e.g. for test environments):
     * robots.txt disallows everything, the frontend sends X-Robots-Tag and
     * <meta name="robots"> with noindex, nofollow.
     */
    public static function allowIndexing(): bool
    {
        return setting('seo.robots_allow_indexing', 'on') === 'on';
    }

    /** Header or meta value while search engines are blocked */
    public const NOINDEX = 'noindex, nofollow';

    /** robots.txt: generated automatically, custom content or none at all (404) */
    public static function robotsMode(): string
    {
        $mode = (string) setting('seo.robots_mode', self::ROBOTS_AUTO);

        return in_array($mode, self::ROBOTS_MODES, true) ? $mode : self::ROBOTS_AUTO;
    }

    /** Sitemap line in robots.txt — this is how Google & co. find the sitemap */
    public static function robotsSitemapLine(): bool
    {
        return setting('seo.robots_sitemap_line', 'on') === 'on';
    }

    public static function robotsExtra(): string
    {
        return self::normalize((string) setting('seo.robots_extra', ''));
    }

    public static function robotsCustom(): string
    {
        return self::normalize((string) setting('seo.robots_custom', ''));
    }

    /**
     * Content of /robots.txt according to the current settings — null if
     * there should be none ("Off" mode: the route responds with 404).
     *
     * Blocked search engines take precedence over every mode: then always
     * "Disallow: /" and no reference to the sitemap.
     */
    public static function robotsTxt(): ?string
    {
        if (! self::allowIndexing()) {
            return "User-agent: *\nDisallow: /\n";
        }

        $mode = self::robotsMode();

        if ($mode === self::ROBOTS_OFF) {
            return null;
        }

        if ($mode === self::ROBOTS_CUSTOM) {
            $blocks = array_filter([self::robotsCustom()], fn ($block) => $block !== '');
        } else {
            $blocks = ["User-agent: *\nDisallow:"];

            if (($extra = self::robotsExtra()) !== '') {
                $blocks[] = $extra;
            }
        }

        // The index is enough — it references the per-language sitemaps itself.
        // Sitemap lines in hand-written custom content take precedence.
        if (self::sitemapEnabled() && self::robotsSitemapLine() && ! self::hasSitemapLine(implode("\n", $blocks))) {
            $blocks[] = 'Sitemap: '.route('sitemap');
        }

        return $blocks === [] ? '' : implode("\n\n", $blocks)."\n";
    }

    public static function hasSitemapLine(string $rules): bool
    {
        return preg_match('/^\s*sitemap\s*:/im', $rules) === 1;
    }

    /**
     * First invalid line, otherwise null. Allowed are blank lines,
     * comments (#) and "directive: value" with the directives from
     * ROBOTS_DIRECTIVES — no HTML. With $allowSitemap (custom content) also
     * "Sitemap: <absolute http(s) URL>".
     */
    public static function invalidRobotsLine(string $rules, bool $allowSitemap = false): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $rules) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                if (! preg_match('/[<>]/', $trimmed)) {
                    continue;
                }

                return $trimmed;
            }

            if (! preg_match('/^([A-Za-z-]+)\s*:\s*([^<>]*)$/', $trimmed, $m)) {
                return $trimmed;
            }

            $directive = strtolower($m[1]);

            if ($directive === 'sitemap' && $allowSitemap) {
                if (! preg_match('#^https?://\S+$#i', trim($m[2]))) {
                    return $trimmed;
                }

                continue;
            }

            if (! in_array($directive, self::ROBOTS_DIRECTIVES, true)) {
                return $trimmed;
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }
}
