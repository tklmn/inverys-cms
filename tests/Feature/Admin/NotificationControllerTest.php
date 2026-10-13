<?php

namespace Tests\Feature\Admin;

use App\Models\CmsNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_notifications_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.notifications.index'))
            ->assertOk();
    }

    public function test_admin_can_get_notification_data_as_json(): void
    {
        $admin = $this->createAdmin();

        // Enable the log_change notification type for this user
        $admin->setSetting('notification.log_change', 'on');

        CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Test Notification',
            'body' => 'Something happened',
            'data' => ['key' => 'value'],
        ]);

        $this->actingAs($admin)
            ->getJson(route('backend.notifications.data'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Test Notification']);
    }

    public function test_admin_can_get_unread_count_as_json(): void
    {
        $admin = $this->createAdmin();

        CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Unread Notification',
            'body' => 'Unread body',
        ]);

        $this->actingAs($admin)
            ->getJson(route('backend.notifications.unread-count'))
            ->assertOk()
            ->assertJsonStructure(['count']);
    }

    public function test_admin_can_mark_notification_as_read(): void
    {
        $admin = $this->createAdmin();

        $notification = CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Mark Me Read',
            'body' => 'Body text',
        ]);

        $this->actingAs($admin)
            ->post(route('backend.notifications.mark-read', $notification->id))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('cms_notification_reads', [
            'notification_id' => $notification->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_mark_all_as_read(): void
    {
        $admin = $this->createAdmin();

        CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Notification 1',
            'body' => 'Body 1',
        ]);

        CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Notification 2',
            'body' => 'Body 2',
        ]);

        $this->actingAs($admin)
            ->post(route('backend.notifications.mark-all-read'))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_admin_can_delete_notification(): void
    {
        $admin = $this->createAdmin();

        $notification = CmsNotification::create([
            'type' => 'log_change',
            'title' => 'Delete Me',
            'body' => 'Body text',
        ]);

        $this->actingAs($admin)
            ->delete(route('backend.notifications.destroy', $notification))
            ->assertRedirect();

        $this->assertDatabaseMissing('cms_notifications', ['id' => $notification->id]);
    }

    public function test_system_messages_need_system_access(): void
    {
        // Editor: may see notifications, but not the system — HTTP errors
        // and failed logins (IP, email) stay hidden from them
        $editor = $this->createEditor();
        $admin = $this->createAdmin();
        $service = app(NotificationService::class);

        CmsNotification::create(['type' => 'http_error', 'title' => 'HTTP 404: GET /secret', 'body' => 'Not Found']);

        $this->assertSame([], $service->getEnabledTypesFor($editor));
        $this->assertSame(0, $service->unreadCountFor($editor));
        $this->assertContains('http_error', $service->getEnabledTypesFor($admin));
    }

    public function test_admin_can_open_the_notification_settings(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.notifications.settings'))
            ->assertOk()
            ->assertSee(__('translation.notifications.type_cms_update'));
    }

    public function test_every_type_switch_is_saved(): void
    {
        $admin = $this->createAdmin();

        // Only failed_login stays on; every other switch is sent as off (absent)
        $this->actingAs($admin)
            ->post(route('backend.notifications.settings.save'), ['failed_login' => 'on'])
            ->assertRedirect(route('backend.notifications.settings'));

        $admin = $admin->fresh();
        foreach (['log_change', 'laravel_update', 'cms_update', 'http_error'] as $type) {
            $this->assertSame('off', $admin->getSetting('notification.'.$type), $type);
        }
        $this->assertSame(['failed_login'], app(NotificationService::class)->getEnabledTypesFor($admin));
    }

    public function test_editor_without_show_notifications_gets_403(): void
    {
        $editor = $this->createEditor();

        // Editor has show-notifications but not delete-notifications.
        // Use a user with no notification permissions at all.
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.notifications.index'))
            ->assertForbidden();
    }
}
