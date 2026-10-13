<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeManagerTest extends TestCase
{
    use RefreshDatabase;

    private ThemeManager $manager;

    private string $themes;

    protected function setUp(): void
    {
        parent::setUp();

        // The real default theme plus a second theme, so switching can be tested
        $this->themes = sys_get_temp_dir().'/theme-manager-'.uniqid();
        File::copyDirectory(base_path('themes/default'), $this->themes.'/default');
        File::ensureDirectoryExists($this->themes.'/second');
        File::put($this->themes.'/second/theme.json', json_encode(['slug' => 'second', 'name' => 'Second Theme', 'version' => '1.0.0']));
        config(['cms.themes_path' => $this->themes]);

        $this->manager = new ThemeManager;
        $this->manager->discover();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themes);
        parent::tearDown();
    }

    public function test_discover_finds_themes_in_themes_directory(): void
    {
        $themes = $this->manager->all();

        $this->assertArrayHasKey('default', $themes);
        $this->assertArrayHasKey('second', $themes);
    }

    public function test_each_theme_has_required_keys(): void
    {
        foreach ($this->manager->all() as $slug => $theme) {
            $this->assertArrayHasKey('name', $theme, "Theme '{$slug}' is missing 'name'.");
            $this->assertArrayHasKey('slug', $theme, "Theme '{$slug}' is missing 'slug'.");
            $this->assertArrayHasKey('version', $theme, "Theme '{$slug}' is missing 'version'.");
        }
    }

    public function test_has_returns_true_for_existing_theme(): void
    {
        $this->assertTrue($this->manager->has('default'));
        $this->assertTrue($this->manager->has('second'));
    }

    public function test_has_returns_false_for_unknown_theme(): void
    {
        $this->assertFalse($this->manager->has('nonexistent-theme'));
    }

    public function test_get_returns_theme_data(): void
    {
        $default = $this->manager->get('default');

        $this->assertNotNull($default);
        $this->assertSame('Default Theme', $default['name']);
        $this->assertSame('default', $default['slug']);
    }

    public function test_get_returns_null_for_unknown_theme(): void
    {
        $this->assertNull($this->manager->get('nonexistent-theme'));
    }

    public function test_get_theme_path_returns_valid_directory(): void
    {
        $path = $this->manager->getThemePath('default');

        $this->assertDirectoryExists($path);
    }

    public function test_get_theme_path_for_second_theme(): void
    {
        $path = $this->manager->getThemePath('second');

        $this->assertDirectoryExists($path);
        $this->assertStringContainsString('second', $path);
    }

    public function test_activate_changes_active_theme(): void
    {
        $result = $this->manager->activate('second');

        $this->assertTrue($result);
        $this->assertSame('second', $this->manager->getActive());
        $this->assertDatabaseHas('settings', [
            'key' => 'active_theme',
            'value' => 'second',
        ]);
    }

    public function test_activate_returns_false_for_unknown_theme(): void
    {
        $result = $this->manager->activate('nonexistent-theme');

        $this->assertFalse($result);
    }

    public function test_boot_sets_active_theme_from_database(): void
    {
        Setting::updateOrCreate(
            ['key' => 'active_theme'],
            ['value' => 'second', 'type' => 'string']
        );

        $manager = new ThemeManager;
        $manager->boot();

        $this->assertSame('second', $manager->getActive());
    }

    public function test_boot_defaults_to_default_theme(): void
    {
        $manager = new ThemeManager;
        $manager->boot();

        $this->assertSame('default', $manager->getActive());
    }

    public function test_get_settings_schema_returns_array(): void
    {
        $schema = $this->manager->getSettingsSchema('default');

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('primary_color', $schema);
        $this->assertArrayHasKey('footer_text', $schema);
    }

    public function test_get_regions_schema_returns_array(): void
    {
        $regions = $this->manager->getRegionsSchema('default');

        $this->assertIsArray($regions);
        $this->assertArrayHasKey('footer_content', $regions);
    }

    public function test_get_asset_url_builds_correct_path(): void
    {
        $url = $this->manager->getAssetUrl('default', 'css/style.css');

        // Served via ExtensionAssetController, from the theme's assets/
        $this->assertStringContainsString('ext-assets/theme/default/css/style.css', $url);
    }

    public function test_save_and_get_saved_settings(): void
    {
        $this->manager->saveSetting('default', 'primary_color', '#ff0000');

        $saved = $this->manager->getSavedSettings('default');

        $this->assertSame('#ff0000', $saved['primary_color']);
    }

    public function test_clear_cache_does_not_throw(): void
    {
        $this->manager->clearCache('default');

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }
}
