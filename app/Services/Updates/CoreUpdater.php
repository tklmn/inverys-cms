<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Updates the CMS itself — from the full release package
 * (inverys-cms-<version>.zip with vendor/ and built files, built by
 * .github/workflows/release-package.yml) or an uploaded ZIP.
 *
 * Runs in two requests:
 *
 *   1. install(): validate, back up database and files, enable maintenance
 *      mode, swap files. This request still has the old code in memory —
 *      it runs nothing new afterwards.
 *   2. finish(): the next request starts with the new code, runs the
 *      migrations and ends maintenance mode. If that fails, this run's
 *      migrations are rolled back and the old files are put back.
 *
 * If the new code does not start at all, public/update-recovery.php (without
 * Laravel) restores the old files.
 *
 * Never touched: .env, storage/, third-party themes and plugins (only the
 * folders contained in the package are swapped), SQLite files, public/storage.
 */
class CoreUpdater
{
    /** Always stays as it is */
    private const PRESERVE = ['.env', 'storage', '.git', '.github', 'node_modules', 'tests'];

    /** Only the entries the package brings along are swapped here */
    private const PER_ITEM = ['themes', 'plugins', 'public', 'database'];

    /** Entries in the PER_ITEM folders that are never swapped */
    private const SKIP = ['public/storage', 'public/hot'];

    /** Must be in the package — otherwise it is just the source archive */
    private const REQUIRED = ['artisan', 'config/cms.php', 'vendor/autoload.php', 'public/index.php', 'public/build/manifest.json'];

    public function __construct(
        private readonly GitHubReleases $releases,
        private readonly DatabaseBackup $database,
        private readonly ?string $root = null,
        private readonly bool $maintenance = true,
    ) {}

    public function root(): string
    {
        return rtrim($this->root ?? base_path(), '/');
    }

    /** Working directory: backups, pending update, extracted packages */
    public function workDir(): string
    {
        return $this->root().'/storage/app/cms-updates';
    }

    /** A Git installation is updated via git pull, not via package */
    public function isGitCheckout(): bool
    {
        return file_exists($this->root().'/.git');
    }

    public function installedVersion(): string
    {
        $config = @file_get_contents($this->root().'/config/cms.php');

        return preg_match("/'version'\s*=>\s*'([^']+)'/", (string) $config, $m) ? $m[1] : (string) config('cms.version');
    }

    /**
     * Asset holding a release's full package, or null.
     *
     * @return array{zip: string, sha256: ?string}|null
     */
    public function releasePackage(?array $release): ?array
    {
        $name = 'inverys-cms-'.($release['version'] ?? '').'.zip';
        $assets = (array) ($release['assets'] ?? []);

        return isset($assets[$name]) ? ['zip' => $assets[$name], 'sha256' => $assets[$name.'.sha256'] ?? null] : null;
    }

    /** Download the latest version; returns the path of the ZIP */
    public function downloadLatest(): string
    {
        $release = $this->releases->latest((string) config('cms.updates.repository'));

        if (! version_compare($release['version'], $this->installedVersion(), '>')) {
            throw new UpdateException(__('translation.updates.error_current', ['name' => 'Inverys CMS']));
        }

        $package = $this->releasePackage($release);
        if ($package === null) {
            throw new UpdateException(__('translation.updates.error_core_no_package', ['version' => $release['version']]));
        }

        File::ensureDirectoryExists($this->workDir());
        $zip = $this->workDir().'/download-'.Str::random(8).'.zip';
        $this->releases->download($release, $zip, $package['zip'], (int) config('cms.updates.max_core_kb', 300 * 1024));

        // Checksum from the release: the package is exactly the one that was built
        if ($package['sha256'] !== null) {
            $expected = strtolower(strtok(trim($this->releases->text($package['sha256'])), " \t") ?: '');
            if (! hash_equals($expected, hash_file('sha256', $zip))) {
                File::delete($zip);
                throw new UpdateException(__('translation.updates.error_checksum'));
            }
        }

        return $zip;
    }

