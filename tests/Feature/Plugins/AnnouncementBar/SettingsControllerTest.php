<?php

namespace Tests\Feature\Plugins\AnnouncementBar;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\AnnouncementBar\AnnouncementBarServiceProvider;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A marketplace plugin, not part of the CMS: runs where it is installed
        // (cloned into plugins/, or as Composer package)
        $path = collect([base_path('plugins/announcement-bar'), base_path('vendor/inverys/announcement-bar')])
            ->first(fn (string $dir) => file_exists($dir.'/plugin.json'));
        if ($path === null) {
            $this->markTestSkipped('The announcement bar plugin is not installed.');
        }
        // Without Composer, the PluginManager would map the namespace to src/
        spl_autoload_register(function (string $class) use ($path) {
            $prefix = 'Plugins\\AnnouncementBar\\';
            if (str_starts_with($class, $prefix) && is_file($file = $path.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        });

        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        $plugin = Plugin::create([
            'slug' => 'announcement-bar',
            'name' => 'Announcement Bar',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => false,
        ]);

        $manifest = json_decode(file_get_contents($path.'/plugin.json'), true);
        $manifest['path'] = $path;

        $provider = new AnnouncementBarServiceProvider($this->app, $plugin, $manifest);
        $provider->register();
        $provider->boot();

        $this->app->make('router')->getRoutes()->refreshNameLookups();
    }

    public function test_editor_without_manage_plugins_cannot_open_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.announcement-bar.settings'))
            ->assertForbidden();
    }

    public function test_editor_without_manage_plugins_cannot_save_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->post(route('backend.announcement-bar.settings.save'), [
                'message' => 'Hacked',
                'bg_color' => '#000000',
                'text_color' => '#ffffff',
                'position' => 'top',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_open_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.announcement-bar.settings'))
            ->assertOk();
    }

    public function test_non_hex_color_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.announcement-bar.settings.save'), [
                'message' => 'Hi',
                'bg_color' => 'red;}',
                'text_color' => '#ffffff',
                'position' => 'top',
            ])
            ->assertSessionHasErrors('bg_color');
    }
}
