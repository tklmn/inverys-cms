<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_permission(): void
    {
        $permission = Permission::factory()->create(['name' => 'edit-page']);

        $this->assertDatabaseHas('permissions', ['name' => 'edit-page']);
        $this->assertEquals('edit-page', $permission->name);
    }

    public function test_can_attach_roles(): void
    {
        $permission = Permission::factory()->create();
        $role1 = Role::factory()->create(['name' => 'admin']);
        $role2 = Role::factory()->create(['name' => 'editor']);

        $permission->roles()->attach([$role1->id, $role2->id]);

        $this->assertCount(2, $permission->roles);
        $this->assertTrue($permission->roles->contains($role1));
        $this->assertTrue($permission->roles->contains($role2));
    }

    public function test_soft_delete_works(): void
    {
        $permission = Permission::factory()->create();
        $permission->delete();

        $this->assertSoftDeleted('permissions', ['id' => $permission->id]);
        $this->assertNull(Permission::find($permission->id));
        $this->assertNotNull(Permission::withTrashed()->find($permission->id));
    }
}
