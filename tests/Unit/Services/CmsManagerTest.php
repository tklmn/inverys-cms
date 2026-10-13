<?php

namespace Tests\Unit\Services;

use App\Contracts\MediaManager;
use App\Contracts\PageManager;
use App\Contracts\PermissionManager;
use App\Contracts\SettingRepository;
use App\Services\CmsManager;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_resolved_from_container(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(CmsManager::class, $manager);
    }

    public function test_cms_helper_returns_cms_manager_instance(): void
    {
        $manager = cms();

        $this->assertInstanceOf(CmsManager::class, $manager);
    }

    public function test_settings_returns_setting_repository(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(SettingRepository::class, $manager->settings());
    }

    public function test_permissions_returns_permission_manager(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(PermissionManager::class, $manager->permissions());
    }

    public function test_pages_returns_page_manager(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(PageManager::class, $manager->pages());
    }

    public function test_media_returns_media_manager(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(MediaManager::class, $manager->media());
    }

    public function test_themes_returns_theme_manager(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(ThemeManager::class, $manager->themes());
    }

    public function test_plugins_returns_plugin_manager(): void
    {
        $manager = app(CmsManager::class);

        $this->assertInstanceOf(PluginManager::class, $manager->plugins());
    }

    public function test_version_returns_string(): void
    {
        $manager = app(CmsManager::class);

        $version = $manager->version();

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function test_setting_repository_can_set_and_get_values(): void
    {
        $settings = cms()->settings();

        $settings->set('test_key', 'test_value');

        $this->assertSame('test_value', $settings->get('test_key'));
    }

    public function test_setting_repository_get_returns_default_for_missing_key(): void
    {
        $settings = cms()->settings();

        $this->assertSame('fallback', $settings->get('missing_key', 'fallback'));
    }

    public function test_permission_manager_can_create_permission(): void
    {
        $this->seedPermissions();

        $permissions = cms()->permissions();
        $perm = $permissions->createPermission('test-permission');

        $this->assertNotNull($perm);
    }
}
