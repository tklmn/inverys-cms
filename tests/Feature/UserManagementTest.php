<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Without this the test depended on a previous test class having left
        // 2FA switched off in the (static) settings cache
        $this->markAsInstalled();
    }

    /**
     * Test that new users are created as inactive by default during registration.
     *
     * @return void
     */
    public function test_new_users_are_inactive_by_default()
    {
        // Create a default 'user' role first
        $userRole = Role::create([
            'name' => 'user',
            'is_system' => true,
        ]);

        $userData = [
            'name' => $this->faker->userName,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'password' => 'P@ssw0rd!23',
            'password_confirmation' => 'P@ssw0rd!23',
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email'],
            'is_active' => false, // Check that user is inactive by default
        ]);

        // Check that the user was assigned the default 'user' role
        $user = User::where('email', $userData['email'])->first();
        $this->assertTrue($user->roles()->where('name', 'user')->exists());
    }

    /**
     * Test avatar upload functionality.
     *
     * @return void
     */
    public function test_user_can_upload_avatar()
    {
        Storage::fake('public');

        // Create a user with admin role
        $user = User::factory()->create();

        $this->actingAs($user);

        // Create a fake image for upload
        $file = UploadedFile::fake()->image('avatar.jpg');

        // Update profile with the new avatar
        $response = $this->patch('/profile', [
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $file,
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('status', 'profile-updated');

        // Refresh user instance from database
        $user->refresh();

        // Assert the avatar was stored
        $this->assertNotNull($user->avatar);
        Storage::disk('public')->assertExists($user->avatar);
    }

    /**
     * Test avatar deletion functionality.
     *
     * @return void
     */
    public function test_user_can_delete_avatar()
    {
        Storage::fake('public');

        // Create a user with an avatar
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg');
        $avatarPath = $file->store('avatars', 'public');

        $user->avatar = $avatarPath;
        $user->save();

        $this->actingAs($user);

        // Assert the avatar exists before deletion
        Storage::disk('public')->assertExists($avatarPath);

        // Delete the avatar
        $response = $this->delete('/profile/avatar/delete');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('status', 'avatar-deleted');

        // Refresh user instance from database
        $user->refresh();

        // Assert the avatar was removed from the user record
        $this->assertNull($user->avatar);

        // Assert the avatar file was deleted
        Storage::disk('public')->assertMissing($avatarPath);
    }

    /**
     * Test that admin can activate/deactivate users.
     *
     * @return void
     */
    public function test_admin_can_change_user_active_status()
    {
        // Create roles and permissions
        $adminRole = Role::create([
            'name' => 'admin',
            'is_system' => true,
        ]);

        $userRole = Role::create([
            'name' => 'user',
            'is_system' => true,
        ]);

        // Create required permissions for the admin
        $editUserPerm = Permission::create(['name' => 'edit-user']);
        $accessDashboardPerm = Permission::create(['name' => 'access-dashboard']);
        $adminRole->permissions()->attach([$editUserPerm->id, $accessDashboardPerm->id]);

        // Create admin user
        $admin = User::factory()->create([
            'is_active' => true,
        ]);
        $admin->roles()->attach($adminRole->id);

        // Create regular user (inactive)
        $user = User::factory()->create([
            'is_active' => false,
        ]);
        $user->roles()->attach($userRole->id);

        $this->actingAs($admin);

        // Activate the user
        $response = $this->put(route('backend.users.update', $user), [
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_active' => true,
            'roles' => [$userRole->id],
        ]);

        $response->assertRedirect();

        // Refresh user instance from database
        $user->refresh();

        // Assert the user is now active
        $this->assertTrue($user->is_active);

        // Deactivate the user
        $response = $this->put(route('backend.users.update', $user), [
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'roles' => [$userRole->id],
            // Omitting is_active which should make it false since it's a checkbox
        ]);

        // Refresh user instance from database
        $user->refresh();

        // Assert the user is now inactive
        $this->assertFalse($user->is_active);
    }
}
