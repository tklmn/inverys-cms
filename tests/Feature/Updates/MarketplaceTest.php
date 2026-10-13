<?php

namespace Tests\Feature\Updates;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\User;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Services\Updates\Marketplace;
use App\Support\RemoteFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * Extensions → Themes / Plugins → Marketplace: catalogs as sources,
 * installing and updating from them, who may do what, and the protection of
 * the server against foreign addresses.
 *
 * Runs on separate themes/ and plugins/ folders with faked responses and a
 * faked DNS — never against the real folders or the network.
 */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOG = 'https://market.example/catalog.json';

    /** A 1x1 PNG as preview image */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $root;

    /** host → address the faked DNS answers with */
    private array $dns = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        Http::preventStrayRequests();

        $this->root = sys_get_temp_dir().'/inverys-market-'.uniqid();
        File::ensureDirectoryExists($this->root.'/themes');
        File::ensureDirectoryExists($this->root.'/plugins');
        config([
            'cms.themes_path' => $this->root.'/themes',
            'cms.plugins_path' => $this->root.'/plugins',
            'cms.marketplace.sources' => self::CATALOG,
            'cms.marketplace.allow_private_hosts' => false,
            'cms.marketplace.screenshots' => $this->root.'/screenshots',
            'cms.version' => '1.4.0',
        ]);
        $this->app->instance(RemoteFile::class, new RemoteFile(fn (string $host) => [$this->dns[$host] ?? '93.184.215.14']));
        app(ThemeManager::class)->discover();
        app(PluginManager::class)->discover();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** ZIP with the package in a folder, like a release asset */
    private function zip(string $manifestFile, array $manifest): string
    {
        $path = $this->root.'/pkg-'.uniqid().'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString($manifest['slug'].'/'.$manifestFile, json_encode($manifest));
        $zip->addFromString($manifest['slug'].'/views/page.blade.php', 'page '.$manifest['version']);
        $zip->close();

        return (string) file_get_contents($path);
    }

    private function ember(string $version = '1.0.0', array $extra = []): array
    {
        $zip = $this->zip('theme.json', ['slug' => 'ember', 'name' => 'Ember', 'version' => $version]);

        return [
            'entry' => $extra + [
                'type' => 'theme', 'slug' => 'ember', 'name' => 'Ember', 'version' => $version,
                'description' => 'Dark <b>theme</b>', 'author' => 'Acme',
                'download' => "https://files.example/ember-{$version}.zip", 'sha256' => hash('sha256', $zip),
                'screenshot' => 'https://files.example/ember.png', 'homepage' => 'https://example.org/ember',
            ],
            'zip' => $zip,
        ];
    }

    /** Fake the catalog and the files it names */
    private function fakeMarket(array $packages, string $catalog = self::CATALOG, string $name = 'Acme Market'): void
    {
        $fakes = [$catalog => Http::response(['schema' => 1, 'name' => $name, 'packages' => array_column($packages, 'entry')])];
        foreach ($packages as $package) {
            if (isset($package['zip'])) {
                $fakes[$package['entry']['download']] = Http::response($package['zip']);
            }
        }
        $fakes['files.example/*.png'] = Http::response(base64_decode(self::PNG));

        Http::fake($fakes);
    }

    private function withPermissions(array $permissions, bool $admin = false): User
    {
        $user = $admin ? $this->createAdmin() : User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'moderator'], ['is_system' => false]);
        // The backend itself needs access-dashboard
        $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', ['access-dashboard', ...$permissions])->pluck('id'));
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->clearRoleCache();

        return $user;
    }

    private function confirmed(User $user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    }

    // ── Catalogs ───────────────────────────────────────────────────

    public function test_catalog_lists_valid_packages_of_the_type(): void
    {
        $ember = $this->ember();
        $this->fakeMarket([
            $ember,
            ['entry' => ['type' => 'plugin', 'slug' => 'bar', 'name' => 'Bar', 'version' => '1.0.0', 'download' => 'https://files.example/bar.zip', 'sha256' => str_repeat('a', 64)]],
            ['entry' => ['type' => 'theme', 'slug' => '../evil', 'name' => 'Evil', 'version' => '1.0.0', 'download' => 'https://files.example/e.zip', 'sha256' => str_repeat('a', 64)]],
            ['entry' => ['type' => 'theme', 'slug' => 'plain', 'name' => 'Plain', 'version' => '1.0.0', 'download' => 'http://files.example/p.zip', 'sha256' => str_repeat('a', 64)]],
            ['entry' => ['type' => 'theme', 'slug' => 'nosum', 'name' => 'No sum', 'version' => '1.0.0', 'download' => 'https://files.example/n.zip']],
        ]);

        $catalog = app(Marketplace::class)->catalog('theme');

        $this->assertSame([], $catalog['errors']);
        $this->assertSame(['ember'], array_column($catalog['items'], 'slug'));
        $item = $catalog['items'][0];
        $this->assertSame('Dark theme', $item['description'], 'Markup from the catalog is stripped');
        $this->assertSame('Acme Market', $item['source']);
        $this->assertFileExists(Marketplace::screenshotDir().'/'.$item['screenshot']);
        $this->assertSame(['bar'], array_column(app(Marketplace::class)->catalog('plugin')['items'], 'slug'));
    }

    public function test_a_file_that_is_no_catalog_is_reported(): void
    {
        Http::fake([self::CATALOG => Http::response(['packages' => []])]);

        $catalog = app(Marketplace::class)->catalog('theme');

        $this->assertSame([], $catalog['items']);
        $this->assertArrayHasKey(self::CATALOG, $catalog['errors']);
    }

    public function test_svg_preview_is_not_stored(): void
    {
        $ember = $this->ember(extra: ['screenshot' => 'https://files.example/shot.svg']);
        Http::fake([
            self::CATALOG => Http::response(['schema' => 1, 'packages' => [$ember['entry']]]),
            'files.example/shot.svg' => Http::response('<svg onload="alert(1)"></svg>'),
        ]);

        $this->assertNull(app(Marketplace::class)->catalog('theme')['items'][0]['screenshot']);
    }

    public function test_the_first_source_wins_for_the_same_slug(): void
    {
        config(['cms.marketplace.sources' => self::CATALOG.', https://other.example/m.json']);
        $first = $this->ember('1.0.0');
        $second = $this->ember('9.0.0', ['download' => 'https://files.example/other.zip']);
        Http::fake([
            self::CATALOG => Http::response(['schema' => 1, 'name' => 'First', 'packages' => [$first['entry']]]),
            'https://other.example/m.json' => Http::response(['schema' => 1, 'name' => 'Second', 'packages' => [$second['entry']]]),
            'files.example/*.png' => Http::response(base64_decode(self::PNG)),
        ]);

        $items = app(Marketplace::class)->catalog('theme')['items'];

        $this->assertCount(1, $items);
        $this->assertSame(['First', '1.0.0'], [$items[0]['source'], $items[0]['version']]);
    }

    public function test_catalog_is_cached_and_a_failed_refresh_keeps_the_last_list(): void
    {
        $this->fakeMarket([$this->ember()]);
        app(Marketplace::class)->catalog('theme');

        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 503)]);

        $this->assertCount(1, app(Marketplace::class)->catalog('theme')['items'], 'served from the cache');
        $refreshed = app(Marketplace::class)->catalog('theme', true);
        $this->assertCount(1, $refreshed['items']);
        $this->assertNotSame([], $refreshed['errors']);
    }

    // ── Protection of the server ───────────────────────────────────

    public function test_addresses_in_private_networks_are_refused(): void
    {
        $this->dns['market.example'] = '10.0.0.5';
        Http::fake();

        $catalog = app(Marketplace::class)->catalog('theme');

        $this->assertSame(__('translation.marketplace.error_private', ['host' => 'market.example']), $catalog['errors'][self::CATALOG] ?? null);
        Http::assertNothingSent();

        foreach (['127.0.0.1', '169.254.169.254', '192.168.1.10', '100.64.0.1', '::1', 'fd00::1', '::ffff:10.0.0.1'] as $ip) {
            $this->assertFalse(RemoteFile::isPublicIp($ip), $ip);
        }
        $this->assertTrue(RemoteFile::isPublicIp('93.184.215.14'));
    }

    public function test_a_redirect_into_a_private_network_is_refused(): void
    {
        $this->dns['internal.example'] = '192.168.0.2';
        Http::fake([
            self::CATALOG => Http::response('', 302, ['Location' => 'https://internal.example/secret.json']),
            'internal.example/*' => Http::response(['schema' => 1, 'packages' => []]),
        ]);

        $this->assertNotSame([], app(Marketplace::class)->catalog('theme')['errors']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'internal.example'));
    }

    public function test_private_hosts_can_be_allowed_for_a_company_marketplace(): void
    {
        config(['cms.marketplace.allow_private_hosts' => true]);
        $this->dns['market.example'] = '10.0.0.5';
        $this->dns['files.example'] = '10.0.0.6';
        $this->fakeMarket([$this->ember()]);

        $this->assertCount(1, app(Marketplace::class)->catalog('theme')['items']);
    }

    public function test_only_https_addresses_without_credentials_are_sources(): void
    {
        $this->assertSame(
            ['https://a.example/m.json', 'https://b.example/x'],
            Marketplace::parseSources("https://a.example/m.json\nhttp://plain.example/m.json, https://user:pw@c.example/m.json https://b.example/x ftp://d.example"),
        );
        $this->assertFalse(RemoteFile::validUrl('https://a.example:8443/m.json'));
    }

    // ── Install and update ─────────────────────────────────────────

    public function test_admin_installs_a_theme_and_can_activate_it(): void
    {
        $this->fakeMarket([$this->ember()]);

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => 'ember'])
            ->assertRedirect(route('backend.themes.marketplace'))
            ->assertSessionHas('success');

        $this->assertFileExists($this->root.'/themes/ember/theme.json');
        app(ThemeManager::class)->discover();
        $item = app(Marketplace::class)->withState('theme', app(Marketplace::class)->catalog('theme')['items'])[0];
        $this->assertSame(['installed', false], [$item['state'], $item['active']]);
    }

    public function test_a_download_that_does_not_match_the_checksum_is_not_installed(): void
    {
        $ember = $this->ember();
        $ember['entry']['sha256'] = str_repeat('0', 64);
        $this->fakeMarket([$ember]);

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => 'ember'])
            ->assertSessionHasErrors('slug');

        $this->assertDirectoryDoesNotExist($this->root.'/themes/ember');
    }

    public function test_only_packages_from_a_catalog_can_be_installed(): void
    {
        $this->fakeMarket([$this->ember()]);

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => 'backdoor'])
            ->assertSessionHasErrors('slug');
        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => '../x'])
            ->assertSessionHasErrors('slug');
    }

    public function test_an_installed_package_is_not_installed_again(): void
    {
        $this->fakeMarket([$this->ember()]);
        File::ensureDirectoryExists($this->root.'/themes/ember');
        File::put($this->root.'/themes/ember/theme.json', json_encode(['slug' => 'ember', 'name' => 'Ember', 'version' => '1.0.0']));
        File::put($this->root.'/themes/ember/marker.txt', 'local');
        app(ThemeManager::class)->discover();

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => 'ember'])
            ->assertSessionHasErrors('slug');

        $this->assertFileExists($this->root.'/themes/ember/marker.txt');
    }

    public function test_an_installed_package_is_updated_to_the_catalog_version(): void
    {
        File::ensureDirectoryExists($this->root.'/themes/ember/views');
        File::put($this->root.'/themes/ember/theme.json', json_encode(['slug' => 'ember', 'name' => 'Ember', 'version' => '1.0.0']));
        app(ThemeManager::class)->discover();
        $this->fakeMarket([$this->ember('1.1.0')]);

        $this->assertSame('update', app(Marketplace::class)->withState('theme', app(Marketplace::class)->catalog('theme')['items'])[0]['state']);

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.update'), ['slug' => 'ember'])
            ->assertSessionHas('success');

        $this->assertSame('1.1.0', json_decode(File::get($this->root.'/themes/ember/theme.json'), true)['version']);
        File::deleteDirectory(storage_path('app/package-backups/themes/ember'));
    }

    public function test_a_package_for_another_cms_version_cannot_be_installed(): void
    {
        $this->fakeMarket([$this->ember(extra: ['requires' => '^2.0'])]);

        $item = app(Marketplace::class)->withState('theme', app(Marketplace::class)->catalog('theme')['items'])[0];
        $this->assertSame('incompatible', $item['state']);

        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.install'), ['slug' => 'ember'])
            ->assertSessionHasErrors('slug');
        $this->assertDirectoryDoesNotExist($this->root.'/themes/ember');
    }

    public function test_requires_is_checked_against_the_cms_version(): void
    {
        // cms.version is 1.4.0 here
        foreach (['' => true, '^1.0' => true, '1.4' => true, '^1.5' => false, '^2.0' => false, '>=1.2' => true, '>=2' => false, 'any' => false] as $requires => $ok) {
            $this->assertSame($ok, Marketplace::compatible((string) $requires), (string) $requires);
        }
    }

    public function test_a_plugin_installed_by_an_admin_lands_in_the_plugin_list(): void
    {
        $manifest = ['slug' => 'bar', 'name' => 'Bar', 'version' => '1.2.0', 'provider' => 'Plugins\\Bar\\BarServiceProvider'];
        $zip = $this->zip('plugin.json', $manifest);
        $this->fakeMarket([['entry' => ['type' => 'plugin', 'slug' => 'bar', 'name' => 'Bar', 'version' => '1.2.0', 'download' => 'https://files.example/bar.zip', 'sha256' => hash('sha256', $zip)], 'zip' => $zip]]);

        $this->confirmed($this->createAdmin())->post(route('backend.plugins.marketplace.install'), ['slug' => 'bar'])
            ->assertRedirect(route('backend.plugins.index'));

        $this->assertFileExists($this->root.'/plugins/bar/plugin.json');
        $this->assertFalse((bool) Plugin::where('slug', 'bar')->value('is_enabled'));
    }

    // ── Permissions ────────────────────────────────────────────────

    public function test_a_moderator_with_the_install_permission_installs_themes(): void
    {
        $this->fakeMarket([$this->ember()]);
        $moderator = $this->withPermissions(['install-themes']);

        $this->confirmed($moderator)->get(route('backend.themes.marketplace'))->assertOk()->assertSee('Ember');
        $this->confirmed($moderator)->post(route('backend.themes.marketplace.install'), ['slug' => 'ember'])
            ->assertSessionHas('success');
        $this->assertFileExists($this->root.'/themes/ember/theme.json');

        // Without manage-themes: no list of installed themes, no sources
        $this->confirmed($moderator)->get(route('backend.themes.index'))->assertForbidden();
        $this->confirmed($moderator)->post(route('backend.themes.marketplace.sources'), ['sources' => 'https://x.example/m.json'])->assertForbidden();
    }

    public function test_a_moderator_installing_a_plugin_stays_in_the_marketplace(): void
    {
        $manifest = ['slug' => 'bar', 'name' => 'Bar', 'version' => '1.0.0', 'provider' => 'Plugins\\Bar\\BarServiceProvider'];
        $zip = $this->zip('plugin.json', $manifest);
        $this->fakeMarket([['entry' => ['type' => 'plugin', 'slug' => 'bar', 'name' => 'Bar', 'version' => '1.0.0', 'download' => 'https://files.example/bar.zip', 'sha256' => hash('sha256', $zip)], 'zip' => $zip]]);

        $this->confirmed($this->withPermissions(['install-plugins']))->post(route('backend.plugins.marketplace.install'), ['slug' => 'bar'])
            ->assertRedirect(route('backend.plugins.marketplace'))
            ->assertSessionHas('success', __('translation.marketplace.installed_plugin_admin', ['name' => 'Bar', 'version' => '1.0.0']));
    }

    public function test_installing_needs_the_permission_code_changes_and_a_confirmed_password(): void
    {
        $this->fakeMarket([$this->ember()]);
        $install = fn ($as) => $as->post(route('backend.themes.marketplace.install'), ['slug' => 'ember']);

        // May browse (manage-themes), may not install
        $install($this->confirmed($this->withPermissions(['manage-themes'])))->assertForbidden();
        $this->actingAs($this->createEditor())->get(route('backend.themes.marketplace'))->assertForbidden();

        $install($this->actingAs($this->withPermissions(['install-themes']))->withSession(['auth.password_confirmed_at' => 0]))
            ->assertRedirect(route('password.confirm'));

        config(['cms.code_editing' => false]);
        $install($this->confirmed($this->createAdmin()))->assertForbidden();

        $this->assertDirectoryDoesNotExist($this->root.'/themes/ember');
    }

    public function test_only_administrators_manage_the_sources(): void
    {
        // Even with the permission, a non-admin role may not choose sources (CodeAccess::ADMIN_ONLY)
        $this->confirmed($this->withPermissions(['manage-themes', 'manage-marketplace']))
            ->post(route('backend.themes.marketplace.sources'), ['sources' => 'https://x.example/m.json'])
            ->assertForbidden();

        $this->actingAs($this->createAdmin())->withSession(['auth.password_confirmed_at' => 0])
            ->post(route('backend.themes.marketplace.sources'), ['sources' => 'https://x.example/m.json'])
            ->assertRedirect(route('password.confirm'));

        $this->assertSame([], Marketplace::addedSources());
    }

    public function test_navigation_leads_installers_to_the_marketplace(): void
    {
        $this->fakeMarket([]);

        $html = $this->confirmed($this->withPermissions(['install-themes']))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('backend.themes.marketplace').'"', $html);
        $this->assertStringNotContainsString('href="'.route('backend.themes.index').'"', $html);
    }

    // ── Sources ────────────────────────────────────────────────────

    public function test_admin_adds_sources(): void
    {
        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.sources'), ['sources' => "https://two.example/m.json\n".self::CATALOG."\nhttps://three.example/c.json"])
            ->assertRedirect(route('backend.themes.marketplace'))
            ->assertSessionHas('success');

        // The source from the .env is not stored twice
        $this->assertSame(['https://two.example/m.json', 'https://three.example/c.json'], Marketplace::addedSources());
        $this->assertSame([self::CATALOG, 'https://two.example/m.json', 'https://three.example/c.json'], Marketplace::sources());
    }

    public function test_an_invalid_source_is_rejected(): void
    {
        $this->confirmed($this->createAdmin())->post(route('backend.themes.marketplace.sources'), ['sources' => 'http://plain.example/m.json'])
            ->assertSessionHasErrors('sources');

        $this->assertSame([], Marketplace::addedSources());
    }

    public function test_without_sources_nothing_is_fetched_and_the_marketplace_can_be_switched_off(): void
    {
        config(['cms.marketplace.sources' => '']);
        Http::fake();

        $this->actingAs($this->createAdmin())->get(route('backend.themes.marketplace'))->assertOk()->assertSee(__('translation.marketplace.no_sources_title'));
        $this->actingAs($this->createAdmin())->post(route('backend.themes.marketplace.refresh'))->assertNotFound();
        Http::assertNothingSent();

        config(['cms.marketplace.enabled' => false]);
        $this->actingAs($this->createAdmin())->get(route('backend.themes.marketplace'))->assertOk()->assertSee(__('translation.marketplace.disabled_title'));
        $this->actingAs($this->createAdmin())->get(route('backend.themes.index'))->assertOk()->assertDontSee(route('backend.themes.marketplace'), false);
    }
}
