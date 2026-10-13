<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_name_returns_first_and_last(): void
    {
        $user = User::factory()->create(['first_name' => 'Max', 'last_name' => 'Mustermann']);
        $this->assertEquals('Max Mustermann', $user->full_name);
    }

    public function test_full_name_falls_back_to_name(): void
    {
        $user = User::factory()->create(['first_name' => null, 'last_name' => null, 'name' => 'TestUser']);
        $this->assertEquals('TestUser', $user->full_name);
    }

    public function test_has_role_returns_true_when_assigned(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'is_system' => true]);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));
    }

    public function test_has_permission_caches_and_checks(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $perm = Permission::create(['name' => 'edit-page']);
        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('edit-page'));
        $this->assertFalse($user->hasPermission('delete-page'));
    }

    public function test_has_permission_is_consistent_across_calls(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $perm = Permission::create(['name' => 'show-page']);
        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        // First and second calls should return same result (cache working)
        $this->assertTrue($user->hasPermission('show-page'));
        $this->assertTrue($user->hasPermission('show-page'));
        $this->assertFalse($user->hasPermission('nonexistent'));
    }

    public function test_get_setting_returns_user_override(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'theme', 'value' => 'light']);
        UserSetting::create(['user_id' => $user->id, 'key' => 'theme', 'value' => 'dark']);

        $this->assertEquals('dark', $user->getSetting('theme'));
    }

    public function test_get_setting_falls_back_to_global(): void
    {
        $user = User::factory()->create();
        Setting::create(['key' => 'language', 'value' => 'de']);

        $this->assertEquals('de', $user->getSetting('language'));
    }

    public function test_get_setting_returns_null_for_missing(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->getSetting('nonexistent'));
    }

    public function test_set_setting_creates_user_setting(): void
    {
        $user = User::factory()->create();
        $user->setSetting('theme', 'dark');

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);
    }

    public function test_set_setting_updates_existing(): void
    {
        $user = User::factory()->create();
        $user->setSetting('theme', 'dark');
        $user->setSetting('theme', 'light');

        $this->assertEquals(1, UserSetting::where('user_id', $user->id)->where('key', 'theme')->count());
        $this->assertDatabaseHas('user_settings', ['user_id' => $user->id, 'key' => 'theme', 'value' => 'light']);
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->assertNotEquals('secret123', $user->password);
    }

    public function test_roles_relationship(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $user->roles()->attach($role);

        $this->assertCount(1, $user->roles);
        $this->assertEquals('admin', $user->roles->first()->name);
    }

    public function test_user_settings_relationship(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'k1', 'value' => 'v1']);
        UserSetting::create(['user_id' => $user->id, 'key' => 'k2', 'value' => 'v2']);

        $this->assertCount(2, $user->userSettings);
    }
}
