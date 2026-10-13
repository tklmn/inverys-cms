<?php

namespace App\Helpers;

class PageRouteHelper
{
    /**
     * Returns the reserved slugs as an array.
     */
    public static function reservedSlugs(): array
    {
        return [
            // Application routes
            'backend', 'protected-assets', 'ext-assets', 'profile', 'email-change', 'api', 'livewire', 'up',
            // Auth routes
            'login', 'logout', 'register', 'password', 'email',
            'verify-email', 'confirm-password', 'forgot-password', 'reset-password',
            // Dev/debug tools
            '_ignition', 'telescope', 'nova', 'admin',
            // Static assets & system paths
            'storage', 'assets', 'js', 'css', 'images', 'img', 'favicon',
            'vendor', 'node_modules', 'public', 'resources', 'tests', 'lang',
            'config', 'database', 'routes', 'app', 'bootstrap',
            // Common files
            'robots.txt', 'sitemap.xml', 'index.php', 'favicon.ico',
            'artisan', 'composer.json', 'composer.lock',
            'package.json', 'package-lock.json', 'phpunit.xml',
            'postcss.config.js', 'tailwind.config.js', 'vite.config.js',
            '.env', '.git', '.gitattributes', '.gitignore', '.editorconfig', 'README.md',
        ];
    }

    /**
     * Returns the regex string for allowed slugs (excludes reserved/system paths).
     */
    public static function slugRegex(): string
    {
        $reserved = array_map(function ($slug) {
            return preg_quote($slug, '/');
        }, self::reservedSlugs());

        // Sitemaps per language (sitemap-de.xml …) — a pattern instead of a list
        $reserved[] = 'sitemap-'.self::SITEMAP_LOCALE_PATTERN.'\.xml';

        return '(?!(?:'.implode('|', $reserved).')$)[^/]+';
    }

    /** Language code in the name of the per-language sitemap: de, en, pt-BR, zh_Hant … */
    public const SITEMAP_LOCALE_PATTERN = '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?';
}
