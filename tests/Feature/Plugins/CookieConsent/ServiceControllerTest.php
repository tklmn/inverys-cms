<?php

namespace Tests\Feature\Plugins\CookieConsent;

use App\Models\Plugin;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
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

    /**
     * Seed the cc.services setting with a test service and return the service data.
     */
    protected function createTestService(array $overrides = []): array
    {
        $service = array_merge([
            'id' => 'google-analytics',
            'name' => 'Google Analytics',
            'category' => 'statistics',
            'description' => 'Tracks website usage.',
            'script' => '<script>/* GA */</script>',
            'position' => 'head',
        ], $overrides);

        EloquentSettingRepository::clearCache();
        Setting::updateOrCreate(
            ['key' => 'cc.services'],
            ['value' => json_encode([$service]), 'type' => 'plugin_setting'],
        );
        EloquentSettingRepository::clearCache();

        return $service;
    }

    public function test_admin_can_view_services_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.cookie-consent.services'))
            ->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.cookie-consent.services.create'))
            ->assertOk();
    }

    public function test_admin_can_store_a_new_service(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.services.store'), [
                'name' => 'Google Analytics',
                'category' => 'statistics',
                'description' => 'Tracks website usage.',
                'script' => '<script>/* GA tracking */</script>',
                'position' => 'head',
            ])
            ->assertRedirect(route('backend.cookie-consent.services'));

        EloquentSettingRepository::clearCache();
        $raw = Setting::where('key', 'cc.services')->value('value');
        $services = json_decode($raw, true);

        $this->assertCount(1, $services);
        $this->assertEquals('Google Analytics', $services[0]['name']);
        $this->assertEquals('google-analytics', $services[0]['id']);
        $this->assertEquals('statistics', $services[0]['category']);
        $this->assertEquals('head', $services[0]['position']);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = $this->createAdmin();
        $service = $this->createTestService();

        $this->actingAs($admin)
            ->get(route('backend.cookie-consent.services.edit', $service['id']))
            ->assertOk();
    }

    public function test_admin_can_update_a_service(): void
    {
        $admin = $this->createAdmin();
        $service = $this->createTestService();

        $this->actingAs($admin)
            ->put(route('backend.cookie-consent.services.update', $service['id']), [
                'name' => 'Updated Analytics',
                'category' => 'marketing',
                'description' => 'Updated description.',
                'script' => '<script>/* updated */</script>',
                'position' => 'body_end',
            ])
            ->assertRedirect(route('backend.cookie-consent.services'));

        EloquentSettingRepository::clearCache();
        $raw = Setting::where('key', 'cc.services')->value('value');
        $services = json_decode($raw, true);

        $this->assertCount(1, $services);
        $this->assertEquals('Updated Analytics', $services[0]['name']);
        $this->assertEquals('marketing', $services[0]['category']);
        $this->assertEquals('body_end', $services[0]['position']);
        // ID should remain unchanged
        $this->assertEquals('google-analytics', $services[0]['id']);
    }

    public function test_admin_can_delete_a_service(): void
    {
        $admin = $this->createAdmin();
        $this->createTestService();

        $this->actingAs($admin)
            ->delete(route('backend.cookie-consent.services.destroy', 'google-analytics'))
            ->assertRedirect(route('backend.cookie-consent.services'));

        EloquentSettingRepository::clearCache();
        $raw = Setting::where('key', 'cc.services')->value('value');
        $services = json_decode($raw, true);

        $this->assertCount(0, $services);
    }

    public function test_store_requires_name_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.services.store'), [
                'category' => 'statistics',
                'script' => '<script>/* code */</script>',
                'position' => 'head',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_category_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.services.store'), [
                'name' => 'Some Service',
                'category' => 'invalid-category',
                'script' => '<script>/* code */</script>',
                'position' => 'head',
            ])
            ->assertSessionHasErrors('category');
    }

    public function test_store_requires_script_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.services.store'), [
                'name' => 'Some Service',
                'category' => 'statistics',
                'position' => 'head',
            ])
            ->assertSessionHasErrors('script');
    }

    public function test_store_generates_unique_slug_for_duplicate_names(): void
    {
        $admin = $this->createAdmin();
        $this->createTestService(['id' => 'google-analytics', 'name' => 'Google Analytics']);

        EloquentSettingRepository::clearCache();

        $this->actingAs($admin)
            ->post(route('backend.cookie-consent.services.store'), [
                'name' => 'Google Analytics',
                'category' => 'statistics',
                'description' => 'Another GA instance.',
                'script' => '<script>/* GA2 */</script>',
                'position' => 'head',
            ])
            ->assertRedirect(route('backend.cookie-consent.services'));

        EloquentSettingRepository::clearCache();
        $raw = Setting::where('key', 'cc.services')->value('value');
        $services = json_decode($raw, true);

        $this->assertCount(2, $services);
        $this->assertEquals('google-analytics', $services[0]['id']);
        $this->assertEquals('google-analytics-1', $services[1]['id']);
    }

    public function test_user_without_access_dashboard_gets_403(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.cookie-consent.services'))
            ->assertForbidden();
    }
}
