<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Services\PluginManager;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolePermissionSeeder::class);

        // Seed the settings table
        $this->call(SettingsTableSeeder::class);

        // Create default admin (needed before media/pages seeders)
        $this->call(DefaultAdminUserSeeder::class);

        // Create demo users for development environment
        if (app()->environment() !== 'production') {
            $this->call(DemoUserSeeder::class);
        }

        // Seed page categories and demo pages (PageSeeder generates its own media)
        $this->call(PageCategorySeeder::class);
        $this->call(PageSeeder::class);

        // Discover and enable plugins based on plugin.json manifests
        $pluginManager = app(PluginManager::class);
        $pluginManager->discover();

        foreach ($pluginManager->all() as $slug => $manifest) {
            if ($manifest['is_system'] ?? false) {
                $pluginManager->enable($slug);
            } else {
                $pluginManager->registerOnly($slug);
            }
        }

        // Mark as installed so the web installer is locked out
        if (! file_exists(storage_path('installed'))) {
            file_put_contents(storage_path('installed'), json_encode([
                'version' => config('cms.version', '1.0.0'),
                'installed_at' => now()->toIso8601String(),
            ]));
        }
    }
}