    /**
     * Validate and extract the ZIP.
     *
     * @return array{tmp: string, root: string, manifest: array<string, mixed>}
     */
    public function prepare(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException(__('Could not open ZIP file.'));
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (str_starts_with($entry, '/') || str_contains($entry, '\\') || in_array('..', explode('/', $entry), true)) {
                $zip->close();
                throw new UpdateException(__('Blocked: ZIP contains a path traversal entry (:entry).', ['entry' => $entry]));
            }
        }

        $tmp = $this->workDir().'/package-'.Str::random(8);
        File::ensureDirectoryExists($tmp);
        $zip->extractTo($tmp);
        $zip->close();

        try {
            $root = is_file($tmp.'/cms-package.json') ? $tmp : null;
            foreach ($root === null ? File::directories($tmp) : [] as $dir) {
                if (is_file($dir.'/cms-package.json')) {
                    $root = $dir;
                    break;
                }
            }
            if ($root === null) {
                throw new UpdateException(__('translation.updates.error_core_incomplete'));
            }

            $manifest = json_decode((string) file_get_contents($root.'/cms-package.json'), true);
            if (! is_array($manifest) || ($manifest['name'] ?? null) !== 'inverys-cms'
                || ! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', (string) ($manifest['version'] ?? ''))) {
                throw new UpdateException(__('translation.updates.error_core_incomplete'));
            }

            foreach (self::REQUIRED as $required) {
                if (! file_exists($root.'/'.$required)) {
                    throw new UpdateException(__('translation.updates.error_core_incomplete'));
                }
            }

            $installed = $this->installedVersion();
            if (version_compare((string) $manifest['version'], $installed, '<')) {
                throw new UpdateException(__('translation.updates.error_downgrade', ['version' => $manifest['version'], 'installed' => $installed]));
            }

            $php = (string) ($manifest['php'] ?? '');
            if ($php !== '' && version_compare(PHP_VERSION, $php, '<')) {
                throw new UpdateException(__('translation.updates.error_core_php', ['required' => $php, 'current' => PHP_VERSION]));
            }

            // Packages added later via Composer are missing from the package — their vendor/ would be lost
            $missing = array_diff($this->composerPackages($this->root()), $this->composerPackages($root));
            if ($missing !== []) {
                throw new UpdateException(__('translation.updates.error_core_extra_packages', ['packages' => implode(', ', $missing)]));
            }
        } catch (UpdateException $e) {
            File::deleteDirectory($tmp);

            throw $e;
        }

