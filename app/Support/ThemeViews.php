<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ThemeManager;
use Illuminate\Support\Facades\View;

/**
 * View namespaces that the theme in use may override: `theme::` and every
 * plugin namespace. A theme overrides a plugin view with
 * themes/{theme}/views/plugins/{namespace}/{view}.blade.php — in every
 * theme, the default theme included.
 *
 * The theme in use is the globally active one; on the website a visitor's
 * own theme (profile) replaces it for the request (ApplyVisitorTheme).
 * Plugins register through PluginServiceProvider::registerPluginViews().
 */
final class ThemeViews
{
    /** @var array<string, array{prepended: list<string>, base: list<string>}> */
    private array $namespaces = [];

    private ?string $theme = null;

    /** Views of a plugin namespace; the theme's override folder comes first. */
    public function register(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] ??= ['prepended' => [], 'base' => []];
        if (! in_array($path, $this->namespaces[$namespace]['base'], true)) {
            $this->namespaces[$namespace]['base'][] = $path;
        }

        $this->apply($namespace);
    }

    /**
     * Views of another plugin that replace some of a namespace's views (the
     * captcha field in form-builder forms). They rank below the theme override.
     */
    public function prepend(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] ??= ['prepended' => [], 'base' => []];
        if (! in_array($path, $this->namespaces[$namespace]['prepended'], true)) {
            array_unshift($this->namespaces[$namespace]['prepended'], $path);
        }

        $this->apply($namespace);
    }

    /** Switch every namespace to a theme (on boot and per request). */
    public function useTheme(string $theme): void
    {
        if ($theme === $this->theme) {
            return;
        }

        $this->theme = $theme;
        $this->applyThemeNamespace();
        foreach (array_keys($this->namespaces) as $namespace) {
            $this->apply($namespace);
        }

        // Views resolved for the previous theme must not be reused
        View::getFinder()->flush();
    }

    public function theme(): string
    {
        return $this->theme ?? app(ThemeManager::class)->getActive() ?? 'default';
    }

    /** @return list<string> */
    public function paths(string $namespace): array
    {
        $entry = $this->namespaces[$namespace] ?? ['prepended' => [], 'base' => []];
        $override = $this->themePath($this->theme()).'/views/plugins/'.$namespace;

        return array_values(array_merge(is_dir($override) ? [$override] : [], $entry['prepended'], $entry['base']));
    }

    private function apply(string $namespace): void
    {
        View::replaceNamespace($namespace, $this->paths($namespace));
    }

    /** theme:: — the theme in use, then the globally active one for views it lacks */
    private function applyThemeNamespace(): void
    {
        $global = app(ThemeManager::class)->getActive() ?? 'default';
        $paths = [];
        foreach (array_unique([$this->theme(), $global]) as $slug) {
            $views = $this->themePath($slug).'/views';
            if (is_dir($views)) {
                $paths[] = $views;
            }
        }

        View::replaceNamespace('theme', $paths);
    }

    private function themePath(string $slug): string
    {
        return app(ThemeManager::class)->getThemePath($slug);
    }
}
