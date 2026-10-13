<?php

namespace Tests\Feature\Admin;

use App\Contracts\PermissionManager;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_permissions_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.permissions.index'))
            ->assertOk();
    }

    public function test_admin_can_create_permission(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.permissions.store'), [
                'name' => 'test-custom-permission',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('permissions', ['name' => 'test-custom-permission']);
    }

    public function test_admin_can_edit_permission(): void
    {
        $admin = $this->createAdmin();
        $permission = Permission::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.permissions.edit', $permission))
            ->assertOk();
    }

    public function test_admin_can_delete_permission(): void
    {
        $admin = $this->createAdmin();
        $permission = Permission::factory()->create();

        $this->actingAs($admin)
            ->delete(route('backend.permissions.destroy', $permission))
            ->assertRedirect();

        $this->assertSoftDeleted('permissions', ['id' => $permission->id]);
    }

    public function test_system_permission_cannot_be_deleted_or_renamed(): void
    {
        $admin = $this->createAdmin();
        $permission = Permission::factory()->create(['name' => 'code-checked', 'is_system' => true]);

        $this->actingAs($admin)
            ->delete(route('backend.permissions.destroy', $permission))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->put(route('backend.permissions.update', $permission), ['name' => 'renamed'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'code-checked', 'deleted_at' => null]);
    }

    public function test_permissions_created_from_code_are_system(): void
    {
        app(PermissionManager::class)->createPermission('plugin-thing');

        $this->assertTrue(Permission::where('name', 'plugin-thing')->first()->is_system);
    }

    public function test_store_requires_name(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.permissions.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_unique_name(): void
    {
        $admin = $this->createAdmin();
        Permission::factory()->create(['name' => 'duplicate-permission']);

        $this->actingAs($admin)
            ->post(route('backend.permissions.store'), [
                'name' => 'duplicate-permission',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_editor_without_permission_gets_403(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.permissions.index'))
            ->assertForbidden();
    }
}
