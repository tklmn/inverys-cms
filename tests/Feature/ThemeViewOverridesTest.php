<?php

namespace Tests\Feature;

use App\Http\Middleware\ApplyVisitorTheme;
use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Support\ThemeViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every theme — the default theme included, and a visitor's own theme —
 * overrides plugin views in themes/{theme}/views/plugins/{namespace}/.
 */
class ThemeViewOverridesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        $this->root = sys_get_temp_dir().'/theme-views-'.uniqid();
        $this->writeView('themes/default/views/plugins/demo-ns/hello.blade.php', 'default override');
        $this->writeView('themes/other/views/plugins/demo-ns/hello.blade.php', 'other override');
        $this->writeView('themes/other/views/page.blade.php', 'other page');
        $this->writeView('themes/default/views/page.blade.php', 'default page');
        $this->writeView('themes/plain/views/page.blade.php', 'plain page');
        foreach (['default', 'other', 'plain'] as $slug) {
            File::put("{$this->root}/themes/{$slug}/theme.json", json_encode(['slug' => $slug, 'name' => ucfirst($slug)]));
        }
        $this->writeView('plugin/views/hello.blade.php', 'original');
        $this->writeView('plugin/views/only.blade.php', 'only in the plugin');
        $this->writeView('extension/views/hello.blade.php', 'extension');

        config(['cms.themes_path' => $this->root.'/themes']);
        Setting::updateOrCreate(['key' => 'active_theme'], ['value' => 'default']);

        // Fresh state: the app booted with the real themes folder
        $this->app->instance(ThemeViews::class, new ThemeViews);
        app(ThemeManager::class)->boot();
        app(ThemeViews::class)->register('demo-ns', $this->root.'/plugin/views');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function writeView(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname("{$this->root}/{$path}"));
        File::put("{$this->root}/{$path}", $content);
    }

    private function render(string $view): string
    {
        return trim(view($view)->render());
    }

    public function test_the_default_theme_overrides_plugin_views(): void
    {
        $this->assertSame('default override', $this->render('demo-ns::hello'));
        $this->assertSame('only in the plugin', $this->render('demo-ns::only'));
    }

    public function test_switching_the_theme_switches_the_overrides(): void
    {
        $this->render('demo-ns::hello');

        app(ThemeViews::class)->useTheme('other');
        $this->assertSame('other override', $this->render('demo-ns::hello'));
        $this->assertSame('other page', $this->render('theme::page'));

        // A theme without overrides shows the plugin's own view
        app(ThemeViews::class)->useTheme('plain');
        $this->assertSame('original', $this->render('demo-ns::hello'));
    }

    public function test_a_theme_override_wins_over_views_from_another_plugin(): void
    {
        app(ThemeViews::class)->prepend('demo-ns', $this->root.'/extension/views');
        $this->assertSame('default override', $this->render('demo-ns::hello'));

        app(ThemeViews::class)->useTheme('plain');
        $this->assertSame('extension', $this->render('demo-ns::hello'));
    }

    /** The web middleware that switches to a visitor's own theme, on a request to $path */
    private function throughMiddleware(string $path): string
    {
        $this->app->instance('request', Request::create($path));

        return (new ApplyVisitorTheme)->handle(request(), fn () => response(view('demo-ns::hello')->render()))->getContent();
    }

    public function test_a_visitors_own_theme_applies_to_plugin_views_on_the_website(): void
    {
        $this->assertStringContainsString('default override', $this->throughMiddleware('/about'));

        $user = $this->createAdmin();
        $user->setSetting('frontend_theme', 'other');
        $this->actingAs($user);
        $this->assertStringContainsString('other override', $this->throughMiddleware('/about'));

        // The admin area keeps the globally active theme
        $this->assertStringContainsString('default override', $this->throughMiddleware('/backend/pages'));
    }

    public function test_the_web_group_applies_the_visitors_theme(): void
    {
        $this->assertContains(ApplyVisitorTheme::class, app('router')->getMiddlewareGroups()['web']);
    }

    public function test_bundled_plugins_register_their_views_for_overrides(): void
    {
        foreach (['blog', 'cookie-consent', 'captcha', 'multilingual', 'mail-providers', 'gallery', 'form-builder', 'activity-log'] as $slug) {
            Plugin::create(['slug' => $slug, 'name' => $slug, 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
        }
        $plugins = app(PluginManager::class);
        $plugins->discover();
        $plugins->boot();

        foreach (['blog', 'blog-admin', 'cookie-consent', 'captcha', 'multilingual', 'mail-providers', 'gallery-admin', 'form-builder-admin', 'activity-log-admin'] as $namespace) {
            $this->assertNotSame([], app(ThemeViews::class)->paths($namespace), $namespace);
        }
    }
}
