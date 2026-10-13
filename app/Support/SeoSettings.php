<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Settings for the sitemap, robots.txt and what every page tells search
 * engines and social networks (Settings → SEO).
 *
 * They apply per website, not per language — only the default description
 * is translatable (TranslatableSettings, maintained under Languages → Texts).
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

    /** Maximum length of the default meta description */
    public const DEFAULT_DESCRIPTION_MAX = 300;

    /** Maximum number of social profile addresses (JSON-LD sameAs) */
    public const SOCIAL_PROFILES_MAX = 20;

    /** Image for social networks when a page has none of its own (path in public/ or URL) */
    public static function defaultImage(): ?string
    {
        $value = trim((string) setting('seo.default_og_image', ''));

        return $value !== '' ? $value : null;
    }

    /** Logo for the organisation in JSON-LD: own setting, otherwise the theme logo */
    public static function organizationLogo(): ?string
    {
        $value = trim((string) setting('seo.organization_logo', ''));
        if ($value === '') {
            $value = trim((string) theme('logo_url', ''));
        }

        return $value !== '' ? $value : null;
    }

    /** @return list<string> profile addresses of the organisation (sameAs) */
    public static function socialProfiles(): array
    {
        return self::parseProfiles((string) setting('seo.social_profiles', ''));
    }

    /** @return list<string> valid http(s) addresses, one per line, without duplicates */
    public static function parseProfiles(string $text): array
    {
        $urls = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $line)) {
                $urls[$line] = true;
            }
        }

        return array_slice(array_keys($urls), 0, self::SOCIAL_PROFILES_MAX);
    }

    /** Verification codes of the webmaster tools: [meta name => content] */
    public static function verificationTags(): array
    {
        return array_filter([
            'google-site-verification' => self::verificationCode((string) setting('seo.google_verification', '')),
            'msvalidate.01' => self::verificationCode((string) setting('seo.bing_verification', '')),
        ], fn ($code) => $code !== '');
    }

    /**
     * The code from a verification value — also from a pasted meta tag
     * (<meta name="google-site-verification" content="…">). Only characters
     * these codes consist of.
     */
    public static function verificationCode(string $value): string
    {
        $value = trim($value);

        if (preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $value, $m)) {
            $value = trim($m[1]);
        }

        return preg_match('/^[A-Za-z0-9_\-=+\/.:]{1,200}$/', $value) ? $value : '';
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
