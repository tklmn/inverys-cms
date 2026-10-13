<?php

namespace Tests\Feature\Security;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_has_all_permissions(): void
    {
        $admin = $this->createAdmin();

        $this->assertTrue($admin->hasPermission('access-dashboard'));
        $this->assertTrue($admin->hasPermission('show-page'));
        $this->assertTrue($admin->hasPermission('create-page'));
        $this->assertTrue($admin->hasPermission('edit-page'));
        $this->assertTrue($admin->hasPermission('delete-page'));
        $this->assertTrue($admin->hasPermission('manage-plugins'));
        $this->assertTrue($admin->hasPermission('api-access'));
    }

    public function test_editor_has_content_permissions_only(): void
    {
        $editor = $this->createEditor();

        // Should have
        $this->assertTrue($editor->hasPermission('access-dashboard'));
        $this->assertTrue($editor->hasPermission('show-page'));
        $this->assertTrue($editor->hasPermission('edit-page'));
        $this->assertTrue($editor->hasPermission('show-media'));

        // Should NOT have
        $this->assertFalse($editor->hasPermission('manage-plugins'));
        $this->assertFalse($editor->hasPermission('manage-themes'));
        $this->assertFalse($editor->hasPermission('show-settings'));
        $this->assertFalse($editor->hasPermission('api-access'));
        $this->assertFalse($editor->hasPermission('delete-user'));
        // No user administration and no system logs (content manager only).
        $this->assertFalse($editor->hasPermission('show-user'));
        $this->assertFalse($editor->hasPermission('edit-user'));
        $this->assertFalse($editor->hasPermission('show-logs'));
    }

    public function test_user_role_has_no_backend_permissions(): void
    {
        $user = $this->createUser();

        $this->assertFalse($user->hasPermission('access-dashboard'));
        $this->assertFalse($user->hasPermission('show-page'));
    }

    /**
     * Comprehensive route protection test:
     * All backend routes should return 403 for a user without permissions.
     */
    public function test_all_backend_routes_require_permissions(): void
    {
        $user = $this->createUser();
        Page::factory()->create();
        Setting::create(['key' => 'site_name', 'value' => 'Test']);

        $protectedRoutes = [
            ['GET', route('dashboard')],
            ['GET', route('backend.pages.index')],
            ['GET', route('backend.pages.create')],
            ['GET', route('backend.media.index')],
            ['GET', route('backend.users.index')],
            ['GET', route('backend.roles.index')],
            ['GET', route('backend.settings.index')],
            ['GET', route('backend.plugins.index')],
        ];

        foreach ($protectedRoutes as [$method, $url]) {
            $response = $this->actingAs($user)->{strtolower($method)}($url);
            $this->assertContains(
                $response->getStatusCode(),
                [403, 302],
                "Route {$method} {$url} should be protected but returned {$response->getStatusCode()}"
            );
        }
    }
}
