<?php

namespace Tests\Feature\Plugins\CookieConsent;

use App\Models\Plugin;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->enableCookieConsentPlugin();
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    protected function enableCookieConsentPlugin(): void
    {
        Plugin::create([
            'slug' => 'cookie-consent',
            'name' => 'Cookie Consent',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        $pm = app(PluginManager::class);
        $pm->boot();

        // Refresh the route name lookup so route() helper resolves dynamically added routes
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.cookie-consent.settings'))
            ->assertOk();
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.settings.save'), [
                'enabled' => '1',
                'banner_text' => 'We use cookies.',
                'privacy_page_slug' => 'privacy',
                'imprint_page_slug' => 'imprint',
                'position' => 'modal',
                'bg_color' => '#222222',
                'text_color' => '#eeeeee',
                'btn_bg_color' => '#ff5500',
                'btn_text_color' => '#ffffff',
                'cookie_lifetime' => '180',
                'show_reopen_btn' => '1',
                'reopen_position' => 'right',
            ])
            ->assertRedirect(route('backend.cookie-consent.settings'));

        $this->assertDatabaseHas('settings', ['key' => 'cc.position', 'value' => 'modal']);
        $this->assertDatabaseHas('settings', ['key' => 'cc.bg_color', 'value' => '#222222']);
        $this->assertDatabaseHas('settings', ['key' => 'cc.text_color', 'value' => '#eeeeee']);
        $this->assertDatabaseHas('settings', ['key' => 'cc.btn_bg_color', 'value' => '#ff5500']);
        $this->assertDatabaseHas('settings', ['key' => 'cc.cookie_lifetime', 'value' => '180']);
        $this->assertDatabaseHas('settings', ['key' => 'cc.reopen_position', 'value' => 'right']);
    }

    public function test_user_without_access_dashboard_gets_403(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.cookie-consent.settings'))
            ->assertForbidden();
    }

    public function test_settings_are_persisted_with_cc_prefix(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.settings.save'), [
                'position' => 'corner',
                'bg_color' => '#1e293b',
                'text_color' => '#f1f5f9',
                'btn_bg_color' => '#4f46e5',
                'btn_text_color' => '#ffffff',
                'cookie_lifetime' => '365',
                'reopen_position' => 'left',
            ])
            ->assertRedirect();

        // All cookie consent settings must use the cc. prefix
        $ccSettings = Setting::where('key', 'like', 'cc.%')->pluck('value', 'key')->toArray();

        $this->assertArrayHasKey('cc.position', $ccSettings);
        $this->assertArrayHasKey('cc.bg_color', $ccSettings);
        $this->assertArrayHasKey('cc.text_color', $ccSettings);
        $this->assertArrayHasKey('cc.btn_bg_color', $ccSettings);
        $this->assertArrayHasKey('cc.btn_text_color', $ccSettings);
        $this->assertArrayHasKey('cc.cookie_lifetime', $ccSettings);
        $this->assertArrayHasKey('cc.enabled', $ccSettings);
        $this->assertArrayHasKey('cc.reopen_position', $ccSettings);
    }

    public function test_update_validates_position(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.settings.save'), [
                'position' => 'invalid-position',
                'bg_color' => '#1e293b',
                'text_color' => '#f1f5f9',
                'btn_bg_color' => '#4f46e5',
                'btn_text_color' => '#ffffff',
                'cookie_lifetime' => '365',
                'reopen_position' => 'left',
            ])
            ->assertSessionHasErrors('position');
    }

    public function test_update_validates_cookie_lifetime(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.settings.save'), [
                'position' => 'bottom',
                'bg_color' => '#1e293b',
                'text_color' => '#f1f5f9',
                'btn_bg_color' => '#4f46e5',
                'btn_text_color' => '#ffffff',
                'cookie_lifetime' => '0',
                'reopen_position' => 'left',
            ])
            ->assertSessionHasErrors('cookie_lifetime');
    }

    public function test_enabled_checkbox_stores_zero_when_unchecked(): void
    {
        $admin = $this->createAdmin();

        // Submit without 'enabled' field (unchecked checkbox)
        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.settings.save'), [
                'position' => 'bottom',
                'bg_color' => '#1e293b',
                'text_color' => '#f1f5f9',
                'btn_bg_color' => '#4f46e5',
                'btn_text_color' => '#ffffff',
                'cookie_lifetime' => '365',
                'reopen_position' => 'left',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'cc.enabled', 'value' => '0']);
    }
}
