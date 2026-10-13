<?php

namespace App\Services;

use App\Helpers\HtmlHelper;
use App\Models\Setting;
use App\Support\AssetVersion;
use App\Support\ThemeViews;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ThemeManager
{
    protected array $themes = [];

    protected ?string $activeTheme = null;

    public function boot(): void
    {
        $this->discover();
        $this->activeTheme = Setting::where('key', 'active_theme')->value('value') ?? 'default';

        // theme:: and the plugin view overrides of the active theme (including default)
        if ($this->has($this->activeTheme)) {
            app(ThemeViews::class)->useTheme($this->activeTheme);
        }
    }

    public function discover(): void
    {
        $this->themes = [];

        // 1. Directory-based themes
        $themesPath = config('cms.themes_path');
        if (is_dir($themesPath)) {
            foreach (File::directories($themesPath) as $dir) {
                $this->discoverFromDirectory($dir);
            }
        }

        // 2. Composer-installed themes (type: inverys-theme)
        $this->discoverComposerPackages('inverys-theme', $themesPath);
    }

    public function all(): array
    {
        return $this->themes;
    }

    public function has(string $slug): bool
    {
        return isset($this->themes[$slug]);
    }

    public function get(string $slug): ?array
    {
        return $this->themes[$slug] ?? null;
    }

    public function getActive(): ?string
    {
        return $this->activeTheme;
    }

    /**
     * Get the active theme for the current user (falls back to global).
     */
    public function getActiveForUser(): string
    {
        if (! request()->is('backend*') && auth()->check()) {
            $userTheme = auth()->user()->getSetting('frontend_theme');
            if ($userTheme && $this->has($userTheme)) {
                return $userTheme;
            }
        }

        return $this->activeTheme ?? 'default';
    }

    public function activate(string $slug): bool
    {
        if (! $this->has($slug)) {
            return false;
        }

        Setting::updateOrCreate(
            ['key' => 'active_theme'],
            ['value' => $slug, 'type' => 'string', 'description' => 'Active frontend theme']
        );

        $this->activeTheme = $slug;

        return true;
    }

    public function getThemePath(string $slug): string
    {
        $theme = $this->get($slug);

        return $theme['path'] ?? config('cms.themes_path').'/'.$slug;
    }

    /**
     * URL of a file from the theme's assets/ (ExtensionAssetController),
     * with the theme version — after an update the browser fetches it again.
     */
    public function getAssetUrl(string $slug, string $path): string
    {
        $version = (string) ($this->get($slug)['version'] ?? '');
        $file = $this->getThemePath($slug).'/assets/'.ltrim($path, '/');

        return asset('ext-assets/theme/'.$slug.'/'.ltrim($path, '/')).AssetVersion::query($version, $file);
    }

    /**
     * Get the settings schema for a theme from its theme.json.
     */
    public function getSettingsSchema(string $slug): array
    {
        $theme = $this->get($slug);

        return $theme['settings'] ?? [];
    }

    /**
     * Get the regions schema for a theme from its theme.json.
     */
    public function getRegionsSchema(string $slug): array
    {
        $theme = $this->get($slug);

        return $theme['regions'] ?? [];
    }

    /**
     * Allowed file extensions for the theme editor.
     */
    public const EDITABLE_EXTENSIONS = [
        'blade.php', 'php', 'html', 'css', 'scss', 'less',
        'js', 'json', 'xml', 'yaml', 'yml', 'md', 'txt', 'svg',
    ];

    /**
     * Check whether a theme allows file editing.
     */
    public function isEditable(string $slug): bool
    {
        $theme = $this->get($slug);
        if (! $theme) {
            return false;
        }

        $editable = $theme['editable'] ?? false;

        return $editable === true || (is_array($editable) && count($editable) > 0);
    }

    /**
     * Get the glob patterns that define which files are editable.
     */
    public function getEditablePatterns(string $slug): array
    {
        if (! $this->isEditable($slug)) {
            return [];
        }

        $editable = $this->get($slug)['editable'];

        return $editable === true ? ['**/*'] : (array) $editable;
    }

    /**
     * List all editable files for a theme, filtered by patterns and extension whitelist.
     *
     * @return array<string> Relative paths from theme root
     */
    public function getEditableFiles(string $slug): array
    {
        $patterns = $this->getEditablePatterns($slug);
        if (empty($patterns)) {
            return [];
        }

        $themePath = $this->getThemePath($slug);
        if (! is_dir($themePath)) {
            return [];
        }

        $allFiles = File::allFiles($themePath);
        $result = [];

        foreach ($allFiles as $file) {
            $relative = $file->getRelativePathname();

            // Skip theme.json — metadata should not be edited here
            if ($relative === 'theme.json') {
                continue;
            }

            // Skip .bak files
            if (str_ends_with($relative, '.bak')) {
                continue;
            }

            // Check extension whitelist
            if (! $this->hasAllowedExtension($relative)) {
                continue;
            }

            // Check against editable patterns
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $relative)) {
                    $result[] = $relative;
                    break;
                }
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Validate that a file path is safe and editable within the given theme.
     *
     * @return array{valid: bool, absolute_path?: string, error?: string}
     */
    public function validateFilePath(string $slug, string $relativePath): array
    {
        if (! $this->isEditable($slug)) {
            return ['valid' => false, 'error' => 'not_editable'];
        }

        $themePath = realpath($this->getThemePath($slug));
        if (! $themePath) {
            return ['valid' => false, 'error' => 'theme_not_found'];
        }

        $absolutePath = realpath($themePath.'/'.$relativePath);

        // File must exist and resolve within the theme directory
        if ($absolutePath === false || ! str_starts_with($absolutePath, $themePath.DIRECTORY_SEPARATOR)) {
            return ['valid' => false, 'error' => 'invalid_path'];
        }

        if (! is_file($absolutePath)) {
            return ['valid' => false, 'error' => 'file_not_found'];
        }

        // Extension whitelist
        if (! $this->hasAllowedExtension($relativePath)) {
            return ['valid' => false, 'error' => 'file_type_not_allowed'];
        }

        // Pattern match
        $patterns = $this->getEditablePatterns($slug);
        $matched = false;
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $relativePath)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            return ['valid' => false, 'error' => 'file_type_not_allowed'];
        }

        return ['valid' => true, 'absolute_path' => $absolutePath];
    }

    /**
     * Check if a filename has an allowed extension for editing.
     */
    protected function hasAllowedExtension(string $filename): bool
    {
        foreach (self::EDITABLE_EXTENSIONS as $ext) {
            if (str_ends_with($filename, '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all saved setting values for a theme.
     */
    public function getSavedSettings(string $slug): array
    {
        return cache()->remember("theme.{$slug}.settings", 3600, function () use ($slug) {
            $prefix = "theme.{$slug}.";
            $settings = Setting::where('key', 'like', $prefix.'%')
                ->where('key', 'not like', $prefix.'region.%')
                ->where('key', '!=', $prefix.'custom_css')
                ->pluck('value', 'key')
                ->toArray();

            $result = [];
            foreach ($settings as $key => $value) {
                $shortKey = str_replace($prefix, '', $key);
                $result[$shortKey] = $value;
            }

            return $result;
        });
    }

    /**
     * Get all saved region block data for a theme.
     */
    public function getSavedRegions(string $slug): array
    {
        return cache()->remember("theme.{$slug}.regions", 3600, function () use ($slug) {
            $prefix = "theme.{$slug}.region.";
            $regions = Setting::where('key', 'like', $prefix.'%')
                ->pluck('value', 'key')
                ->toArray();

            $result = [];
            foreach ($regions as $key => $value) {
                $shortKey = str_replace($prefix, '', $key);
                $decoded = json_decode($value, true);
                $result[$shortKey] = is_array($decoded) ? $decoded : [];
            }

            return $result;
        });
    }

    /**
     * Get saved custom CSS for a theme.
     */
    public function getCustomCss(string $slug): string
    {
        return cache()->remember("theme.{$slug}.custom_css", 3600, function () use ($slug) {
            return Setting::where('key', "theme.{$slug}.custom_css")->value('value') ?? '';
        });
    }

    /**
     * Clear all cached theme data for a given slug.
     */
    public function clearCache(string $slug): void
    {
        cache()->forget("theme.{$slug}.settings");
        cache()->forget("theme.{$slug}.regions");
        cache()->forget("theme.{$slug}.custom_css");
    }

    /**
     * Save a theme setting.
     */
    public function saveSetting(string $slug, string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => "theme.{$slug}.{$key}"],
            ['value' => $value, 'type' => 'theme_setting']
        );

        $this->clearCache($slug);
    }

    /**
     * Save a theme region (block JSON).
     */
    public function saveRegion(string $slug, string $key, string $jsonValue): void
    {
        Setting::updateOrCreate(
            ['key' => "theme.{$slug}.region.{$key}"],
            ['value' => $jsonValue, 'type' => 'theme_region']
        );

        $this->clearCache($slug);
    }

    /**
     * Save custom CSS for a theme.
     */
    public function saveCustomCss(string $slug, string $css): void
    {
        $css = HtmlHelper::cleanCss($css);

        Setting::updateOrCreate(
            ['key' => "theme.{$slug}.custom_css"],
            ['value' => $css, 'type' => 'theme_css']
        );

        $this->clearCache($slug);
    }

    protected function discoverFromDirectory(string $dir): void
    {
        $manifest = $dir.'/theme.json';
        if (File::exists($manifest)) {
            $data = json_decode(File::get($manifest), true);
            if ($data && isset($data['slug'])) {
                $data['path'] = $dir;
                $this->themes[$data['slug']] = $data;
                // Translations for names and labels from the manifest
                // (lang/de.json …); language packs can supply them as well
                if (is_dir($dir.'/lang')) {
                    app('translator')->addJsonPath($dir.'/lang');
                }
            }
        }
    }

    protected function discoverComposerPackages(string $type, string $installBase): void
    {
        $installed = base_path('vendor/composer/installed.json');
        if (! File::exists($installed)) {
            return;
        }

        $data = json_decode(File::get($installed), true);
        $packages = $data['packages'] ?? $data;
        $composerDir = base_path('vendor/composer');

        foreach ($packages as $pkg) {
            if (($pkg['type'] ?? '') !== $type) {
                continue;
            }

            $slug = $pkg['extra']['inverys']['slug'] ?? basename($pkg['name'] ?? '');

            if (isset($this->themes[$slug])) {
                continue;
            }

            // 1. Check the local themes directory first (uploaded or manually placed)
            $localDir = $installBase.'/'.$slug;
            if (is_dir($localDir)) {
                $this->discoverFromDirectory($localDir);

                continue;
            }

            // 2. Resolve from Composer's install-path (vendor directory)
            $installPath = $pkg['install-path'] ?? null;
            if ($installPath) {
                $vendorDir = realpath($composerDir.'/'.$installPath);
                if ($vendorDir && is_dir($vendorDir)) {
                    $this->discoverFromDirectory($vendorDir);
                }
            }
        }
    }
}
