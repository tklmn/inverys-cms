<?php

namespace Tests\Feature\Plugins\ActivityLog;

use App\Models\Page;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\ActivityLog\ActivityLogServiceProvider;
use Plugins\ActivityLog\Models\ActivityLog;
use Plugins\ActivityLog\Services\ActivityLogger;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
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

    public function test_logger_creates_activity_log_entry(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user);

        ActivityLogger::log('test_action');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'test_action',
        ]);
    }

    public function test_logger_records_user_id_and_user_name(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user);

        ActivityLogger::log('login');

        $log = ActivityLog::where('action', 'login')->first();

        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($user->name, $log->user_name);
    }

    public function test_logger_records_subject_type_and_subject_id(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user);

        $page = Page::create(['title' => 'Test Page', 'slug' => 'test-page']);

        // Clear any observer-created entries so we can test the logger directly
        ActivityLog::query()->delete();

        ActivityLogger::log('created', $page);

        $log = ActivityLog::first();

        $this->assertNotNull($log);
        $this->assertEquals(Page::class, $log->subject_type);
        $this->assertEquals($page->id, $log->subject_id);
    }

    public function test_logger_records_properties(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user);

        $properties = [
            'old' => ['title' => 'Old Title'],
            'new' => ['title' => 'New Title'],
        ];

        ActivityLogger::log('updated', null, $properties);

        $log = ActivityLog::where('action', 'updated')->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->properties);
        $this->assertEquals('Old Title', $log->properties['old']['title']);
        $this->assertEquals('New Title', $log->properties['new']['title']);
    }

    public function test_logger_records_action_string(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user);

        ActivityLogger::log('custom_action');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'custom_action',
            'user_id' => $user->id,
        ]);
    }
}
