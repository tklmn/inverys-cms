<?php

namespace Tests\Feature\Updates;

use App\Services\Updates\CoreUpdater;
use App\Services\Updates\DatabaseBackup;
use App\Services\Updates\GitHubReleases;
use App\Services\Updates\UpdateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

/**
 * CMS update from a package: check, back up, swap, finish, roll back —
 * on a replicated installation in a temp folder, never on the real code.
 */
class CoreUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private array $temp = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Http::preventStrayRequests();
        Cache::flush();

        $this->root = sys_get_temp_dir().'/inverys-core-'.uniqid();
        $this->temp[] = $this->root;
        $this->install($this->root, '1.0.0', 'old');
        // Only on this installation: no .git, own files alongside
        File::put($this->root.'/.env', 'APP_KEY=keep');
        File::put($this->root.'/storage/keep.txt', 'keep');
        File::ensureDirectoryExists($this->root.'/themes/custom');
        File::put($this->root.'/themes/custom/theme.json', '{"slug":"custom"}');
        File::ensureDirectoryExists($this->root.'/plugins/mine');
        File::put($this->root.'/plugins/mine/plugin.json', '{"slug":"mine"}');
        File::put($this->root.'/database/database.sqlite', 'DATA');
        File::ensureDirectoryExists($this->root.'/public/storage');
        File::put($this->root.'/public/storage/upload.jpg', 'IMG');

        config(['cms.updates.repository' => 'acme/cms']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────

    /** An installation (or package content) with a version and a marker */
    private function install(string $dir, string $version, string $mark, array $packages = ['laravel/framework']): void
    {
        $files = [
            'artisan' => '#!/usr/bin/env php',
            'config/cms.php' => "<?php return ['version' => '{$version}'];",
            'app/Marker.php' => $mark,
            'vendor/autoload.php' => '<?php',
            'vendor/composer/installed.json' => json_encode(['packages' => array_map(fn ($n) => ['name' => $n], $packages), 'dev-package-names' => []]),
            'public/index.php' => $mark,
            'public/build/manifest.json' => '{}',
            'themes/default/theme.json' => $mark,
            'plugins/blog/plugin.json' => $mark,
            'database/migrations/2026_01_01_000000_x.php' => $mark,
            'storage/.gitignore' => '*',
        ];
        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($dir.'/'.$path));
            File::put($dir.'/'.$path, $content);
        }
    }

    /** Update package as a ZIP */
    private function package(string $version, array $manifest = [], bool $complete = true, array $packages = ['laravel/framework']): string
    {
        $src = sys_get_temp_dir().'/inverys-pkg-'.uniqid();
        $this->temp[] = $src;
        $this->install($src, $version, 'new', $packages);
        File::put($src.'/app/NewThing.php', 'new');
        File::put($src.'/public/update-recovery.php', file_get_contents(base_path('public/update-recovery.php')));
        File::put($src.'/cms-package.json', json_encode($manifest + ['name' => 'inverys-cms', 'version' => $version, 'php' => '8.0']));
        if (! $complete) {
            File::deleteDirectory($src.'/vendor');
        }

        $zip = $src.'.zip';
        $this->temp[] = $zip;
        $archive = new ZipArchive;
        $archive->open($zip, ZipArchive::CREATE);
        foreach (File::allFiles($src, true) as $file) {
            $archive->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $archive->close();

        return $zip;
    }

    private function updater(?CoreUpdater $instance = null): CoreUpdater
    {
        $updater = $instance ?? new CoreUpdater(app(GitHubReleases::class), app(DatabaseBackup::class), $this->root, false);
        $this->app->instance(CoreUpdater::class, $updater);

        return $updater;
    }

    // ── Checks ─────────────────────────────────────────────────────

    public function test_the_source_archive_is_refused(): void
    {
        $this->expectException(UpdateException::class);
        $this->updater()->prepare($this->package('1.1.0', [], false));
    }

    public function test_older_versions_new_php_and_missing_composer_packages_are_refused(): void
    {
        $updater = $this->updater();

        foreach ([
            $this->package('0.9.0'),
            $this->package('1.1.0', ['php' => '99.0']),
        ] as $zip) {
            try {
                $updater->prepare($zip);
                $this->fail('Expected UpdateException');
            } catch (UpdateException) {
                $this->assertSame('old', File::get($this->root.'/app/Marker.php'));
            }
        }

        // Installed later via Composer: missing from the package — vendor/ would be lost
        File::put($this->root.'/vendor/composer/installed.json', json_encode(['packages' => [['name' => 'laravel/framework'], ['name' => 'acme/extra']]]));
        try {
            $updater->prepare($this->package('1.1.0'));
            $this->fail('Expected UpdateException');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('acme/extra', $e->getMessage());
        }
    }

    // ── Installing ─────────────────────────────────────────────────

    public function test_install_swaps_core_files_and_keeps_everything_of_the_site(): void
    {
        $updater = $this->updater();
        $result = $updater->install($updater->prepare($this->package('1.1.0')));

        $this->assertSame(['from' => '1.0.0', 'to' => '1.1.0'], ['from' => $result['from'], 'to' => $result['to']]);
        $this->assertSame('new', File::get($this->root.'/app/Marker.php'));
        $this->assertSame('new', File::get($this->root.'/themes/default/theme.json'));
        $this->assertFileExists($this->root.'/app/NewThing.php');
        $this->assertSame('1.1.0', $updater->installedVersion());

        // Untouched
        $this->assertSame('APP_KEY=keep', File::get($this->root.'/.env'));
        $this->assertSame('keep', File::get($this->root.'/storage/keep.txt'));
        $this->assertFileExists($this->root.'/themes/custom/theme.json');
        $this->assertFileExists($this->root.'/plugins/mine/plugin.json');
        $this->assertSame('DATA', File::get($this->root.'/database/database.sqlite'));
        $this->assertSame('IMG', File::get($this->root.'/public/storage/upload.jpg'));

        // Backed up and open for step 2
        $pending = $updater->pending();
        $this->assertSame($result['token'], $pending['token']);
        $this->assertSame('old', File::get($pending['backup'].'/files/app/Marker.php'));
    }

    public function test_finish_completes_the_update(): void
    {
        $updater = $this->updater();
        $result = $updater->install($updater->prepare($this->package('1.1.0')));

        $done = $updater->finish($result['token']);

        $this->assertSame(['status' => 'updated', 'version' => '1.1.0'], $done);
        $this->assertNull($updater->pending());
        $this->assertSame('1.0.0', $updater->backups()[0]['from']);
    }

    public function test_finish_needs_the_token(): void
    {
        $updater = $this->updater();
        $updater->install($updater->prepare($this->package('1.1.0')));

        $this->expectException(UpdateException::class);
        $updater->finish('wrong');
    }

    public function test_failed_migrations_put_the_old_files_back(): void
    {
        $updater = $this->updater(new class(app(GitHubReleases::class), app(DatabaseBackup::class), $this->root, false) extends CoreUpdater
        {
            protected function runMigrations(): void
            {
                throw new \RuntimeException('migration broke');
            }
        });
        $result = $updater->install($updater->prepare($this->package('1.1.0')));

        $done = $updater->finish($result['token']);

        $this->assertSame('rolled_back', $done['status']);
        $this->assertSame('migration broke', $done['error']);
        $this->assertSame('old', File::get($this->root.'/app/Marker.php'));
        $this->assertFileDoesNotExist($this->root.'/app/NewThing.php');
        $this->assertSame('1.0.0', $updater->installedVersion());
        $this->assertFileExists($this->root.'/themes/custom/theme.json');
        $this->assertNull($updater->pending());
    }

    public function test_restore_takes_the_last_update_back(): void
    {
        $updater = $this->updater();
        $result = $updater->install($updater->prepare($this->package('1.1.0')));
        $updater->finish($result['token']);

        $this->assertSame('1.0.0', $updater->restore());

        $this->assertSame('old', File::get($this->root.'/app/Marker.php'));
        $this->assertFileDoesNotExist($this->root.'/app/NewThing.php');
        $this->assertSame('APP_KEY=keep', File::get($this->root.'/.env'));
        $this->assertSame([], $updater->backups());
    }

    public function test_recovery_script_restores_without_laravel(): void
    {
        $updater = $this->updater();
        $result = $updater->install($updater->prepare($this->package('1.1.0')), [
            'recovery_title' => 'Restore', 'recovery_done' => 'RESTORED',
        ]);
        File::ensureDirectoryExists($this->root.'/storage/framework');
        File::put($this->root.'/storage/framework/down', '{}');

        $run = fn (string $token) => shell_exec(sprintf(
            'php -r %s 2>&1',
            escapeshellarg('$_SERVER["REQUEST_METHOD"] = "POST"; $_POST["token"] = '.var_export($token, true).'; include '.var_export($this->root.'/public/update-recovery.php', true).';')
        ));

        $this->assertStringNotContainsString('RESTORED', (string) $run('wrong'));
        $this->assertSame('new', File::get($this->root.'/app/Marker.php'));

        $this->assertStringContainsString('RESTORED', (string) $run($result['token']));
        $this->assertSame('old', File::get($this->root.'/app/Marker.php'));
        $this->assertFileDoesNotExist($this->root.'/app/NewThing.php');
        $this->assertFileDoesNotExist($this->root.'/storage/framework/down');
        $this->assertNull($updater->pending());
    }

    // ── From the release ───────────────────────────────────────────

    public function test_download_needs_the_complete_package(): void
    {
        $updater = $this->updater();
        // Only the source archive on the release: no package
        Http::fake(['api.github.com/repos/acme/cms/releases/latest' => Http::response([
            'tag_name' => '1.1.0', 'zipball_url' => 'https://api.github.com/repos/acme/cms/zipball/1.1.0', 'assets' => [],
        ])]);

        try {
            $updater->downloadLatest();
            $this->fail('Expected UpdateException');
        } catch (UpdateException $e) {
            $this->assertSame(__('translation.updates.error_core_no_package', ['version' => '1.1.0']), $e->getMessage());
        }
    }

    public function test_download_refuses_a_wrong_checksum(): void
    {
        $updater = $this->updater();
        $zip = File::get($this->package('1.1.0'));
        $release = fn (array $assets) => Http::response([
            'tag_name' => '1.1.0', 'zipball_url' => 'https://api.github.com/repos/acme/cms/zipball/1.1.0', 'assets' => $assets,
        ]);

        // Package with a wrong checksum
        Http::fake([
            'api.github.com/repos/acme/cms/releases/latest' => $release([
                ['name' => 'inverys-cms-1.1.0.zip', 'url' => 'https://api.github.com/repos/acme/cms/releases/assets/1'],
                ['name' => 'inverys-cms-1.1.0.zip.sha256', 'url' => 'https://api.github.com/repos/acme/cms/releases/assets/2'],
            ]),
            'api.github.com/repos/acme/cms/releases/assets/1' => Http::response($zip),
            'api.github.com/repos/acme/cms/releases/assets/2' => Http::response(str_repeat('0', 64).'  inverys-cms-1.1.0.zip'),
        ]);
        try {
            $updater->downloadLatest();
            $this->fail('Expected UpdateException');
        } catch (UpdateException $e) {
            $this->assertStringContainsString(__('translation.updates.error_checksum'), $e->getMessage());
        }
    }

    public function test_download_with_matching_checksum(): void
    {
        $updater = $this->updater();
        $zip = File::get($this->package('1.1.0'));
        Http::fake([
            'api.github.com/repos/acme/cms/releases/latest' => Http::response([
                'tag_name' => '1.1.0', 'zipball_url' => 'https://api.github.com/x',
                'assets' => [
                    ['name' => 'inverys-cms-1.1.0.zip', 'url' => 'https://api.github.com/repos/acme/cms/releases/assets/1'],
                    ['name' => 'inverys-cms-1.1.0.zip.sha256', 'url' => 'https://api.github.com/repos/acme/cms/releases/assets/2'],
                ],
            ]),
            'api.github.com/repos/acme/cms/releases/assets/1' => Http::response($zip),
            'api.github.com/repos/acme/cms/releases/assets/2' => Http::response(hash('sha256', $zip).'  inverys-cms-1.1.0.zip'),
        ]);

        $path = $updater->downloadLatest();
        $this->temp[] = $path;

        $this->assertSame(hash('sha256', $zip), hash_file('sha256', $path));
    }

    // ── Interface ──────────────────────────────────────────────────

    public function test_upload_through_the_page_runs_both_steps(): void
    {
        $this->updater();
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('backend.updates.core.upload'), ['package' => new UploadedFile($this->package('1.1.0'), 'inverys-cms-1.1.0.zip', 'application/zip', null, true)]);

        // Unencrypted: Laravel reads the bypass cookie before decrypting
        $response->assertOk()->assertPlainCookie('laravel_maintenance');
        $this->assertSame('new', File::get($this->root.'/app/Marker.php'));
        preg_match('/url=([^"]+)"/', $response->getContent(), $m);
        $finish = html_entity_decode($m[1]);
        $this->assertStringContainsString('/backend/updates/core/finish?token=', $finish);

        $this->actingAs($admin)->get($finish)
            ->assertRedirect(route('backend.updates.index'))
            ->assertSessionHas('success');
    }

    public function test_page_shows_upload_and_guards_it(): void
    {
        $this->updater();

        $this->actingAs($this->createAdmin())->get(route('backend.updates.index'))
            ->assertOk()
            ->assertSee(route('backend.updates.core.upload'));

        $this->actingAs($this->createEditor())->post(route('backend.updates.core.upload'))->assertForbidden();

        config(['cms.code_editing' => false]);
        $this->actingAs($this->createAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('backend.updates.core.upload'))->assertForbidden();
    }

    public function test_git_installations_are_not_updated_by_package(): void
    {
        File::ensureDirectoryExists($this->root.'/.git');
        $this->updater();

        $this->actingAs($this->createAdmin())->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('backend.updates.core.upload'), ['package' => new UploadedFile($this->package('1.1.0'), 'p.zip', 'application/zip', null, true)])
            ->assertRedirect(route('backend.updates.index'))
            ->assertSessionHas('error', __('translation.updates.reason_core_git'));

        $this->assertSame('old', File::get($this->root.'/app/Marker.php'));
    }
}
