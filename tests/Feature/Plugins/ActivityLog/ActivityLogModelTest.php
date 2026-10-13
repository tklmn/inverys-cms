<?php

namespace Tests\Feature\Plugins\ActivityLog;

use App\Models\Plugin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\ActivityLog\ActivityLogServiceProvider;
use Plugins\ActivityLog\Models\ActivityLog;
use Tests\TestCase;

class ActivityLogModelTest extends TestCase
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

    public function test_can_create_activity_log_entry(): void
    {
        $log = ActivityLog::create([
            'user_id' => null,
            'user_name' => 'Test User',
            'action' => 'created',
            'subject_type' => 'App\\Models\\Page',
            'subject_id' => 1,
            'subject_label' => 'Home',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'action' => 'created',
            'subject_label' => 'Home',
        ]);
    }

    public function test_properties_cast_to_array(): void
    {
        $log = ActivityLog::create([
            'action' => 'updated',
            'properties' => ['old' => ['title' => 'Old'], 'new' => ['title' => 'New']],
        ]);

        $log->refresh();

        $this->assertIsArray($log->properties);
        $this->assertArrayHasKey('old', $log->properties);
        $this->assertArrayHasKey('new', $log->properties);
    }

    public function test_user_relationship_works(): void
    {
        $user = User::factory()->create();

        $log = ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => 'login',
        ]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_for_user_scope_filters_by_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ActivityLog::create(['user_id' => $user->id, 'action' => 'login']);
        ActivityLog::create(['user_id' => $user->id, 'action' => 'logout']);
        ActivityLog::create(['user_id' => $otherUser->id, 'action' => 'login']);

        $results = ActivityLog::forUser($user->id)->get();

        $this->assertCount(2, $results);
        $results->each(fn ($log) => $this->assertEquals($user->id, $log->user_id));
    }

    public function test_for_action_scope_filters_by_action(): void
    {
        ActivityLog::create(['action' => 'created']);
        ActivityLog::create(['action' => 'updated']);
        ActivityLog::create(['action' => 'created']);

        $results = ActivityLog::forAction('created')->get();

        $this->assertCount(2, $results);
        $results->each(fn ($log) => $this->assertEquals('created', $log->action));
    }

    public function test_for_subject_scope_filters_by_subject_type_and_id(): void
    {
        ActivityLog::create(['action' => 'created', 'subject_type' => 'App\\Models\\Page', 'subject_id' => 1]);
        ActivityLog::create(['action' => 'updated', 'subject_type' => 'App\\Models\\Page', 'subject_id' => 2]);
        ActivityLog::create(['action' => 'created', 'subject_type' => 'App\\Models\\User', 'subject_id' => 1]);

        // Filter by type only
        $byType = ActivityLog::forSubject('App\\Models\\Page')->get();
        $this->assertCount(2, $byType);

        // Filter by type and id
        $byTypeAndId = ActivityLog::forSubject('App\\Models\\Page', 1)->get();
        $this->assertCount(1, $byTypeAndId);
        $this->assertEquals(1, $byTypeAndId->first()->subject_id);
    }

    public function test_recent_scope_limits_and_orders_by_created_at_desc(): void
    {
        // Create entries with distinct timestamps
        for ($i = 1; $i <= 5; $i++) {
            $log = ActivityLog::create(['action' => 'action_'.$i]);
            $log->forceFill(['created_at' => now()->subMinutes(6 - $i)])->save();
        }

        $results = ActivityLog::recent(3)->get();

        $this->assertCount(3, $results);
        // Most recent first (action_5 was created most recently)
        $this->assertEquals('action_5', $results[0]->action);
        $this->assertEquals('action_4', $results[1]->action);
        $this->assertEquals('action_3', $results[2]->action);
    }

    public function test_has_diff_returns_true_when_properties_has_old_and_new(): void
    {
        $log = ActivityLog::create([
            'action' => 'updated',
            'properties' => [
                'old' => ['title' => 'Old Title'],
                'new' => ['title' => 'New Title'],
            ],
        ]);

        $this->assertTrue($log->hasDiff());
    }

    public function test_has_diff_returns_false_when_properties_is_empty(): void
    {
        $log = ActivityLog::create([
            'action' => 'deleted',
            'properties' => null,
        ]);

        $this->assertFalse($log->hasDiff());
    }

    public function test_get_diff_fields_returns_field_names_that_differ(): void
    {
        $log = ActivityLog::create([
            'action' => 'updated',
            'properties' => [
                'old' => ['title' => 'Old', 'slug' => 'same', 'content' => 'original'],
                'new' => ['title' => 'New', 'slug' => 'same', 'content' => 'changed'],
            ],
        ]);

        $changed = $log->getDiffFields();

        $this->assertContains('title', $changed);
        $this->assertContains('content', $changed);
        $this->assertNotContains('slug', $changed);
    }
}
