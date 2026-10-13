<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles. The admin account is owned by DefaultAdminUserSeeder (which assigns it
        // a strong random password); this seeder only creates the fixed-password demo roles.
        $editorRole = Role::where('name', 'editor')->first();
        $userRole = Role::where('name', 'user')->first();
        $blockedRole = Role::where('name', 'blocked')->first();

        if (! $editorRole || ! $userRole || ! $blockedRole) {
            // If roles don't exist, abort
            $this->command->error('Roles not found. Please run the RolePermissionSeeder first.');

            return;
        }

        // Create editor user
        $editor = User::firstOrCreate(
            ['email' => 'editor@example.com'],
            [
                'name' => 'Editor User',
                'first_name' => 'Editor',
                'last_name' => 'User',
                'phone' => '+1234567891',
                'job_title' => 'Content Manager',
                'bio' => 'Editor account with content management privileges.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $editor->roles()->sync([$editorRole->id]);

        // Create normal user
        $normalUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Normal User',
                'first_name' => 'Normal',
                'last_name' => 'User',
                'phone' => '+1234567892',
                'job_title' => 'Member',
                'bio' => 'Standard user account with limited permissions.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $normalUser->roles()->sync([$userRole->id]);

        // Create blocked user
        $blockedUser = User::firstOrCreate(
            ['email' => 'blocked@example.com'],
            [
                'name' => 'Blocked User',
                'first_name' => 'Blocked',
                'last_name' => 'User',
                'phone' => '+1234567893',
                'job_title' => 'Suspended Account',
                'bio' => 'This account has been blocked due to violations.',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => false,
            ]
        );
        $blockedUser->roles()->sync([$blockedRole->id]);

        // Disable 2FA for all demo users (example.com emails can't receive codes)
        foreach ([$editor, $normalUser, $blockedUser] as $user) {
            UserSetting::firstOrCreate(
                ['user_id' => $user->id, 'key' => 'two_factor_disabled'],
                ['value' => '1'],
            );
        }

        $this->command->info('Demo users created successfully.');
        $this->command->info('Login with: editor@example.com, user@example.com or blocked@example.com — password "password".');
        $this->command->info('The admin account (admin@example.com) uses the password shown by the default admin seeder.');
    }
}