        return ['tmp' => $tmp, 'root' => $root, 'manifest' => $manifest];
    }

    /**
     * Step 1: back up, maintenance mode, swap files.
     *
     * @param  array<string, string>  $texts  Texts for public/update-recovery.php (in the admin's language)
     * @return array{token: string, secret: string, from: string, to: string}
     */
    public function install(array $prepared, array $texts = []): array
    {
        $from = $this->installedVersion();
        $to = (string) $prepared['manifest']['version'];
        $paths = $this->plan($prepared['root']);

        try {
            foreach ($paths as $item) {
                $target = $this->root().'/'.$item['path'];
                if (! is_writable(dirname($target)) || (file_exists($target) && ! is_writable($target))) {
                    throw new UpdateException(__('translation.updates.error_core_writable', ['path' => $item['path']]));
                }
            }

            $backup = $this->workDir().'/backups/'.preg_replace('/[^A-Za-z0-9._-]/', '', $from).'__'.now()->format('Ymd-His');
            File::ensureDirectoryExists($backup.'/files');

            $database = $this->database->create($backup);

            // Record the plan before swapping: the emergency rollback needs it even midway
            $this->writeJson($backup.'/manifest.json', [
                'from' => $from, 'to' => $to, 'paths' => $paths, 'database' => $database,
                'batch' => null, 'created_at' => now()->toIso8601String(), 'completed' => false,
            ]);

            $token = Str::random(40);
            $secret = Str::random(32);
            $this->writeJson($this->workDir().'/pending.json', [
                'token' => $token, 'from' => $from, 'to' => $to, 'backup' => $backup,
                'database' => $database, 'texts' => $texts, 'started_at' => now()->toIso8601String(),
            ]);

            $this->down($secret);

            try {
                foreach ($paths as $item) {
                    $this->swapIn($prepared['root'], $backup, $item);
                }
            } catch (\Throwable $e) {
                $this->restoreFiles($backup, $paths);
                $this->clearCompiled();
                File::delete($this->workDir().'/pending.json');
                $this->up();

                throw new UpdateException(__('translation.updates.error_files'));
            }

            $this->clearCompiled();

            return ['token' => $token, 'secret' => $secret, 'from' => $from, 'to' => $to];
        } finally {
            File::deleteDirectory($prepared['tmp']);
        }
    }

    /** @return array<string, mixed>|null Pending update (step 2 outstanding) */
    public function pending(): ?array
    {
        $file = $this->workDir().'/pending.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Step 2 — runs with the new code: migrations, caches, online.
     *
     * @return array{status: string, version: string, error?: string}
     */
    public function finish(string $token): array
    {
        $pending = $this->pending();
        if ($pending === null || ! hash_equals((string) $pending['token'], $token)) {
            throw new UpdateException(__('translation.updates.error_no_pending'));
        }

        @set_time_limit(0);
        $manifestFile = $pending['backup'].'/manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        $batchBefore = $this->currentBatch();

        try {
            $this->runMigrations();
        } catch (\Throwable $e) {
            // Roll back this run's migrations while the new code (with down()) is present
            try {
                $this->rollbackBatch($batchBefore);
            } finally {
                $this->restoreFiles($pending['backup'], $manifest['paths']);
                $this->clearCompiled();
                File::delete($this->workDir().'/pending.json');
                $this->up();
            }

            return ['status' => 'rolled_back', 'version' => (string) $pending['from'], 'error' => $e->getMessage()];
        }

        $manifest['batch'] = $this->currentBatch() > $batchBefore ? $this->currentBatch() : null;
        $manifest['completed'] = true;
        $this->writeJson($manifestFile, $manifest);

        $this->clearCaches();
        File::delete($this->workDir().'/pending.json');
        $this->up();
        $this->prune();

        return ['status' => 'updated', 'version' => (string) $pending['to']];
    }

    /**
     * Completed updates, newest first — each one can be rolled back.
     *
     * @return list<array<string, mixed>>
     */
    public function backups(): array
    {
        $list = [];
        $root = $this->workDir().'/backups';
        foreach (is_dir($root) ? File::directories($root) : [] as $dir) {
            $manifest = is_file($dir.'/manifest.json') ? json_decode((string) file_get_contents($dir.'/manifest.json'), true) : null;
            if (is_array($manifest) && ! empty($manifest['completed'])) {
                $list[] = $manifest + ['path' => $dir];
            }
        }

        usort($list, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $list;
    }

    /** Roll back the last update: revert migrations, restore old files */
    public function restore(): string
    {
        $backup = $this->backups()[0] ?? null;
        if ($backup === null) {
            throw new UpdateException(__('translation.updates.error_no_backup'));
        }
        if ($this->installedVersion() !== (string) $backup['to']) {
            throw new UpdateException(__('translation.updates.error_core_restore_mismatch', ['version' => $backup['to']]));
        }

        @set_time_limit(0);
        $this->down(Str::random(32));

        try {
            // Exactly this update's migrations — not later ones
            if (! empty($backup['batch'])) {
                Artisan::call('migrate:rollback', ['--force' => true, '--batch' => (int) $backup['batch']]);
            }
            $this->restoreFiles($backup['path'], $backup['paths']);
            File::deleteDirectory($backup['path']);
            $this->clearCompiled();
        } finally {
            $this->up();
        }

        return (string) $backup['from'];
    }

    // ── Building blocks ────────────────────────────────────────────

    /**
     * What gets swapped: everything from the package except PRESERVE; in PER_ITEM
     * only the entries brought along; folders with their own .git stay.
     *
     * @return list<array{path: string, existed: bool}>
     */
    private function plan(string $packageRoot): array
    {
        $paths = [];
        foreach (array_diff(scandir($packageRoot) ?: [], ['.', '..']) as $name) {
            if (in_array($name, self::PRESERVE, true)) {
                continue;
            }

            $names = in_array($name, self::PER_ITEM, true) && is_dir($packageRoot.'/'.$name)
                ? array_map(fn ($sub) => $name.'/'.$sub, array_values(array_diff(scandir($packageRoot.'/'.$name) ?: [], ['.', '..'])))
                : [$name];

            foreach ($names as $path) {
                $current = $this->root().'/'.$path;
                if (in_array($path, self::SKIP, true) || str_ends_with($path, '.sqlite') || file_exists($current.'/.git')) {
                    continue;
                }
                $paths[] = ['path' => $path, 'existed' => file_exists($current) || is_link($current)];
            }
        }

        return $paths;
    }

    /** Move the old entry into the backup, the new one into its place */
    private function swapIn(string $packageRoot, string $backup, array $item): void
    {
        $current = $this->root().'/'.$item['path'];
        $saved = $backup.'/files/'.$item['path'];

        if ($item['existed']) {
            File::ensureDirectoryExists(dirname($saved));
            $this->move($current, $saved);
        }

        File::ensureDirectoryExists(dirname($current));
        $this->move($packageRoot.'/'.$item['path'], $current);
    }

    /**
     * Put backed-up entries back (also after an abort midway):
     * what the backup has is restored; what was newly added is removed.
     */
    private function restoreFiles(string $backup, array $paths): void
    {
        $failed = $backup.'/replaced-'.Str::random(6);

        foreach (array_reverse($paths) as $item) {
            $current = $this->root().'/'.$item['path'];
            $saved = $backup.'/files/'.$item['path'];
            $hasSaved = file_exists($saved) || is_link($saved);

            if (! $hasSaved && $item['existed']) {
                continue; // never swapped
            }
            if (file_exists($current) || is_link($current)) {
                File::ensureDirectoryExists(dirname($failed.'/'.$item['path']));
                $this->move($current, $failed.'/'.$item['path']);
            }
            if ($hasSaved) {
                $this->move($saved, $current);
            }
        }

        File::deleteDirectory($failed);
    }

    private function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        $ok = is_dir($from) ? File::copyDirectory($from, $to) && File::deleteDirectory($from) : copy($from, $to) && unlink($from);
        if (! $ok) {
            throw new UpdateException(__('translation.updates.error_files'));
        }
    }

    /** @return list<string> Names of the installed Composer packages (excluding dev) */
    private function composerPackages(string $root): array
    {
        $file = $root.'/vendor/composer/installed.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data)) {
            return [];
        }

        $names = array_column($data['packages'] ?? $data, 'name');

        return array_values(array_diff($names, (array) ($data['dev-package-names'] ?? [])));
    }

    private function currentBatch(): int
    {
        return (int) DB::table('migrations')->max('batch');
    }

    protected function runMigrations(): void
    {
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new UpdateException(trim(Artisan::output()));
        }
    }

    private function rollbackBatch(int $batchBefore): void
    {
        $batch = $this->currentBatch();
        if ($batch > $batchBefore) {
            Artisan::call('migrate:rollback', ['--force' => true, '--batch' => $batch]);
        }
    }

    /** Discard compiled views and clear OPcache — directly, without loading new code */
    private function clearCompiled(): void
    {
        foreach (glob($this->root().'/storage/framework/views/*.php') ?: [] as $file) {
            @unlink($file);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function clearCaches(): void
    {
        if ($this->maintenance) {
            Artisan::call('optimize:clear');
        }
        $this->clearCompiled();
    }

    private function prune(): void
    {
        $keep = max(1, (int) config('cms.updates.keep_core_backups', 2));
        foreach (array_slice($this->backups(), $keep) as $old) {
            File::deleteDirectory($old['path']);
        }
    }

    private function down(string $secret): void
    {
        if ($this->maintenance) {
            Artisan::call('down', ['--secret' => $secret, '--retry' => 60]);
        }
    }

    private function up(): void
    {
        if ($this->maintenance) {
            Artisan::call('up');
        }
    }

    private function writeJson(string $file, array $data): void
    {
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
