<?php

namespace App\Services\Plugins;

use App\Contracts\CmsUserProvider;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\MediaManager;
use App\Contracts\PageManager;
use App\Contracts\PermissionManager;
use App\Contracts\RouteRegistrar;
use App\Contracts\SettingRepository;
use App\Models\BlockType;
use App\Models\Plugin;
use App\Services\CmsManager;
use App\Support\ThemeViews;
use Illuminate\Contracts\Foundation\Application;

abstract class PluginServiceProvider
{
    protected Application $app;

    protected Plugin $plugin;

    protected array $manifest;

    protected static array $registeredAutoloaders = [];

    public function __construct(Application $app, Plugin $plugin, array $manifest)
    {
        $this->app = $app;
        $this->plugin = $plugin;
        $this->manifest = $manifest;
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }

    public function install(): void
    {
        //
    }

    public function uninstall(): void
    {
        //
    }

    /**
     * Runs once when a new version of the plugin is loaded for the first
     * time (update via the update page, upload, git pull) — for schema
     * changes and data migrations. Write it idempotently
     * (Schema::hasColumn …). Permissions are synced again afterwards.
     * If it throws, the plugin is disabled and — after an update via the
     * update page — the previous state is restored.
     */
    public function upgrade(string $fromVersion, string $toVersion): void
    {
        //
    }

    /**
     * Sync permissions on every enable (not just first install).
     * Override in plugins that register permissions.
     */
    public function syncPermissions(): void
    {
        //
    }

    protected function getPluginPath(): string
    {
        return $this->manifest['path'] ?? '';
    }

    /**
     * Register a PSR-4 autoloader for the plugin namespace.
     */
    protected function registerAutoloader(string $namespace): void
    {
        $prefix = rtrim($namespace, '\\').'\\';

        // Skip if already registered (PluginManager may have bootstrapped one for this namespace)
        if (isset(static::$registeredAutoloaders[$prefix])) {
            return;
        }
        static::$registeredAutoloaders[$prefix] = true;

        $pluginPath = $this->getPluginPath();
        spl_autoload_register(function ($class) use ($prefix, $pluginPath) {
            if (str_starts_with($class, $prefix)) {
                $relative = str_replace($prefix, '', $class);
                $file = $pluginPath.'/src/'.str_replace('\\', '/', $relative).'.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        });
    }

    /**
     * Register plugin views. Every theme (the default theme included, and a
     * visitor's own theme) can override them in
     * themes/{theme}/views/plugins/{namespace}/ — see App\Support\ThemeViews.
     */
    protected function registerPluginViews(string $namespace, string $viewsPath): void
    {
        app(ThemeViews::class)->register($namespace, $viewsPath);
    }

    /**
     * Replace some views of another namespace with this plugin's views (the
     * captcha field in form-builder forms). A theme override still wins.
     */
    protected function extendPluginViews(string $namespace, string $viewsPath): void
    {
        app(ThemeViews::class)->prepend($namespace, $viewsPath);
    }

    /**
     * Seed default settings if they don't already exist.
     */
    protected function seedSettings(array $defaults): void
    {
        foreach ($defaults as $key => $data) {
            if (! $this->settings()->has($key)) {
                $this->settings()->set($key, $data['value'] ?? 'on', $data['type'] ?? 'string', $data['description'] ?? '');
            }
        }
    }

    protected function getSetting(string $key, $default = null)
    {
        $settings = $this->plugin->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Register a page-builder block type for this plugin (idempotent).
     * Lets plugins expose blocks without importing the core BlockType model.
     *
     * @param  array<string, mixed>  $attributes  name, category, icon, … (slug required)
     */
    protected function registerBlockType(array $attributes): void
    {
        BlockType::firstOrCreate(
            ['slug' => $attributes['slug']],
            array_merge(['is_core' => false, 'plugin_slug' => $this->plugin->slug], $attributes)
        );
    }

    /**
     * Remove a page-builder block type previously registered by this plugin.
     */
    protected function unregisterBlockType(string $slug): void
    {
        BlockType::where('slug', $slug)->delete();
    }

    // ─── Contract accessors for plugins ─────────────────────

    protected function cms(): CmsManager
    {
        return app(CmsManager::class);
    }

    protected function settings(): SettingRepository
    {
        return app(SettingRepository::class);
    }

    protected function permissions(): PermissionManager
    {
        return app(PermissionManager::class);
    }

    protected function pages(): PageManager
    {
        return app(PageManager::class);
    }

    protected function media(): MediaManager
    {
        return app(MediaManager::class);
    }

    protected function users(): CmsUserProvider
    {
        return app(CmsUserProvider::class);
    }

    protected function routes(): RouteRegistrar
    {
        return app(RouteRegistrar::class);
    }

    protected function extensions(): ExtensionRegistrar
    {
        return app(ExtensionRegistrar::class);
    }
}
