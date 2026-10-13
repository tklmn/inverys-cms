<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->exists()) {
            $this->command?->info('Default admin already exists. Skipping.');

            return;
        }

        // Generate a strong random password instead of a well-known default, so a seeded
        // install never ships with guessable admin credentials. It is printed once below
        // and stored only as a hash — it cannot be recovered afterwards.
        $password = Str::password(20);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->attach($adminRole->id);
        }

        // Disable 2FA for this account: the placeholder @example.com address cannot receive
        // codes, so leaving 2FA enabled would lock the operator out. Mirrors the web installer.
        UserSetting::updateOrCreate(
            ['user_id' => $admin->id, 'key' => 'two_factor_disabled'],
            ['value' => '1'],
        );

        $this->command?->warn('Default admin created — copy this password now, it will NOT be shown again:');
        $this->command?->info('  Email:    admin@example.com');
        $this->command?->info('  Password: '.$password);
        $this->command?->warn('  Log in and change it immediately (Profile → Security).');
    }
}
