<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve or create roles
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['is_system' => true]
        );
        $editor = Role::firstOrCreate(
            ['name' => 'editor'],
            ['is_system' => false]
        );
        $user = Role::firstOrCreate(
            ['name' => 'user'],
            ['is_system' => true]
        );
        $blocked = Role::firstOrCreate(
            ['name' => 'blocked'],
            ['is_system' => false]
        );

        // Create Permissions
        $permissions = Permission::CORE;

        // Ensure every core permission exists.
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission], ['is_system' => true]);
        }

        // Editor = content manager: manages content (incl. content plugins such as
        // blog, forms and gallery, which grant their own editor permissions via
        // syncPermissions() — full view/create/edit/delete, but not the plugin
        // settings) but performs no user or system administration and has no
        // access to system logs.
        $editorExcludedPermissions = [
            'show-role', 'create-role', 'edit-role', 'delete-role',
            'show-permission', 'create-permission', 'edit-permission', 'delete-permission',
            'show-settings', 'create-settings', 'edit-settings', 'delete-settings',
            'manage-search-settings', 'manage-security-settings', 'manage-seo-settings',
            'show-system',
            'show-user', 'create-user', 'edit-user', 'delete-user',
            'show-logs', 'download-logs', 'clear-logs',
            'manage-themes', 'edit-theme-files', 'manage-plugins', 'manage-updates', 'api-access',
            'delete-notifications', 'manage-notification-settings',
        ];

        // Grant the allowed core permissions and revoke the excluded ones WITHOUT a
        // full detach, so permissions that plugins assign to the editor role keep
        // sticking even when this seeder is re-run after the plugins are enabled.
        $allowedCoreIds = Permission::whereIn('name', array_values(array_diff($permissions, $editorExcludedPermissions)))->pluck('id');
        $excludedCoreIds = Permission::whereIn('name', $editorExcludedPermissions)->pluck('id');
        $editor->permissions()->syncWithoutDetaching($allowedCoreIds);
        $editor->permissions()->detach($excludedCoreIds);

        // The 'user' (authenticated frontend visitor) and 'blocked' roles hold no
        // backend permissions.
        $user->permissions()->detach();

        // Admin gets ALL permissions (including plugin-registered ones).
        $admin->permissions()->sync(Permission::pluck('id'));

        // Seed default notification settings
        Setting::updateOrCreate(
            ['key' => 'notification.http_status_codes'],
            ['value' => '401,403,404,500,503']
        );
    }
}
