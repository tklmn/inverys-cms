<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_log_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.logs.index'))
            ->assertOk();
    }

    public function test_editor_cannot_view_logs(): void
    {
        // The editor role is a content manager with no access to system logs.
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.logs.index'))
            ->assertForbidden();
    }

    public function test_plain_user_cannot_view_logs(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.logs.index'))
            ->assertForbidden();
    }

    public function test_directory_traversal_attempts_are_blocked(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.logs.show', ['filename' => '..%2F..%2Fetc%2Fpasswd']))
            ->assertNotFound();
    }
}
