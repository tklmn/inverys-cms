<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Models\Plugin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Swap theme and plugin folders — with backup and a way back.
 *
 * Before each swap the old folder moves to
 * storage/app/package-backups/<plugins|themes>/<slug>/<version>__<time>; the
 * last cms.updates.keep_backups states are kept. A plugin that was active
 * during the update only counts as done once the next request has loaded the
 * new code and run its upgrade() (PluginManager) — if that fails, settle()
 * restores the old state.
 */
final class PackageStore
{
    private const PENDING = 'cms.updates.pending';

    public const ERROR_PREFIX = 'cms.plugin-error.';

    /** Base folder for packages of a type */
    public function base(string $type): string
    {
        return rtrim((string) config($type === 'theme' ? 'cms.themes_path' : 'cms.plugins_path'), '/');
    }

    /**
     * Who manages the folder? zip: swappable; git: its own Git checkout
     * (a ZIP would overwrite .git); composer: not located under the
     * base folder (vendor/).
     */
    public function manager(string $type, ?string $path): string
    {
        $base = realpath($this->base($type));
        $dir = $path ? realpath($path) : false;

        if ($base === false || $dir === false || dirname($dir) !== $base) {
            return 'composer';
        }

        return file_exists($dir.'/.git') ? 'git' : 'zip';
    }

    /**
     * Put the new package folder in place of the old one.
     *
     * @param  string  $newRoot  extracted folder with theme.json/plugin.json
     */
    public function replace(string $type, string $slug, string $newRoot, ?string $fromVersion, string $toVersion): void
    {
        $target = $this->base($type).'/'.$slug;
        $existed = is_dir($target);
        $backup = null;

        if ($existed) {
            if ($this->manager($type, $target) !== 'zip') {
                throw new UpdateException(__('translation.updates.reason_git'));
            }

            $backup = $this->backupPath($type, $slug, $fromVersion ?? 'unknown');
            File::ensureDirectoryExists(dirname($backup));
            if (! $this->move($target, $backup)) {
                throw new UpdateException(__('translation.updates.error_files'));
            }
        }

        if (! $this->move($newRoot, $target)) {
            // Put the old state back immediately
            if ($backup !== null) {
                $this->move($backup, $target);
            }
            throw new UpdateException(__('translation.updates.error_files'));
        }

        // Active plugin: only the next request loads the new code
        if ($type === 'plugin' && $existed && Plugin::where('slug', $slug)->where('is_enabled', true)->exists()) {
            $pending = Cache::get(self::PENDING, []);
            $pending['plugin:'.$slug] = [
                'type' => $type, 'slug' => $slug, 'from' => $fromVersion, 'to' => $toVersion, 'backup' => $backup,
            ];
            Cache::forever(self::PENDING, $pending);
            Cache::forget(self::ERROR_PREFIX.$slug);
        }

        $this->prune($type, $slug);
        $this->refresh();
    }

    /**
     * Backed-up states, newest first.
     *
     * @return list<array{path: string, version: string, saved_at: string}>
     */
    public function backups(string $type, string $slug): array
    {
        $dir = $this->backupRoot($type).'/'.$slug;
        if (! is_dir($dir)) {
            return [];
        }

        $backups = [];
        foreach (File::directories($dir) as $path) {
            [$version, $stamp] = array_pad(explode('__', basename($path), 2), 2, '');
            $backups[] = ['path' => $path, 'version' => $version, 'saved_at' => $stamp];
        }

        usort($backups, fn ($a, $b) => strcmp($b['saved_at'], $a['saved_at']));

        return $backups;
    }

    /** Restore the newest backed-up state; the current one is backed up in the process */
    public function restore(string $type, string $slug, ?string $currentVersion): string
    {
        $backup = $this->backups($type, $slug)[0] ?? null;
        if ($backup === null) {
            throw new UpdateException(__('translation.updates.error_no_backup'));
        }

        $target = $this->base($type).'/'.$slug;
        if (is_dir($target) && $this->manager($type, $target) !== 'zip') {
            throw new UpdateException(__('translation.updates.reason_git'));
        }

        $this->swapBack($type, $slug, $backup['path'], $currentVersion);

        // Plugin: set the version directly — otherwise the PluginManager would
        // take the older version for an update and call upgrade()
        if ($type === 'plugin') {
            Plugin::where('slug', $slug)->update(['version' => $backup['version']]);
        }

        return $backup['version'];
    }

