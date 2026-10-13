<?php

use App\Contracts\SettingRepository;
use App\Helpers\HtmlHelper;
use App\Services\BlockRenderer;
use App\Services\CmsManager;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Support\ThemeTranslations;

if (! function_exists('registration_open')) {
    /**
     * May visitors create an account themselves? (Security → Allow registration)
     * Themes only show the register link in that case.
     */
    function registration_open(): bool
    {
        return setting('security.registration_enabled', 'on') !== 'off';
    }
}

if (! function_exists('cms')) {
    /**
     * Get the CMS manager instance — the public API for plugins and themes.
     */
    function cms(): CmsManager
    {
        return app(CmsManager::class);
    }
}

if (! function_exists('theme')) {
    /**
     * Get a setting value for the active theme (cached per request).
     *
     * Never saved (freshly activated theme, customizer never opened):
     * the default from theme.json, only then $default. Otherwise e.g. the
     * logo would be missing until someone saves once in the customizer.
     */
    function theme(string $key, mixed $default = null): mixed
    {
        $tm = app(ThemeManager::class);
        $slug = request()->is('backend*') ? ($tm->getActive() ?? 'default') : $tm->getActiveForUser();

        $value = app(SettingRepository::class)->get("theme.{$slug}.{$key}");
        if ($value === null) {
            $value = $tm->getSettingsSchema((string) $slug)[$key]['default'] ?? $default;
        }

        // Multilingual: texts (text/textarea) in the visitor's language
        return ThemeTranslations::setting($slug, $key, $value);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * Address of a file from the active theme's assets/, e.g.
     * theme_asset('css/theme.css') — cached in the browser for a year.
     */
    function theme_asset(string $path): string
    {
        $tm = app(ThemeManager::class);
        $slug = request()->is('backend*') ? ($tm->getActive() ?? 'default') : $tm->getActiveForUser();

        return $tm->getAssetUrl((string) $slug, $path);
    }
}

if (! function_exists('plugin_asset')) {
    /**
     * Address of a file from a plugin's assets/, e.g.
     * plugin_asset('portfolio', 'css/blocks.css') — cached in the browser for a year.
     */
    function plugin_asset(string $slug, string $path): string
    {
        $version = (string) (app(PluginManager::class)->get($slug)['version'] ?? '');

        return asset('ext-assets/plugin/'.$slug.'/'.ltrim($path, '/')).($version !== '' ? '?v='.rawurlencode($version) : '');
    }
}

if (! function_exists('theme_region')) {
    /**
     * Render a block-based region for the active theme (cached per request).
     */
    function theme_region(string $key): string
    {
        static $cache = [];
        if (! array_key_exists($key, $cache)) {
            $tm = app(ThemeManager::class);
            $slug = request()->is('backend*') ? ($tm->getActive() ?? 'default') : $tm->getActiveForUser();

            $value = app(SettingRepository::class)->get("theme.{$slug}.region.{$key}");

            // Multilingual: the region's blocks in the visitor's language
            $value = ThemeTranslations::region($slug, $key, $value);

            if (! $value || empty($value)) {
                $cache[$key] = '';
            } else {
                $blocks = json_decode($value, true);
                $cache[$key] = (is_array($blocks) && ! empty($blocks))
                    ? app(BlockRenderer::class)->render($blocks)
                    : '';
            }
        }

        return $cache[$key];
    }
}

if (! function_exists('setting')) {
    /**
     * Get a global setting value by key (cached per request).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingRepository::class)->get($key, $default);
    }
}

if (! function_exists('theme_custom_css')) {
    /**
     * Get custom CSS for the active theme (cached per request).
     */
    function theme_custom_css(): string
    {
        static $cache = null;
        if ($cache === null) {
            $tm = app(ThemeManager::class);
            $slug = request()->is('backend*') ? ($tm->getActive() ?? 'default') : $tm->getActiveForUser();

            $value = app(SettingRepository::class)->get("theme.{$slug}.custom_css");
            $cache = $value ? HtmlHelper::cleanCss($value) : '';
        }

        return $cache;
    }
}
