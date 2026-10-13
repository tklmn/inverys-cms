<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One-off, purely additive grant of the content delete permissions to the
 * existing editor role (2026_09_28_120000).
 */
class EditorContentPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_28_120000_grant_editor_content_delete_permissions.php');
        $migration->up();
    }

    /** @param  list<string>  $names */
    private function permissions(array $names): array
    {
        return array_map(fn ($name) => Permission::firstOrCreate(['name' => $name])->id, $names);
    }

    /** @return list<string> */
    private function editorPermissions(): array
    {
        return Role::where('name', 'editor')->first()->permissions()->pluck('name')->sort()->values()->all();
    }

    public function test_grants_delete_rights_where_the_editor_can_edit(): void
    {
        $editor = Role::firstOrCreate(['name' => 'editor'], ['is_system' => false]);
        $this->permissions(['delete-post', 'delete-form', 'delete-gallery-album', 'delete-blog-category']);
        $editor->permissions()->attach($this->permissions(['edit-post', 'edit-gallery-album', 'delete-gallery-album', 'blog-settings']));
        // edit-form is missing: the admin has revoked forms from the editor
        $this->permissions(['edit-form', 'edit-blog-category']);

        $this->runMigration();

        $this->assertSame(
            ['blog-settings', 'delete-gallery-album', 'delete-post', 'edit-gallery-album', 'edit-post'],
            $this->editorPermissions(),
        );

        // A second run changes nothing (no duplicates)
        $this->runMigration();
        $this->assertCount(5, $this->editorPermissions());
    }

    public function test_plugin_settings_are_revoked_from_the_editor(): void
    {
        // 2026_09_28_130000: editors manage content, not plugin settings
        $editor = Role::firstOrCreate(['name' => 'editor'], ['is_system' => false]);
        $admin = Role::firstOrCreate(['name' => 'admin'], ['is_system' => true]);
        $ids = $this->permissions(['blog-settings', 'edit-post']);
        $editor->permissions()->attach($ids);
        $admin->permissions()->attach($ids);

        $migration = require database_path('migrations/2026_09_28_130000_revoke_plugin_settings_from_editor.php');
        $migration->up();

        $this->assertSame(['edit-post'], $this->editorPermissions());
        $this->assertCount(2, $admin->permissions()->get());
    }

    public function test_does_nothing_without_editor_role_or_permissions(): void
    {
        $this->runMigration();
        $this->assertNull(Role::where('name', 'editor')->first());

        // Role without plugin permissions (plugins never installed)
        Role::create(['name' => 'editor', 'is_system' => false]);
        $this->runMigration();
        $this->assertSame([], $this->editorPermissions());
    }
}
