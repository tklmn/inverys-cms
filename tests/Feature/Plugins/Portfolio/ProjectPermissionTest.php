<?php

namespace Tests\Feature\Plugins\Portfolio;

use App\Models\Plugin;
use App\Services\AdminSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Portfolio\Models\Project;
use Plugins\Portfolio\PortfolioServiceProvider;
use Plugins\Portfolio\Services\Installer;
use Tests\TestCase;

/**
 * The portfolio plugin defines show-/edit-/delete-portfolio-project and gives
 * them to the admin role only. A backend user without them (e.g. an editor)
 * must not list, change, delete or re-import projects.
 */
class ProjectPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A separate plugin, not part of the CMS: runs where it is installed
        $path = collect([base_path('plugins/portfolio'), base_path('vendor/inverys/portfolio')])
            ->first(fn (string $dir) => file_exists($dir.'/plugin.json'));
        if ($path === null) {
            $this->markTestSkipped('The portfolio plugin is not installed.');
        }
        spl_autoload_register(function (string $class) use ($path) {
            $prefix = 'Plugins\\Portfolio\\';
            if (str_starts_with($class, $prefix) && is_file($file = $path.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        });

        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        $plugin = Plugin::create([
            'slug' => 'portfolio',
            'name' => 'Portfolio Sections',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => false,
        ]);

        $manifest = json_decode(file_get_contents($path.'/plugin.json'), true);
        $manifest['path'] = $path;

        $provider = new PortfolioServiceProvider($this->app, $plugin, $manifest);
        $provider->register();
        $provider->boot();
        Installer::createProjectsTable();
        $provider->syncPermissions();

        $this->app->make('router')->getRoutes()->refreshNameLookups();
        $this->app->make('router')->getRoutes()->refreshActionLookups();
    }

    private function project(): Project
    {
        return Project::create([
            'source' => 'manual',
            'slug' => 'hidden-project',
            'name' => 'Hidden project',
            'url' => 'https://example.com/repo',
            'is_visible' => false,
            'status' => Project::STATUS_MANUAL,
        ]);
    }

    public function test_backend_user_without_project_permissions_is_refused(): void
    {
        $project = $this->project();
        $editor = $this->createEditor();
        $this->assertTrue($editor->hasPermission('access-dashboard'));

        $this->actingAs($editor)->get(route('backend.portfolio.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.portfolio.edit', $project))->assertForbidden();
        $this->actingAs($editor)->put(route('backend.portfolio.update', $project), [
            'name' => 'Changed',
            'url' => 'https://attacker.example/',
            'is_visible' => '1',
        ])->assertForbidden();
        $this->actingAs($editor)->delete(route('backend.portfolio.destroy', $project))->assertForbidden();
        $this->actingAs($editor)->post(route('backend.portfolio.sync'))->assertForbidden();

        $project->refresh();
        $this->assertSame('Hidden project', $project->name);
        $this->assertSame('https://example.com/repo', $project->url);
        $this->assertFalse($project->is_visible);
        $this->assertFalse($project->trashed());
    }

    public function test_quick_search_hides_projects_from_users_without_permission(): void
    {
        $this->project();

        $this->assertFalse(AdminSearch::mayOpen('backend.portfolio.edit', $this->createEditor()));
        $this->assertTrue(AdminSearch::mayOpen('backend.portfolio.edit', $this->createAdmin()));
    }

    public function test_admin_keeps_full_access(): void
    {
        $project = $this->project();
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('backend.portfolio.index'))->assertOk();
        $this->actingAs($admin)->get(route('backend.portfolio.edit', $project))->assertOk();
        $this->actingAs($admin)->put(route('backend.portfolio.update', $project), [
            'name' => 'Renamed',
            'slug' => '',
            'is_visible' => '1',
        ])->assertRedirect(route('backend.portfolio.edit', $project));
        $this->assertSame('Renamed', $project->fresh()->name);

        $this->actingAs($admin)->delete(route('backend.portfolio.destroy', $project))
            ->assertRedirect(route('backend.portfolio.index'));
        $this->assertSoftDeleted('portfolio_projects', ['id' => $project->id]);
    }
}
