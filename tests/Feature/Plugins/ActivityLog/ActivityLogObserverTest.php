<?php

namespace Tests\Feature\Plugins\ActivityLog;

use App\Models\Page;
use App\Models\Plugin;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\ActivityLog\ActivityLogServiceProvider;
use Plugins\ActivityLog\Models\ActivityLog;
use Tests\TestCase;

class ActivityLogObserverTest extends TestCase
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

    public function test_creating_a_model_creates_log_entry_with_action_created(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // Clear any log entries from admin/role creation
        ActivityLog::query()->delete();

        Page::create(['title' => 'New Page', 'slug' => 'new-page']);

        $log = ActivityLog::where('action', 'created')
            ->where('subject_type', Page::class)
            ->first();

        $this->assertNotNull($log, 'Expected a "created" log entry for Page');
        $this->assertEquals('created', $log->action);
        $this->assertEquals($admin->id, $log->user_id);
    }

    public function test_updating_a_model_creates_log_entry_with_old_and_new_properties(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $page = Page::create(['title' => 'Original Title', 'slug' => 'original']);

        // Clear previous entries
        ActivityLog::query()->delete();

        $page->update(['title' => 'Updated Title']);

        $log = ActivityLog::where('action', 'updated')
            ->where('subject_type', Page::class)
            ->first();

        $this->assertNotNull($log, 'Expected an "updated" log entry for Page');
        $this->assertIsArray($log->properties);
        $this->assertEquals('Original Title', $log->properties['old']['title']);
        $this->assertEquals('Updated Title', $log->properties['new']['title']);
    }

    public function test_deleting_a_model_creates_log_entry_with_action_deleted(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $setting = Setting::create(['key' => 'test.setting', 'value' => 'test']);

        // Clear previous entries
        ActivityLog::query()->delete();

        $setting->delete();

        $log = ActivityLog::where('action', 'deleted')
            ->where('subject_type', Setting::class)
            ->first();

        $this->assertNotNull($log, 'Expected a "deleted" log entry for Setting');
        $this->assertEquals('deleted', $log->action);
    }

    public function test_updating_only_ignored_fields_does_not_create_log_entry(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page']);

        // Clear previous entries
        ActivityLog::query()->delete();

        // Touch only updated_at (an ignored field)
        $page->forceFill(['updated_at' => now()->addHour()])->save();

        $log = ActivityLog::where('action', 'updated')
            ->where('subject_type', Page::class)
            ->where('subject_id', $page->id)
            ->first();

        $this->assertNull($log, 'No log entry should be created for ignored-field-only changes');
    }

    public function test_password_changes_are_not_logged_in_properties(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => 'Jane Doe']);

        // Clear previous entries
        ActivityLog::query()->delete();

        $user->update([
            'name' => 'Jane Updated',
            'password' => bcrypt('newpassword123'),
        ]);

        $log = ActivityLog::where('action', 'updated')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->first();

        $this->assertNotNull($log, 'Expected an "updated" log entry for User');

        // Password should not appear in the logged properties
        $this->assertArrayNotHasKey('password', $log->properties['old'] ?? []);
        $this->assertArrayNotHasKey('password', $log->properties['new'] ?? []);

        // But the name change should be recorded
        $this->assertArrayHasKey('name', $log->properties['new']);
    }
}