    /**
     * Complete pending plugin updates. If the new version did not load
     * or its upgrade() failed (plugin disabled), the old state is
     * restored and the plugin is enabled again.
     *
     * @return list<array{status: string, slug: string, version: ?string, error?: string}>
     */
    public function settle(): array
    {
        $pending = Cache::get(self::PENDING, []);
        if ($pending === []) {
            return [];
        }

        $results = [];
        foreach ($pending as $key => $item) {
            $plugin = Plugin::where('slug', $item['slug'])->first();
            $error = Cache::pull(self::ERROR_PREFIX.$item['slug']);

            if ($plugin && $plugin->is_enabled && $error === null && (string) $plugin->version === (string) $item['to']) {
                $results[] = ['status' => 'updated', 'slug' => $item['slug'], 'version' => $item['to']];
                unset($pending[$key]);

                continue;
            }

            if ($plugin && $plugin->is_enabled && $error === null) {
                // New version not loaded yet (e.g. parallel upgrade run) — retry later
                continue;
            }

            if (! empty($item['backup']) && is_dir($item['backup'])) {
                $this->swapBack('plugin', $item['slug'], $item['backup'], ($item['to'] ?? 'unknown').'-failed');
            }
            Plugin::where('slug', $item['slug'])->update([
                'is_enabled' => true,
                'enabled_at' => now(),
                'version' => $item['from'],
            ]);

            $results[] = ['status' => 'rolled_back', 'slug' => $item['slug'], 'version' => $item['from'], 'error' => (string) ($error ?? '')];
            unset($pending[$key]);
        }

        Cache::forever(self::PENDING, $pending);

        return $results;
    }

    /** Back up the current folder and put $backupPath in its place */
    private function swapBack(string $type, string $slug, string $backupPath, ?string $currentVersion): void
    {
        $target = $this->base($type).'/'.$slug;

        if (is_dir($target)) {
            $current = $this->backupPath($type, $slug, $currentVersion ?? 'unknown');
            File::ensureDirectoryExists(dirname($current));
            if (! $this->move($target, $current)) {
                throw new UpdateException(__('translation.updates.error_files'));
            }
        }

        if (! $this->move($backupPath, $target)) {
            throw new UpdateException(__('translation.updates.error_files'));
        }

        $this->prune($type, $slug);
        $this->refresh();
    }

    private function prune(string $type, string $slug): void
    {
        $keep = max(1, (int) config('cms.updates.keep_backups', 3));

        foreach (array_slice($this->backups($type, $slug), $keep) as $old) {
            File::deleteDirectory($old['path']);
        }
    }

    private function backupRoot(string $type): string
    {
        return storage_path('app/package-backups/'.($type === 'theme' ? 'themes' : 'plugins'));
    }

    private function backupPath(string $type, string $slug, string $version): string
    {
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', $version) ?: 'unknown';
        $path = $this->backupRoot($type).'/'.$slug.'/'.$version.'__'.now()->format('Ymd-His-v');

        // Two backups in the same millisecond (update and immediate rollback)
        $unique = $path;
        for ($i = 2; is_dir($unique); $i++) {
            $unique = $path.'-'.$i;
        }

        return $unique;
    }

    /** Move; across filesystem boundaries copy and delete */
    private function move(string $from, string $to): bool
    {
        if (@rename($from, $to)) {
            return true;
        }

        if (! File::copyDirectory($from, $to)) {
            File::deleteDirectory($to);

            return false;
        }

        return File::deleteDirectory($from);
    }

    /** New code should take effect immediately: clear OPcache, discard compiled views */
    private function refresh(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        Artisan::call('view:clear');
    }
}
