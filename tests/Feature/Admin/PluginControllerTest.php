<?php

namespace Tests\Feature\Admin;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_plugins(): void
    {
        $admin = $this->createAdmin();

        // Plugin management requires a freshly confirmed password
        $this->actingAs($admin)
            ->get(route('backend.plugins.index'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))
            ->assertOk();
    }

    public function test_system_plugin_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $plugin = Plugin::factory()->system()->enabled()->create(['slug' => 'core-plugin']);

        $this->actingAs($admin)
            ->delete(route('backend.plugins.destroy', $plugin->slug))
            ->assertRedirect();

        // System plugin should still exist
        $this->assertDatabaseHas('plugins', ['slug' => 'core-plugin']);
    }

    /**
     * Separate plugins/ directory for deletion tests — never the real one, in case
     * a check fails.
     */
    private function fakePluginsPath(): string
    {
        $path = sys_get_temp_dir().'/inverys-plugins-'.uniqid();
        foreach (['core-thing' => true, 'uploaded-thing' => false] as $slug => $system) {
            File::ensureDirectoryExists($path.'/'.$slug);
            File::put($path.'/'.$slug.'/plugin.json', json_encode([
                'slug' => $slug, 'name' => $slug, 'version' => '1.0.0', 'is_system' => $system,
            ]));
        }

        config(['cms.plugins_path' => $path]);
        app(PluginManager::class)->discover();

        return $path;
    }

    public function test_dot_slug_cannot_wipe_the_plugins_directory(): void
    {
        $path = $this->fakePluginsPath();
        $admin = $this->createAdmin();

        try {
            foreach (['/backend/plugins/%2e', '/backend/plugins/.'] as $url) {
                $this->actingAs($admin)->delete($url)->assertSessionHasErrors('slug');
            }

            $this->assertFileExists($path.'/core-thing/plugin.json');
            $this->assertFileExists($path.'/uploaded-thing/plugin.json');
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_known_plugin_is_deleted_only_inside_its_own_directory(): void
    {
        $path = $this->fakePluginsPath();
        $admin = $this->createAdmin();

        try {
            $this->actingAs($admin)
                ->delete(route('backend.plugins.destroy', 'uploaded-thing'))
                ->assertSessionHasNoErrors();

            $this->assertDirectoryDoesNotExist($path.'/uploaded-thing');
            $this->assertFileExists($path.'/core-thing/plugin.json');
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_plugin_delete_respects_the_code_editing_lock(): void
    {
        $path = $this->fakePluginsPath();
        config(['cms.code_editing' => false]);

        try {
            $this->actingAs($this->createAdmin())
                ->delete(route('backend.plugins.destroy', 'uploaded-thing'))
                ->assertForbidden();

            $this->assertFileExists($path.'/uploaded-thing/plugin.json');
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_editor_cannot_manage_plugins(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.plugins.index'))
            ->assertForbidden();
    }
}
