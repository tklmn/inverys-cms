<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_role(): void
    {
        $role = Role::factory()->create(['name' => 'moderator']);

        $this->assertDatabaseHas('roles', ['name' => 'moderator']);
        $this->assertEquals('moderator', $role->name);
    }

    public function test_is_system_cast_to_boolean(): void
    {
        $role = Role::factory()->create(['is_system' => 1]);

        $this->assertIsBool($role->is_system);
        $this->assertTrue($role->is_system);

        $role2 = Role::factory()->create(['is_system' => 0]);

        $this->assertIsBool($role2->is_system);
        $this->assertFalse($role2->is_system);
    }

    public function test_can_attach_permissions_to_role(): void
    {
        $role = Role::factory()->create();
        $perm1 = Permission::factory()->create(['name' => 'edit-page']);
        $perm2 = Permission::factory()->create(['name' => 'delete-page']);

        $role->permissions()->attach([$perm1->id, $perm2->id]);

        $this->assertCount(2, $role->permissions);
        $this->assertTrue($role->permissions->contains($perm1));
        $this->assertTrue($role->permissions->contains($perm2));
    }

    public function test_can_attach_users_to_role(): void
    {
        $role = Role::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $role->users()->attach([$user1->id, $user2->id]);

        $this->assertCount(2, $role->users);
        $this->assertTrue($role->users->contains($user1));
        $this->assertTrue($role->users->contains($user2));
    }

    public function test_soft_delete_works(): void
    {
        $role = Role::factory()->create();
        $role->delete();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
        $this->assertNull(Role::find($role->id));
        $this->assertNotNull(Role::withTrashed()->find($role->id));
    }

    public function test_system_role_factory_state(): void
    {
        $role = Role::factory()->system()->create();

        $this->assertTrue($role->is_system);
    }
}
