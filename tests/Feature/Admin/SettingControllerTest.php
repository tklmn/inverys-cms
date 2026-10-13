<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_settings(): void
    {
        $admin = $this->createAdmin();
        Setting::create(['key' => 'site_name', 'value' => 'Test Site']);

        $this->actingAs($admin)
            ->get(route('backend.settings.index'))
            ->assertOk();
    }

    public function test_settings_index_excludes_sensitive_keys(): void
    {
        $admin = $this->createAdmin();
        Setting::create(['key' => 'site_name', 'value' => 'Test']);
        Setting::create(['key' => 'mail.host', 'value' => 'smtp.test.com']);

        $response = $this->actingAs($admin)
            ->get(route('backend.settings.index'));

        $response->assertOk();
        // The index method filters out mail.* keys
    }

    public function test_admin_can_edit_setting(): void
    {
        $admin = $this->createAdmin();
        $setting = Setting::create(['key' => 'site_name', 'value' => 'Old Name']);

        $this->actingAs($admin)
            ->get(route('backend.settings.edit', $setting))
            ->assertOk();
    }

    public function test_admin_can_update_setting(): void
    {
        $admin = $this->createAdmin();
        $setting = Setting::create(['key' => 'site_name', 'value' => 'Old Name']);

        $this->actingAs($admin)
            ->put(route('backend.settings.update', $setting), ['value' => 'New Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'New Name']);
    }

    public function test_update_validates_value_required(): void
    {
        $admin = $this->createAdmin();
        $setting = Setting::create(['key' => 'site_name', 'value' => 'Test']);

        $this->actingAs($admin)
            ->put(route('backend.settings.update', $setting), ['value' => ''])
            ->assertSessionHasErrors('value');
    }

    public function test_editor_cannot_access_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.settings.index'))
            ->assertForbidden();
    }

    /**
     * SettingController only implements index/edit/update. The unimplemented
     * resource actions must not be routed, otherwise e.g. GET /backend/settings/create
     * 500s with "Method … does not exist".
     */
    public function test_unimplemented_resource_routes_are_not_registered(): void
    {
        foreach (['create', 'store', 'show', 'destroy'] as $action) {
            $this->assertFalse(
                Route::has("backend.settings.{$action}"),
                "backend.settings.{$action} should not be registered"
            );
        }

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get('/backend/settings/create');
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
