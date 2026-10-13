<?php

namespace Tests\Concerns;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Support\Facades\Route;

/**
 * Enable plugins for a test: create the record, load the provider
 * (registerOnly also registers the plugin's autoloader) and run install()
 * so that the plugin's tables exist.
 */
trait LoadsPlugins
{
    /** @param array<string, string> $plugins slug => name */
    protected function loadPlugins(array $plugins): void
    {
        foreach ($plugins as $slug => $name) {
            Plugin::firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'version' => '1.0.0',
                'is_enabled' => true,
                'is_system' => true,
            ]);
        }

        $manager = app(PluginManager::class);
        $manager->discover();

        foreach (array_keys($plugins) as $slug) {
            $manager->registerOnly($slug);
            $manager->getLoadedProviders()[$slug]->install();
        }

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
