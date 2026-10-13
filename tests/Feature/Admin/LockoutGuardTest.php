<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protection against locking yourself out: your own account stays active and keeps the
 * admin role, system roles keep their name.
 */
class LockoutGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_own_account_stays_active_and_admin(): void
    {
        $admin = $this->createAdmin(['is_active' => true]);
        $editorRole = Role::where('name', 'editor')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('backend.users.update', $admin), [
                'name' => $admin->name,
                'first_name' => 'Ada',
                'last_name' => 'Admin',
                'email' => $admin->email,
                'is_active' => '0',
                'roles' => [$editorRole->id],
            ])
            ->assertRedirect();

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->roles->contains('name', 'admin'));
        $this->assertTrue($admin->roles->contains('name', 'editor'));
    }

    public function test_other_accounts_can_be_deactivated(): void
    {
        $admin = $this->createAdmin();
        $other = User::factory()->create(['is_active' => true]);
        $editorRole = Role::where('name', 'editor')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('backend.users.update', $other), [
                'name' => $other->name,
                'first_name' => 'Otto',
                'last_name' => 'Other',
                'email' => $other->email,
                'is_active' => '0',
                'roles' => [$editorRole->id],
            ])
            ->assertRedirect();

        $this->assertFalse($other->refresh()->is_active);
    }

    public function test_system_role_keeps_its_name(): void
    {
        $admin = $this->createAdmin();
        $role = Role::where('name', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('backend.roles.update', $role), [
                'name' => 'superuser',
                'permissions' => $role->permissions->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertSame('admin', $role->refresh()->name);
    }
}
