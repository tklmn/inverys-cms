<?php

namespace Tests\Feature\Updates;

use App\Models\CmsNotification;
use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PackageInstaller;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Services\Updates\GitHubReleases;
use App\Services\Updates\PackageStore;
use App\Services\Updates\PackageUpdater;
use App\Services\Updates\UpdateChecker;
use App\Services\Updates\UpdateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * System → Updates: check versions, update and restore plugins/themes,
 * upgrade() of new plugin versions.
 *
 * Everything runs on a separate plugins/ folder and with faked GitHub
 * responses — never against the real folders or the network.
 */
class UpdatesTest extends TestCase
{
    use RefreshDatabase;

    private string $plugins;

    private array $zips = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        Http::preventStrayRequests();
        Cache::flush();

        $this->plugins = sys_get_temp_dir().'/inverys-upd-'.uniqid();
        File::ensureDirectoryExists($this->plugins);
        config([
            'cms.plugins_path' => $this->plugins,
            // Own empty themes folder: themes cloned locally (with an update source) must not be checked
            'cms.themes_path' => $this->plugins.'/themes',
            'cms.updates.repository' => 'acme/cms',
            'cms.updates.token' => '',
        ]);
        app(ThemeManager::class)->discover();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->plugins);
        File::deleteDirectory(storage_path('app/package-backups/plugins/upd-plugin'));
        foreach ($this->zips as $zip) {
            File::delete($zip);
        }

        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function makePlugin(string $slug, string $version, array $extra = []): string
    {
        $dir = $this->plugins.'/'.$slug;
        File::ensureDirectoryExists($dir);
        File::put($dir.'/plugin.json', json_encode([
            'slug' => $slug, 'name' => 'Upd Plugin', 'version' => $version,
            'provider' => 'Plugins\\UpdPlugin\\Provider',
        ] + $extra));
        File::put($dir.'/marker.txt', $version);
        app(PluginManager::class)->discover();

        return $dir;
    }

    /** ZIP like a GitHub source archive: everything in a wrapper folder */
    private function zipBytes(string $slug, string $version, array $extra = []): string
    {
        $path = sys_get_temp_dir().'/upd-'.uniqid().'.zip';
        $this->zips[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('acme-'.$slug.'-abc123/plugin.json', json_encode([
            'slug' => $slug, 'name' => 'Upd Plugin', 'version' => $version,
            'provider' => 'Plugins\\UpdPlugin\\Provider', 'repository' => 'acme/'.$slug,
        ] + $extra));
        $zip->addFromString('acme-'.$slug.'-abc123/marker.txt', $version);
        $zip->close();

        return (string) file_get_contents($path);
    }

    private function fakeRelease(string $repo, string $tag, ?string $zip = null, ?string $assetUrl = null): void
    {
        Http::fake([
            "api.github.com/repos/{$repo}/releases/latest" => Http::response([
                'tag_name' => $tag,
                'name' => 'Release '.$tag,
                'body' => "## Changes\n\n- <script>alert(1)</script> fixed",
                'html_url' => "https://github.com/{$repo}/releases/tag/{$tag}",
                'published_at' => '2026-10-09T10:00:00Z',
                'zipball_url' => "https://api.github.com/repos/{$repo}/zipball/{$tag}",
                'assets' => $assetUrl ? [['name' => 'pkg.zip', 'url' => $assetUrl]] : [],
            ]),
            "api.github.com/repos/{$repo}/zipball/*" => Http::response($zip ?? '', 200),
            'api.github.com/repos/acme/cms/releases/latest' => Http::response(['tag_name' => '1.0.0', 'zipball_url' => 'https://api.github.com/repos/acme/cms/zipball/1.0.0']),
        ]);
    }

    // ── Source and checks ──────────────────────────────────────────

    public function test_repository_must_be_owner_slash_repo(): void
    {
        $this->assertTrue(GitHubReleases::validRepository('tklmn/inverys-cms'));
        foreach (['', 'tklmn', '../x/y', 'a/b/c', 'https://evil.test/a', 'a/..', 'a b/c', null, 42] as $bad) {
            $this->assertFalse(GitHubReleases::validRepository($bad), var_export($bad, true));
        }
    }

    public function test_check_finds_newer_versions_and_remembers_them(): void
    {
        config(['cms.updates.repository' => 'acme/newcms']);
        $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        Http::fake([
            'api.github.com/repos/acme/newcms/releases/latest' => Http::response(['tag_name' => 'v99.1.0', 'zipball_url' => 'https://api.github.com/x']),
            'api.github.com/repos/acme/upd-plugin/releases/latest' => Http::response(['tag_name' => '1.2.0', 'zipball_url' => 'https://api.github.com/y']),
        ]);

        $checker = app(UpdateChecker::class);
        $checker->check();
        $status = collect($checker->status())->keyBy('key');

        $this->assertTrue($status['core']['available']);
        $this->assertSame('99.1.0', $status['core']['latest']);
        $this->assertTrue($status['plugin:upd-plugin']['available']);
        $this->assertSame(['upd-plugin'], array_keys($checker->availableFor('plugin')));
        $this->assertSame('1.2.0', $checker->availableFor('plugin')['upd-plugin']['latest']);
        $this->assertNotNull($checker->checkedAt());
    }

    public function test_token_is_sent_when_configured(): void
    {
        config(['cms.updates.token' => 'secret-token']);
        $this->fakeRelease('acme/cms', '1.0.0');

        app(UpdateChecker::class)->check();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_failed_source_is_reported_not_thrown(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 404)]);

        $checker = app(UpdateChecker::class);
        $checker->check();
        $core = $checker->status()[0];

        $this->assertFalse($core['available']);
        $this->assertNotNull($core['error']);
    }

    public function test_git_checkouts_composer_packages_and_sourceless_packages_are_not_updated_here(): void
    {
        $git = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        File::ensureDirectoryExists($git.'/.git');
        $this->makePlugin('plain-plugin', '1.0.0');
        app(PluginManager::class)->discover();

        $packages = collect(app(UpdateChecker::class)->packages())->keyBy('key');

        $this->assertSame('git', $packages['plugin:upd-plugin']['manager']);
        $this->assertSame('none', $packages['plugin:plain-plugin']['manager']);

        $this->expectException(UpdateException::class);
        app(PackageUpdater::class)->update('plugin', 'upd-plugin');
    }

    public function test_command_notifies_once_per_version(): void
    {
        config(['cms.updates.repository' => 'acme/newcms']);
        Http::fake(['api.github.com/repos/acme/newcms/releases/latest' => Http::response(['tag_name' => '99.0.0', 'zipball_url' => 'https://api.github.com/x'])]);

        $this->artisan('cms:check-updates')->assertSuccessful();
        $this->artisan('cms:check-updates')->assertSuccessful();

        $this->assertSame(1, CmsNotification::where('type', 'cms_update')->count());
    }

    // ── Updating and restoring ─────────────────────────────────────

    public function test_update_replaces_the_folder_and_keeps_a_backup(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $this->fakeRelease('acme/upd-plugin', 'v1.1.0', $this->zipBytes('upd-plugin', '1.1.0'));

        $result = app(PackageUpdater::class)->update('plugin', 'upd-plugin');

        $this->assertSame(['name' => 'Upd Plugin', 'from' => '1.0.0', 'to' => '1.1.0'], $result);
        $this->assertSame('1.1.0', File::get($dir.'/marker.txt'));

        $backups = app(PackageStore::class)->backups('plugin', 'upd-plugin');
        $this->assertCount(1, $backups);
        $this->assertSame('1.0.0', $backups[0]['version']);
        $this->assertSame('1.0.0', File::get($backups[0]['path'].'/marker.txt'));
    }

    public function test_restore_brings_the_previous_version_back(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        Plugin::factory()->create(['slug' => 'upd-plugin', 'version' => '1.0.0', 'is_enabled' => false]);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', $this->zipBytes('upd-plugin', '1.1.0'));

        app(PackageUpdater::class)->update('plugin', 'upd-plugin');
        app(PluginManager::class)->discover();
        $restored = app(PackageUpdater::class)->restore('plugin', 'upd-plugin');

        $this->assertSame('1.0.0', $restored['version']);
        $this->assertSame('1.0.0', File::get($dir.'/marker.txt'));
        // No upgrade() for the older version: the version is written straight to the record
        $this->assertSame('1.0.0', Plugin::where('slug', 'upd-plugin')->value('version'));
        // The replaced state is itself backed up
        $this->assertSame('1.1.0', app(PackageStore::class)->backups('plugin', 'upd-plugin')[0]['version']);
    }

    public function test_only_the_last_backups_are_kept(): void
    {
        config(['cms.updates.keep_backups' => 2]);
        $this->makePlugin('upd-plugin', '1.0.0');
        $installer = app(PackageInstaller::class);

        foreach (['1.1.0', '1.2.0', '1.3.0', '1.4.0'] as $version) {
            $zip = sys_get_temp_dir().'/upd-'.uniqid().'.zip';
            $this->zips[] = $zip;
            File::put($zip, $this->zipBytes('upd-plugin', $version));
            $this->assertTrue($installer->installFromPath($zip, 'plugin')['success']);
            usleep(5000); // Backups carry the millisecond in their name
        }

        $versions = array_column(app(PackageStore::class)->backups('plugin', 'upd-plugin'), 'version');
        $this->assertSame(['1.3.0', '1.2.0'], $versions);
    }

    public function test_upload_refuses_an_older_version(): void
    {
        $dir = $this->makePlugin('upd-plugin', '2.0.0');
        $zip = sys_get_temp_dir().'/upd-'.uniqid().'.zip';
        $this->zips[] = $zip;
        File::put($zip, $this->zipBytes('upd-plugin', '1.0.0'));

        $result = app(PackageInstaller::class)->installFromPath($zip, 'plugin');

        $this->assertFalse($result['success']);
        $this->assertSame('2.0.0', json_decode(File::get($dir.'/plugin.json'), true)['version']);
        $this->assertSame('2.0.0', File::get($dir.'/marker.txt'));
    }

    public function test_package_with_another_slug_is_refused(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', $this->zipBytes('other-plugin', '1.1.0'));

        try {
            app(PackageUpdater::class)->update('plugin', 'upd-plugin');
            $this->fail('Expected UpdateException');
        } catch (UpdateException) {
            $this->assertSame('1.0.0', File::get($dir.'/marker.txt'));
            $this->assertDirectoryDoesNotExist($this->plugins.'/other-plugin');
        }
    }

    public function test_downloads_only_come_from_github(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', null, 'https://evil.test/pkg.zip');

        try {
            app(PackageUpdater::class)->update('plugin', 'upd-plugin');
            $this->fail('Expected UpdateException');
        } catch (UpdateException) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.test'));
            $this->assertSame('1.0.0', File::get($dir.'/marker.txt'));
        }
    }

    // ── Active plugins: completion in the next request ─────────────

    public function test_active_plugin_update_is_confirmed_once_the_new_version_loaded(): void
    {
        $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        Plugin::factory()->enabled()->create(['slug' => 'upd-plugin', 'version' => '1.0.0']);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', $this->zipBytes('upd-plugin', '1.1.0'));

        app(PackageUpdater::class)->update('plugin', 'upd-plugin');
        // What the PluginManager does in the next request: load and record the new version
        Plugin::where('slug', 'upd-plugin')->update(['version' => '1.1.0']);

        $results = app(PackageStore::class)->settle();

        $this->assertSame('updated', $results[0]['status']);
        $this->assertSame([], app(PackageStore::class)->settle());
    }

    public function test_active_plugin_that_fails_after_the_update_is_rolled_back(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        Plugin::factory()->enabled()->create(['slug' => 'upd-plugin', 'version' => '1.0.0']);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', $this->zipBytes('upd-plugin', '1.1.0'));

        app(PackageUpdater::class)->update('plugin', 'upd-plugin');
        // The new version fails to load: plugin disabled, error recorded
        Plugin::where('slug', 'upd-plugin')->update(['is_enabled' => false]);
        Cache::put(PackageStore::ERROR_PREFIX.'upd-plugin', 'Boom', 60);

        $results = app(PackageStore::class)->settle();

        $this->assertSame('rolled_back', $results[0]['status']);
        $this->assertSame('Boom', $results[0]['error']);
        $this->assertSame('1.0.0', File::get($dir.'/marker.txt'));
        $plugin = Plugin::where('slug', 'upd-plugin')->first();
        $this->assertTrue((bool) $plugin->is_enabled);
        $this->assertSame('1.0.0', $plugin->version);
    }

    public function test_plugin_manager_runs_upgrade_once_for_a_new_version(): void
    {
        $this->makeProviderPlugin('upg-ok', 'UpgOk', '2.0.0', "Cache::put('upg-ok-ran', \$from.'>'.\$to, 60);");
        Plugin::factory()->enabled()->create(['slug' => 'upg-ok', 'version' => '1.0.0']);

        app(PluginManager::class)->boot();

        $this->assertSame('1.0.0>2.0.0', Cache::get('upg-ok-ran'));
        $this->assertSame('2.0.0', Plugin::where('slug', 'upg-ok')->value('version'));
    }

    public function test_plugin_manager_disables_a_plugin_whose_upgrade_fails(): void
    {
        $this->makeProviderPlugin('upg-fail', 'UpgFail', '2.0.0', "throw new \\RuntimeException('schema broke');");
        Plugin::factory()->enabled()->create(['slug' => 'upg-fail', 'version' => '1.0.0']);

        app(PluginManager::class)->boot();

        $plugin = Plugin::where('slug', 'upg-fail')->first();
        $this->assertFalse((bool) $plugin->is_enabled);
        $this->assertSame('1.0.0', $plugin->version);
        $this->assertSame('schema broke', Cache::get(PackageStore::ERROR_PREFIX.'upg-fail'));
    }

    private function makeProviderPlugin(string $slug, string $class, string $version, string $upgradeBody): void
    {
        $dir = $this->plugins.'/'.$slug;
        File::ensureDirectoryExists($dir.'/src');
        File::put($dir.'/plugin.json', json_encode([
            'slug' => $slug, 'name' => $slug, 'version' => $version,
            'provider' => "Plugins\\{$class}\\{$class}ServiceProvider",
        ]));
        File::put($dir."/src/{$class}ServiceProvider.php", <<<PHP
        <?php
        namespace Plugins\\{$class};

        use Illuminate\\Support\\Facades\\Cache;

        class {$class}ServiceProvider extends \\App\\Services\\Plugins\\PluginServiceProvider
        {
            public function upgrade(string \$from, string \$to): void
            {
                {$upgradeBody}
            }
        }
        PHP);
    }

    // ── Interface and permissions ──────────────────────────────────

    public function test_admin_sees_the_update_page_with_escaped_release_notes(): void
    {
        config(['cms.updates.repository' => 'acme/newcms']);
        Http::fake(['api.github.com/repos/acme/newcms/releases/latest' => Http::response([
            'tag_name' => '99.0.0', 'name' => 'Big one', 'body' => "- <script>alert(1)</script> fixed\n- [click](javascript:alert(1))",
            'html_url' => 'https://github.com/acme/newcms/releases/tag/99.0.0', 'zipball_url' => 'https://api.github.com/x',
        ])]);
        app(UpdateChecker::class)->check();

        $response = $this->actingAs($this->createAdmin())->get(route('backend.updates.index'));

        $response->assertOk()
            ->assertSee('99.0.0')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('click');
    }

    public function test_editor_has_no_access(): void
    {
        $this->actingAs($this->createEditor())->get(route('backend.updates.index'))->assertForbidden();
        $this->actingAs($this->createEditor())->post(route('backend.plugins.update', 'upd-plugin'))->assertForbidden();
        $this->actingAs($this->createEditor())->post(route('backend.themes.update', 'upd-theme'))->assertForbidden();
    }

    public function test_installing_needs_code_editing_and_a_fresh_password(): void
    {
        $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $admin = $this->createAdmin();

        config(['cms.code_editing' => false]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('backend.plugins.update', 'upd-plugin'))
            ->assertForbidden();

        config(['cms.code_editing' => true]);
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => 0])
            ->post(route('backend.plugins.restore', 'upd-plugin'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_uploading_a_newer_plugin_keeps_its_record(): void
    {
        $this->makePlugin('upd-plugin', '1.0.0');
        Plugin::factory()->enabled()->create(['slug' => 'upd-plugin', 'version' => '1.0.0']);
        $zip = sys_get_temp_dir().'/upd-'.uniqid().'.zip';
        $this->zips[] = $zip;
        File::put($zip, $this->zipBytes('upd-plugin', '1.1.0'));

        $this->actingAs($this->createAdmin())
            ->post(route('backend.plugins.upload'), ['package' => new UploadedFile($zip, 'pkg.zip', 'application/zip', null, true)])
            ->assertRedirect(route('backend.plugins.index'));

        // The upload keeps the record — the plugin stays enabled
        $plugin = Plugin::where('slug', 'upd-plugin')->first();
        $this->assertNotNull($plugin);
        $this->assertTrue((bool) $plugin->is_enabled);
    }

    public function test_uploading_a_newer_theme_keeps_its_settings(): void
    {
        $themes = sys_get_temp_dir().'/inverys-upd-themes-'.uniqid();
        File::ensureDirectoryExists($themes.'/upd-theme');
        File::put($themes.'/upd-theme/theme.json', json_encode(['slug' => 'upd-theme', 'name' => 'Upd', 'version' => '1.0.0']));
        config(['cms.themes_path' => $themes]);
        Setting::create(['key' => 'theme.upd-theme.primary_color', 'value' => '#123456']);

        $zip = sys_get_temp_dir().'/upd-'.uniqid().'.zip';
        $this->zips[] = $zip;
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        $archive->addFromString('upd-theme/theme.json', json_encode(['slug' => 'upd-theme', 'name' => 'Upd', 'version' => '1.1.0']));
        $archive->close();

        try {
            $this->actingAs($this->createAdmin())
                ->post(route('backend.themes.upload'), ['package' => new UploadedFile($zip, 'pkg.zip', 'application/zip', null, true)])
                ->assertSessionHas('success');

            $this->assertSame('#123456', Setting::where('key', 'theme.upd-theme.primary_color')->value('value'));
            $this->assertSame('1.1.0', json_decode(File::get($themes.'/upd-theme/theme.json'), true)['version']);
        } finally {
            File::deleteDirectory($themes);
            File::deleteDirectory(storage_path('app/package-backups/themes/upd-theme'));
        }
    }

    public function test_update_from_the_plugin_list(): void
    {
        $dir = $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $this->fakeRelease('acme/upd-plugin', '1.1.0', $this->zipBytes('upd-plugin', '1.1.0'));
        app(UpdateChecker::class)->check();
        $admin = $this->createAdmin();

        // The button only appears because there is a newer version
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))
            ->assertOk()
            ->assertSee(route('backend.plugins.update', 'upd-plugin'))
            ->assertDontSee(route('backend.plugins.restore', 'upd-plugin'));

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('backend.plugins.update', 'upd-plugin'))
            ->assertRedirect(route('backend.plugins.index'))
            ->assertSessionHas('success');

        $this->assertSame('1.1.0', File::get($dir.'/marker.txt'));

        // Afterwards: no more update, but the way back
        app(PluginManager::class)->discover();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))
            ->assertDontSee(route('backend.plugins.update', 'upd-plugin'))
            ->assertSee(route('backend.plugins.restore', 'upd-plugin'));
    }

    public function test_plugin_list_shows_no_update_button_without_newer_version(): void
    {
        $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);
        $this->fakeRelease('acme/upd-plugin', '1.0.0');
        app(UpdateChecker::class)->check();

        $this->actingAs($this->createAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))
            ->assertOk()
            ->assertDontSee(route('backend.plugins.update', 'upd-plugin'));
    }

    public function test_theme_list_offers_the_update_of_a_theme(): void
    {
        $themes = sys_get_temp_dir().'/inverys-upd-themes-'.uniqid();
        File::ensureDirectoryExists($themes.'/upd-theme');
        File::put($themes.'/upd-theme/theme.json', json_encode(['slug' => 'upd-theme', 'name' => 'Upd', 'version' => '1.0.0', 'repository' => 'acme/upd-theme']));
        config(['cms.themes_path' => $themes]);
        app(ThemeManager::class)->discover();
        Http::fake([
            'api.github.com/repos/acme/upd-theme/releases/latest' => Http::response(['tag_name' => '1.1.0', 'zipball_url' => 'https://api.github.com/repos/acme/upd-theme/zipball/1.1.0']),
            'api.github.com/repos/acme/cms/releases/latest' => Http::response(['tag_name' => '1.0.0', 'zipball_url' => 'https://api.github.com/x']),
        ]);
        app(UpdateChecker::class)->check();

        try {
            $this->actingAs($this->createAdmin())
                ->get(route('backend.themes.index'))
                ->assertOk()
                ->assertSee(route('backend.themes.update', 'upd-theme'));
        } finally {
            File::deleteDirectory($themes);
        }
    }

    public function test_updates_page_shows_only_the_cms(): void
    {
        $this->makePlugin('upd-plugin', '1.0.0', ['repository' => 'acme/upd-plugin']);

        $this->actingAs($this->createAdmin())
            ->get(route('backend.updates.index'))
            ->assertOk()
            ->assertSee('Inverys CMS')
            ->assertDontSee('Upd Plugin');
    }
}
