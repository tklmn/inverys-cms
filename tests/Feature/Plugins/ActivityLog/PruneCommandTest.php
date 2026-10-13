<?php

namespace Tests\Feature\Plugins\ActivityLog;

use App\Contracts\SettingRepository;
use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\ActivityLog\ActivityLogServiceProvider;
use Plugins\ActivityLog\Models\ActivityLog;
use Tests\TestCase;

class PruneCommandTest extends TestCase
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

    public function test_command_deletes_entries_older_than_retention_days(): void
    {
        $settings = app(SettingRepository::class);
        $settings->set('activity_log.retention_days', '30', 'integer');
        $settings->set('activity_log.max_entries', '10000', 'integer');

        // Old entry (100 days ago) - should be deleted
        $old = ActivityLog::create(['action' => 'old_action']);
        $old->forceFill(['created_at' => now()->subDays(100)])->save();

        // Recent entry (5 days ago) - should be kept
        $recent = ActivityLog::create(['action' => 'recent_action']);
        $recent->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->artisan('activity-log:prune')->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recent->id]);
    }

    public function test_command_enforces_max_entries_limit(): void
    {
        $settings = app(SettingRepository::class);
        $settings->set('activity_log.retention_days', '0', 'integer');
        $settings->set('activity_log.max_entries', '3', 'integer');

        // Create 5 entries with distinct timestamps so ordering is deterministic
        for ($i = 1; $i <= 5; $i++) {
            $log = ActivityLog::create(['action' => "action_{$i}"]);
            $log->forceFill(['created_at' => now()->subMinutes(6 - $i)])->save();
        }

        $this->artisan('activity-log:prune')->assertSuccessful();

        // The 3 most recent should survive (action_3, action_4, action_5)
        $remaining = ActivityLog::orderByDesc('created_at')->pluck('action')->all();
        $this->assertCount(3, $remaining);
        $this->assertContains('action_5', $remaining);
        $this->assertContains('action_4', $remaining);
        $this->assertContains('action_3', $remaining);
    }

    public function test_command_does_nothing_when_within_limits(): void
    {
        $settings = app(SettingRepository::class);
        $settings->set('activity_log.retention_days', '90', 'integer');
        $settings->set('activity_log.max_entries', '10000', 'integer');

        // Create 3 recent entries
        for ($i = 1; $i <= 3; $i++) {
            $log = ActivityLog::create(['action' => "action_{$i}"]);
            $log->forceFill(['created_at' => now()->subDays($i)])->save();
        }

        $this->artisan('activity-log:prune')->assertSuccessful();

        $this->assertEquals(3, ActivityLog::count());
    }
}
