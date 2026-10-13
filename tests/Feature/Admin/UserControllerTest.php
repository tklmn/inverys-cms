<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_user_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.users.index'))
            ->assertOk();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.users.create'))
            ->assertOk();
    }

    public function test_admin_can_store_user(): void
    {
        $admin = $this->createAdmin();
        $role = Role::where('name', 'editor')->first();

        $this->actingAs($admin)
            ->post(route('backend.users.store'), [
                'name' => 'newuser',
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => 'newuser@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_active' => true,
                'roles' => [$role->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_user_requires_email(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.users.store'), [
                'name' => 'Test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_store_user_requires_unique_email(): void
    {
        $admin = $this->createAdmin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->post(route('backend.users.store'), [
                'name' => 'Test',
                'email' => 'taken@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_edit_user(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.users.edit', $user))
            ->assertOk();
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('backend.users.destroy', $user))
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_editor_cannot_view_users_list(): void
    {
        // The editor role is a content manager without user administration.
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.users.index'))
            ->assertForbidden();
    }

    public function test_editor_cannot_create_user(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.users.create'))
            ->assertForbidden();
    }

    public function test_editor_cannot_delete_user(): void
    {
        $editor = $this->createEditor();
        $user = User::factory()->create();

        $this->actingAs($editor)
            ->delete(route('backend.users.destroy', $user))
            ->assertForbidden();
    }
}
