<?php

namespace Tests\Feature\Plugins\ActivityLog;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\ActivityLog\ActivityLogServiceProvider;
use Plugins\ActivityLog\Models\ActivityLog;
use Tests\TestCase;

class AdminActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $plugin = Plugin::create([
            'slug' => 'activity-log',
            'name' => 'Activity Log',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        $manifest = json_decode(file_get_contents(base_path('plugins/activity-log/plugin.json')), true);
        $manifest['path'] = base_path('plugins/activity-log');

        $provider = new ActivityLogServiceProvider($this->app, $plugin, $manifest);
        $provider->register();
        $provider->install();
        $provider->boot();

        $this->app->make('router')->getRoutes()->refreshNameLookups();
    }

    public function test_admin_can_view_activity_log_index(): void
    {
        $admin = $this->createAdmin();

        ActivityLog::create(['action' => 'login', 'user_name' => 'Test']);

        $this->actingAs($admin)
            ->get(route('backend.activity-log.index'))
            ->assertOk();
    }

    public function test_admin_can_view_a_single_log_entry(): void
    {
        $admin = $this->createAdmin();

        $log = ActivityLog::create([
            'action' => 'created',
            'subject_type' => 'App\\Models\\Page',
            'subject_id' => 1,
            'subject_label' => 'Test Page',
        ]);

        $this->actingAs($admin)
            ->get(route('backend.activity-log.show', $log->id))
            ->assertOk();
    }

    public function test_admin_can_delete_a_log_entry(): void
    {
        $admin = $this->createAdmin();

        $log = ActivityLog::create(['action' => 'created', 'subject_label' => 'Deletable']);

        $this->actingAs($admin)
            ->delete(route('backend.activity-log.destroy', $log->id))
            ->assertRedirect(route('backend.activity-log.index'));

        $this->assertDatabaseMissing('activity_logs', ['id' => $log->id]);
    }

    public function test_admin_can_clear_all_log_entries(): void
    {
        $admin = $this->createAdmin();

        ActivityLog::create(['action' => 'login']);
        ActivityLog::create(['action' => 'logout']);
        ActivityLog::create(['action' => 'created']);

        $this->assertGreaterThan(0, ActivityLog::count());

        $this->actingAs($admin)
            ->post(route('backend.activity-log.clear'))
            ->assertRedirect(route('backend.activity-log.index'));

        // Clearing wipes the trail but records a single "cleared" audit entry.
        $this->assertEquals(1, ActivityLog::count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'cleared']);
    }

    public function test_editor_without_permission_gets_403(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.activity-log.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.activity-log.settings.edit'))
            ->assertOk();
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->put(route('backend.activity-log.settings.update'), [
                'retention_days' => 30,
                'max_entries' => 5000,
            ])
            ->assertRedirect(route('backend.activity-log.settings.edit'));

        $this->assertDatabaseHas('settings', [
            'key' => 'activity_log.retention_days',
            'value' => '30',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'activity_log.max_entries',
            'value' => '5000',
        ]);
    }
}
