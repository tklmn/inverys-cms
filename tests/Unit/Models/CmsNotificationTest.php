<?php

namespace Tests\Unit\Models;

use App\Models\CmsNotification;
use App\Models\CmsNotificationRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_notification(): void
    {
        $notification = CmsNotification::create([
            'type' => 'info',
            'title' => 'System Update',
            'body' => 'A new version is available.',
        ]);

        $this->assertDatabaseHas('cms_notifications', [
            'type' => 'info',
            'title' => 'System Update',
        ]);
        $this->assertEquals('System Update', $notification->title);
    }

    public function test_data_cast_to_array(): void
    {
        $data = ['version' => '2.0', 'urgent' => true];
        $notification = CmsNotification::create([
            'type' => 'update',
            'title' => 'Update',
            'body' => 'Details here.',
            'data' => $data,
        ]);

        $notification->refresh();

        $this->assertIsArray($notification->data);
        $this->assertEquals('2.0', $notification->data['version']);
        $this->assertTrue($notification->data['urgent']);
    }

    public function test_of_type_scope(): void
    {
        CmsNotification::create(['type' => 'info', 'title' => 'Info 1', 'body' => 'Body']);
        CmsNotification::create(['type' => 'warning', 'title' => 'Warning 1', 'body' => 'Body']);
        CmsNotification::create(['type' => 'info', 'title' => 'Info 2', 'body' => 'Body']);

        $infos = CmsNotification::ofType('info')->get();

        $this->assertCount(2, $infos);
        $this->assertTrue($infos->every(fn ($n) => $n->type === 'info'));
    }

    public function test_of_types_scope(): void
    {
        CmsNotification::create(['type' => 'info', 'title' => 'A', 'body' => 'Body']);
        CmsNotification::create(['type' => 'warning', 'title' => 'B', 'body' => 'Body']);
        CmsNotification::create(['type' => 'error', 'title' => 'C', 'body' => 'Body']);
        CmsNotification::create(['type' => 'success', 'title' => 'D', 'body' => 'Body']);

        $results = CmsNotification::ofTypes(['info', 'error'])->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($n) => in_array($n->type, ['info', 'error'])));
    }

    public function test_is_read_by_returns_false_for_unread_user(): void
    {
        $notification = CmsNotification::create([
            'type' => 'info',
            'title' => 'Test',
            'body' => 'Body',
        ]);
        $user = User::factory()->create();

        $this->assertFalse($notification->isReadBy($user));
    }

    public function test_is_read_by_returns_true_after_marking_read(): void
    {
        $notification = CmsNotification::create([
            'type' => 'info',
            'title' => 'Test',
            'body' => 'Body',
        ]);
        $user = User::factory()->create();

        CmsNotificationRead::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $this->assertTrue($notification->isReadBy($user));
    }

    public function test_reads_relationship(): void
    {
        $notification = CmsNotification::create([
            'type' => 'info',
            'title' => 'Test',
            'body' => 'Body',
        ]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        CmsNotificationRead::create([
            'notification_id' => $notification->id,
            'user_id' => $user1->id,
            'read_at' => now(),
        ]);
        CmsNotificationRead::create([
            'notification_id' => $notification->id,
            'user_id' => $user2->id,
            'read_at' => now(),
        ]);

        $this->assertCount(2, $notification->reads);
        $this->assertInstanceOf(CmsNotificationRead::class, $notification->reads->first());
    }
}
