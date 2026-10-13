<?php

namespace Tests\Unit\Services;

use App\Models\CmsNotification;
use App\Models\CmsNotificationRead;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService;
        $this->seedNotificationSettings();
        // System messages require show-system / show-logs
        $this->seedPermissions();
    }

    /**
     * Seed global notification type settings so that getSetting() returns 'on' by default.
     */
    private function seedNotificationSettings(): void
    {
        foreach (['log_change', 'laravel_update', 'http_error', 'failed_login'] as $type) {
            Setting::firstOrCreate(
                ['key' => "notification.{$type}"],
                ['value' => 'on', 'type' => 'string']
            );
        }
    }

    public function test_create_persists_notification(): void
    {
        $notification = $this->service->create('log_change', 'Test Title', 'Test body');

        $this->assertDatabaseHas('cms_notifications', [
            'id' => $notification->id,
            'type' => 'log_change',
            'title' => 'Test Title',
            'body' => 'Test body',
        ]);
    }

    public function test_unread_count_for_user_returns_correct_count(): void
    {
        $user = $this->createAdmin();

        CmsNotification::create(['type' => 'log_change', 'title' => 'A']);
        CmsNotification::create(['type' => 'log_change', 'title' => 'B']);
        CmsNotification::create(['type' => 'http_error', 'title' => 'C']);

        $this->assertSame(3, $this->service->unreadCountFor($user));
    }

    public function test_unread_count_excludes_read_notifications(): void
    {
        $user = $this->createAdmin();

        $n1 = CmsNotification::create(['type' => 'log_change', 'title' => 'A']);
        $n2 = CmsNotification::create(['type' => 'log_change', 'title' => 'B']);

        $this->service->markAsRead($n1->id, $user);

        $this->assertSame(1, $this->service->unreadCountFor($user));
    }

    public function test_recent_for_returns_notifications_for_user(): void
    {
        $user = $this->createAdmin();

        CmsNotification::create(['type' => 'log_change', 'title' => 'First']);
        CmsNotification::create(['type' => 'http_error', 'title' => 'Second']);

        $recent = $this->service->recentFor($user);

        $this->assertCount(2, $recent);
    }

    public function test_recent_for_respects_limit(): void
    {
        $user = $this->createAdmin();

        for ($i = 0; $i < 5; $i++) {
            CmsNotification::create(['type' => 'log_change', 'title' => "N{$i}"]);
        }

        $recent = $this->service->recentFor($user, 3);

        $this->assertCount(3, $recent);
    }

    public function test_recent_for_includes_is_read_flag(): void
    {
        $user = $this->createAdmin();

        $n1 = CmsNotification::create(['type' => 'log_change', 'title' => 'Read']);
        $n2 = CmsNotification::create(['type' => 'log_change', 'title' => 'Unread']);

        $this->service->markAsRead($n1->id, $user);

        $recent = $this->service->recentFor($user);
        $readOne = $recent->firstWhere('id', $n1->id);
        $unreadOne = $recent->firstWhere('id', $n2->id);

        $this->assertTrue($readOne->is_read);
        $this->assertFalse($unreadOne->is_read);
    }

    public function test_recent_for_filters_by_user_enabled_types(): void
    {
        $user = $this->createAdmin();
        // Disable http_error for this user
        $user->setSetting('notification.http_error', 'off');
        // Reset cached settings so getSetting picks up the new user-level value
        $user->cachedSettings = [];

        CmsNotification::create(['type' => 'log_change', 'title' => 'Visible']);
        CmsNotification::create(['type' => 'http_error', 'title' => 'Hidden']);

        $recent = $this->service->recentFor($user);

        $this->assertCount(1, $recent);
        $this->assertSame('Visible', $recent->first()->title);
    }

    public function test_mark_as_read_creates_read_record(): void
    {
        $user = $this->createAdmin();
        $notification = CmsNotification::create(['type' => 'log_change', 'title' => 'Test']);

        $this->service->markAsRead($notification->id, $user);

        $this->assertDatabaseHas('cms_notification_reads', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = $this->createAdmin();
        $notification = CmsNotification::create(['type' => 'log_change', 'title' => 'Test']);

        $this->service->markAsRead($notification->id, $user);
        $this->service->markAsRead($notification->id, $user);

        $this->assertSame(1, CmsNotificationRead::where('notification_id', $notification->id)->count());
    }

    public function test_mark_all_as_read_marks_all_unread_notifications(): void
    {
        $user = $this->createAdmin();

        CmsNotification::create(['type' => 'log_change', 'title' => 'A']);
        CmsNotification::create(['type' => 'http_error', 'title' => 'B']);
        CmsNotification::create(['type' => 'failed_login', 'title' => 'C']);

        $this->assertSame(3, $this->service->unreadCountFor($user));

        $this->service->markAllAsRead($user);

        $this->assertSame(0, $this->service->unreadCountFor($user));
    }

    public function test_prune_removes_old_notifications(): void
    {
        $old = CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Old',
        ]);
        // Use a raw query to backdate created_at, bypassing Eloquent timestamp handling
        CmsNotification::where('id', $old->id)
            ->update(['created_at' => now()->subDays(31)]);

        CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Recent',
        ]);

        $this->assertSame(2, CmsNotification::count());

        $deleted = $this->service->prune(30);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, CmsNotification::count());
        $this->assertSame('Recent', CmsNotification::first()->title);
    }

    public function test_get_enabled_types_returns_all_by_default(): void
    {
        $user = $this->createAdmin();

        $types = $this->service->getEnabledTypesFor($user);

        $this->assertContains('log_change', $types);
        $this->assertContains('laravel_update', $types);
        $this->assertContains('http_error', $types);
        $this->assertContains('failed_login', $types);
    }

    public function test_get_enabled_types_respects_user_settings(): void
    {
        $user = $this->createAdmin();
        $user->setSetting('notification.log_change', 'off');
        $user->setSetting('notification.laravel_update', 'off');
        $user->cachedSettings = [];

        $types = $this->service->getEnabledTypesFor($user);

        $this->assertNotContains('log_change', $types);
        $this->assertNotContains('laravel_update', $types);
        $this->assertContains('http_error', $types);
        $this->assertContains('failed_login', $types);
    }
}
