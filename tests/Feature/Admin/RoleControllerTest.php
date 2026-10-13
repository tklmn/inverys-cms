<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_roles(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.roles.index'))
            ->assertOk();
    }

    public function test_admin_can_create_role(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.roles.store'), ['name' => 'moderator'])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'moderator']);
    }

    public function test_admin_can_edit_role(): void
    {
        $admin = $this->createAdmin();
        $role = Role::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.roles.edit', $role))
            ->assertOk();
    }

    public function test_cannot_delete_system_role(): void
    {
        $admin = $this->createAdmin();
        $role = Role::where('name', 'admin')->first();

        $this->actingAs($admin)
            ->delete(route('backend.roles.destroy', $role))
            ->assertRedirect();

        // System roles should not be deleted
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_editor_cannot_access_roles(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.roles.index'))
            ->assertForbidden();
    }
}
