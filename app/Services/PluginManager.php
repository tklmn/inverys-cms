<?php

namespace App\Services;

use App\Models\Plugin;
use App\Services\Plugins\PluginServiceProvider;
use App\Services\Updates\PackageStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PluginManager
{
    protected array $plugins = [];

    protected array $loadedProviders = [];

    protected array $registeredAutoloaders = [];

    public function boot(): void
    {
        $this->discover();

        $enabled = Plugin::where('is_enabled', true)->orderBy('order')->get();

        foreach ($enabled as $plugin) {
            $this->loadPlugin($plugin);
            $this->upgradeIfChanged($plugin);
        }
    }

    /**
     * If a different version is on disk than was last loaded (update,
     * upload, git pull), the plugin's upgrade() runs once and the version
     * is recorded. If that fails, the plugin is disabled and the error
     * recorded — the update page then rolls back to the previous state
     * (PackageStore::settle).
     */
    protected function upgradeIfChanged(Plugin $plugin): void
    {
        $provider = $this->loadedProviders[$plugin->slug] ?? null;
        $version = (string) ($this->plugins[$plugin->slug]['version'] ?? '');

        if ($provider === null || $version === '' || $version === (string) $plugin->version) {
            return;
        }

        // Only one request runs the upgrade; the others load the plugin normally
        $lock = Cache::lock('cms.plugin-upgrade.'.$plugin->slug, 120);
        if (! $lock->get()) {
            return;
        }

        try {
            $provider->upgrade((string) $plugin->version, $version);
            $provider->syncPermissions();
            $plugin->update(['version' => $version]);
        } catch (\Throwable $e) {
            Log::error("Plugin '{$plugin->slug}' upgrade to {$version} failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            $plugin->update(['is_enabled' => false, 'enabled_at' => null]);
            unset($this->loadedProviders[$plugin->slug]);
            Cache::put(PackageStore::ERROR_PREFIX.$plugin->slug, $e->getMessage(), now()->addDay());
        } finally {
            $lock->release();
        }
    }

    public function discover(): void
    {
        $this->plugins = [];

        // 1. Directory-based plugins
        $pluginsPath = config('cms.plugins_path');
        if (is_dir($pluginsPath)) {
            foreach (File::directories($pluginsPath) as $dir) {
                $this->discoverFromDirectory($dir);
            }
        }

        // 2. Composer-installed plugins (type: inverys-plugin)
        $this->discoverComposerPackages('inverys-plugin', $pluginsPath);
    }

    public function all(): array
    {
        return $this->plugins;
    }

    public function has(string $slug): bool
    {
        return isset($this->plugins[$slug]);
    }

    public function get(string $slug): ?array
    {
        return $this->plugins[$slug] ?? null;
    }

    public function enable(string $slug): bool
    {
        if (! $this->has($slug)) {
            return false;
        }

        $manifest = $this->plugins[$slug];
        $existing = Plugin::where('slug', $slug)->first();
        $isFirstInstall = ! $existing || ! $existing->installed_at;

        // No DB::transaction() — plugin install() may run DDL (CREATE TABLE)
        // which implicitly commits in MySQL, breaking the outer transaction.
        // All operations here are idempotent, so atomicity is not required.

        $plugin = Plugin::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest['name'] ?? $slug,
                // Existing installation: keep the old version, upgrade() below
                'version' => $existing?->version ?? ($manifest['version'] ?? '1.0.0'),
                'description' => $manifest['description'] ?? '',
                'author' => $manifest['author'] ?? '',
                'is_enabled' => true,
                'enabled_at' => now(),
                'installed_at' => $existing?->installed_at ?? now(),
                'is_system' => $manifest['is_system'] ?? false,
            ]
        );

        $this->loadPlugin($plugin);

        // If loadPlugin failed and auto-disabled the plugin, report failure
        if (! $plugin->is_enabled) {
            return false;
        }

        if (isset($this->loadedProviders[$slug])) {
            try {
                if ($isFirstInstall) {
                    $this->loadedProviders[$slug]->install();
                }

                // Always sync permissions (idempotent) so new permissions
                // added after initial install are assigned to roles
                $this->loadedProviders[$slug]->syncPermissions();

                // Newer version than at the last enable: catch up on upgrade()
                if (! $isFirstInstall && (string) $plugin->version !== (string) ($manifest['version'] ?? $plugin->version)) {
                    $this->loadedProviders[$slug]->upgrade((string) $plugin->version, (string) $manifest['version']);
                }
                $plugin->update(['version' => $manifest['version'] ?? $plugin->version]);
            } catch (\Throwable $e) {
                Log::error("Plugin '{$slug}' install/sync failed: {$e->getMessage()}", [
                    'exception' => $e,
                ]);

                $plugin->update(['is_enabled' => false, 'enabled_at' => null]);

                session()->flash('error', __('translation.plugins.auto_disabled', [
                    'slug' => $slug,
                    'error' => $e->getMessage(),
                ]));

                return false;
            }
        }

        return true;
    }

    /**
     * Register a plugin (create DB record + run install()) but keep it disabled.
     */
    public function registerOnly(string $slug): bool
    {
        if (! $this->has($slug)) {
            return false;
        }

        $manifest = $this->plugins[$slug];
        $existing = Plugin::where('slug', $slug)->first();

        if ($existing && $existing->installed_at) {
            return true; // Already installed
        }

        $plugin = Plugin::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest['name'] ?? $slug,
                'version' => $manifest['version'] ?? '1.0.0',
                'description' => $manifest['description'] ?? '',
                'author' => $manifest['author'] ?? '',
                'is_enabled' => false,
                'enabled_at' => null,
                'installed_at' => now(),
            ]
        );

        // Temporarily load the provider to run install(), then unload
        $this->loadPlugin($plugin);

        if (isset($this->loadedProviders[$slug])) {
            try {
                $this->loadedProviders[$slug]->install();
            } catch (\Throwable $e) {
                Log::error("Plugin '{$slug}' install() failed: {$e->getMessage()}", [
                    'exception' => $e,
                ]);

                return false;
            }
        }

        // Ensure it stays disabled
        $plugin->update(['is_enabled' => false, 'enabled_at' => null]);

        return true;
    }

    public function disable(string $slug): bool
    {
        return DB::transaction(function () use ($slug) {
            $plugin = Plugin::where('slug', $slug)->first();
            if (! $plugin) {
                return false;
            }

            $plugin->update([
                'is_enabled' => false,
                'enabled_at' => null,
            ]);

            return true;
        });
    }

    protected function loadPlugin(Plugin $plugin): void
    {
        $manifest = $this->plugins[$plugin->slug] ?? null;
        if (! $manifest || ! isset($manifest['provider'])) {
            return;
        }

        $providerClass = $manifest['provider'];

        // For directory-based plugins without Composer autoload, register a PSR-4 autoloader
        if (! class_exists($providerClass)) {
            $pluginSrcPath = ($manifest['path'] ?? '').'/src';
            if (is_dir($pluginSrcPath)) {
                // Derive namespace from provider class (e.g. "Plugins\AnnouncementBar\" from "Plugins\AnnouncementBar\ServiceProvider")
                $namespace = substr($providerClass, 0, (int) strrpos($providerClass, '\\') + 1);
                if (! isset($this->registeredAutoloaders[$namespace])) {
                    $this->registeredAutoloaders[$namespace] = true;
                    spl_autoload_register(function (string $class) use ($namespace, $pluginSrcPath) {
                        if (str_starts_with($class, $namespace)) {
                            $relative = str_replace('\\', '/', substr($class, strlen($namespace)));
                            $file = $pluginSrcPath.'/'.$relative.'.php';
                            if (file_exists($file)) {
                                require_once $file;
                            }
                        }
                    });
                }
            }
        }

        if (class_exists($providerClass) && is_subclass_of($providerClass, PluginServiceProvider::class)) {
            try {
                $provider = new $providerClass(app(), $plugin, $manifest);
                $provider->register();
                $provider->boot();
                $this->loadedProviders[$plugin->slug] = $provider;
            } catch (\Throwable $e) {
                Log::error("Plugin '{$plugin->slug}' failed to load: {$e->getMessage()}", [
                    'exception' => $e,
                ]);

                // Auto-disable the broken plugin so the app remains usable
                $plugin->update([
                    'is_enabled' => false,
                    'enabled_at' => null,
                ]);
                // For the update page: a freshly updated plugin gets rolled back
                Cache::put(PackageStore::ERROR_PREFIX.$plugin->slug, $e->getMessage(), now()->addDay());

                session()->flash('error', __('translation.plugins.auto_disabled', [
                    'slug' => $plugin->slug,
                    'error' => $e->getMessage(),
                ]));
            }
        }
    }

    public function getLoadedProviders(): array
    {
        return $this->loadedProviders;
    }

    protected function discoverFromDirectory(string $dir): void
    {
        $manifest = $dir.'/plugin.json';
        if (File::exists($manifest)) {
            $data = json_decode(File::get($manifest), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("Invalid plugin.json in {$dir}: ".json_last_error_msg());

                return;
            }
            if ($data && isset($data['slug'])) {
                $data['path'] = $dir;
                $this->plugins[$data['slug']] = $data;
                // Translations for names and labels from the manifest
                // (lang/de.json …); language packs can provide them as well
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

            if (isset($this->plugins[$slug])) {
                continue;
            }

            // 1. Check the local plugins directory first (uploaded or manually placed)
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
