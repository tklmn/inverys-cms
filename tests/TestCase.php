<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests render backend/frontend views that use @vite. Decouple them from
        // the built manifest (public/build) so the suite is hermetic and does not
        // require `npm run build` first — e.g. in CI's PHP-only job.
        $this->withoutVite();
    }

    /**
     * Disable two-factor authentication for tests.
     * Call this in setUp() of feature tests that use RefreshDatabase.
     */
    protected function disableTwoFactor(): void
    {
        Setting::firstOrCreate(
            ['key' => 'security.two_factor_enabled'],
            ['value' => 'off', 'type' => 'string', 'description' => 'Disabled for tests'],
        );
    }

    /**
     * Create a user with the admin role and all permissions.
     */
    protected function createAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = $this->ensureAdminRole();
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->clearRoleCache();

        return $user;
    }

    /**
     * Create a user with the editor role (limited permissions).
     */
    protected function createEditor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = $this->ensureEditorRole();
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->clearRoleCache();

        return $user;
    }

    /**
     * Create a user with no backend permissions.
     */
    protected function createUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::firstOrCreate(['name' => 'user'], ['is_system' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * Seed the full permission system (roles + permissions).
     * Lighter than running the full seeder — only creates what's needed.
     */
    protected function seedPermissions(): void
    {
        $admin = $this->ensureAdminRole();
        $editor = $this->ensureEditorRole();

        $allPermissions = [
            'show-content', 'create-content', 'edit-content', 'delete-content',
            'show-user', 'create-user', 'edit-user', 'delete-user',
            'show-role', 'create-role', 'edit-role', 'delete-role',
            'show-permission', 'create-permission', 'edit-permission', 'delete-permission',
            'show-settings', 'create-settings', 'edit-settings', 'delete-settings',
            'show-email-settings', 'edit-email-settings',
            'show-page', 'create-page', 'edit-page', 'delete-page',
            'show-page-category', 'create-page-category', 'edit-page-category', 'delete-page-category',
            'show-logs', 'download-logs', 'clear-logs',
            'show-media', 'create-media', 'edit-media', 'delete-media',
            'show-system', 'manage-themes', 'manage-plugins', 'manage-updates', 'manage-blocks',
            'install-themes', 'install-plugins', 'manage-marketplace',
            'api-access', 'manage-trash', 'manage-search-settings', 'manage-security-settings', 'manage-seo-settings',
            'show-notifications', 'delete-notifications', 'manage-notification-settings',
            'access-dashboard',
        ];

        // Mirrors the production RolePermissionSeeder: the editor is a content manager
        // with no user administration and no access to system logs.
        $editorExcluded = [
            'show-role', 'create-role', 'edit-role', 'delete-role',
            'show-permission', 'create-permission', 'edit-permission', 'delete-permission',
            'show-settings', 'create-settings', 'edit-settings', 'delete-settings',
            'show-email-settings', 'edit-email-settings',
            'manage-search-settings', 'manage-security-settings', 'manage-seo-settings', 'show-system',
            'show-user', 'create-user', 'edit-user', 'delete-user',
            'show-logs', 'download-logs', 'clear-logs',
            'manage-themes', 'manage-plugins', 'manage-updates', 'api-access',
            'install-themes', 'install-plugins', 'manage-marketplace',
            'delete-notifications', 'manage-notification-settings',
        ];

        $adminPermIds = [];
        $editorPermIds = [];

        foreach ($allPermissions as $name) {
            $perm = Permission::firstOrCreate(['name' => $name]);
            $adminPermIds[] = $perm->id;
            if (! in_array($name, $editorExcluded)) {
                $editorPermIds[] = $perm->id;
            }
        }

        $admin->permissions()->sync($adminPermIds);
        $editor->permissions()->sync($editorPermIds);
    }

    /**
     * Ensure the installed marker exists (so middleware doesn't redirect to installer).
     * Also disables 2FA so authenticated tests are not blocked by the challenge.
     */
    protected function markAsInstalled(): void
    {
        $path = storage_path('installed');
        if (! file_exists($path)) {
            file_put_contents($path, now()->toDateTimeString());
        }

        $this->disableTwoFactor();
    }

    private function ensureAdminRole(): Role
    {
        return Role::firstOrCreate(['name' => 'admin'], ['is_system' => true]);
    }

    private function ensureEditorRole(): Role
    {
        return Role::firstOrCreate(['name' => 'editor'], ['is_system' => false]);
    }
}
