<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Models\Plugin;
use App\Services\PackageInstaller;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Support\RemoteFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Marketplace: themes and plugins from catalogs.
 *
 * A source is the address of a catalog — a JSON file that lists packages with
 * their version, a download address and its SHA-256 checksum (format in
 * docs/MARKETPLACE.md). Anyone can run a marketplace by publishing such a
 * file; sources are CMS_MARKETPLACE_SOURCES plus the ones administrators add
 * in the backend. Catalogs are cached; installing downloads the package,
 * checks the checksum and then the package like an upload (PackageInstaller).
 * Only packages listed in a catalog can be installed.
 */
final class Marketplace
{
    public const TYPES = ['theme', 'plugin'];

    /** Setting with the sources added in the backend (one address per line) */
    public const SOURCES_SETTING = 'marketplace.sources';

    private const MAX_CATALOG_BYTES = 1024 * 1024;

    private const MAX_SCREENSHOT_BYTES = 2 * 1024 * 1024;

    /** Preview images that are stored (no SVG: it can carry script) */
    private const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(
        private readonly RemoteFile $remote,
        private readonly PackageInstaller $installer,
        private readonly ThemeManager $themes,
        private readonly PluginManager $plugins,
        private readonly PackageStore $store,
    ) {}

    /** Switched on (CMS_MARKETPLACE) — with or without sources yet */
    public static function enabled(): bool
    {
        return (bool) config('cms.marketplace.enabled', true);
    }

    /**
     * All sources: the ones from the .env plus the ones added in the backend.
     *
     * @return list<string>
     */
    public static function sources(): array
    {
        return array_values(array_unique([...self::configuredSources(), ...self::addedSources()]));
    }

    /**
     * Sources from CMS_MARKETPLACE_SOURCES — fixed in the backend.
     *
     * @return list<string>
     */
    public static function configuredSources(): array
    {
        return self::parseSources(config('cms.marketplace.sources', []));
    }

    /**
     * Sources an administrator added under Marketplace → Sources.
     *
     * @return list<string>
     */
    public static function addedSources(): array
    {
        try {
            return self::parseSources(setting(self::SOURCES_SETTING, ''));
        } catch (\Throwable) {
            // Before installation there is no settings table
            return [];
        }
    }

    /**
     * Catalog addresses from a list or a text (separated by commas, spaces or
     * lines); anything that is not an https address is dropped.
     *
     * @return list<string>
     */
    public static function parseSources(mixed $sources): array
    {
        $sources = is_string($sources) ? preg_split('/[\s,]+/', $sources) : (array) $sources;

        return array_values(array_unique(array_filter(
            array_map(fn ($source) => trim((string) $source), $sources),
            fn (string $source) => RemoteFile::validUrl($source),
        )));
    }

    /** Folder of the stored preview images */
    public static function screenshotDir(): string
    {
        return rtrim((string) config('cms.marketplace.screenshots', storage_path('app/marketplace')), '/');
    }

    /**
     * The packages of a type from all sources (cached unless $refresh).
     * The same slug in two catalogs: the source listed first wins.
     *
     * @return array{items: list<array<string, mixed>>, errors: array<string, string>, fetched_at: ?string}
     */
    public function catalog(string $type, bool $refresh = false): array
    {
        $items = [];
        $errors = [];
        $fetchedAt = null;

        foreach (self::sources() as $source) {
            $catalog = $this->source($source, $refresh);
            if ($catalog['error'] !== null) {
                $errors[$source] = $catalog['error'];
            }
            $fetchedAt = max($fetchedAt ?? '', (string) $catalog['fetched_at']) ?: null;

            foreach ($catalog['packages'] as $package) {
                if ($package['type'] === $type) {
                    $items[$package['slug']] ??= $package;
                }
            }
        }

        uasort($items, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return ['items' => array_values($items), 'errors' => $errors, 'fetched_at' => $fetchedAt];
    }

    /**
     * Catalog entries with their state on this installation.
     *
     * state: available | installed | update (newer version) | foreign (same
     * slug, managed by Git or Composer — not touched from here) | incompatible
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function withState(string $type, array $items): array
    {
        $installed = $type === 'theme' ? $this->themes->all() : $this->plugins->all();
        $enabled = $type === 'plugin' ? Plugin::where('is_enabled', true)->pluck('slug')->all() : [];

        return array_map(function (array $item) use ($type, $installed, $enabled) {
            $local = $installed[$item['slug']] ?? null;
            $version = $local !== null ? (string) ($local['version'] ?? '1.0.0') : null;
            $manager = $local !== null ? $this->store->manager($type, $local['path'] ?? null) : null;

            $state = match (true) {
                $local === null => self::compatible($item['requires']) ? 'available' : 'incompatible',
                $manager !== 'zip' => 'foreign',
                version_compare($item['version'], (string) $version, '>') => self::compatible($item['requires']) ? 'update' : 'installed',
                default => 'installed',
            };

            return $item + [
                'installed' => $version,
                'state' => $state,
                'active' => $type === 'theme'
                    ? $this->themes->getActive() === $item['slug']
                    : in_array($item['slug'], $enabled, true),
            ];
        }, $items);
    }

    /**
     * Install a package from the catalog.
     *
     * @return array{slug: string, name: string, version: string}
     */
    public function install(string $type, string $slug): array
    {
        $item = $this->find($type, $slug);

        if ($this->installedManifest($type, $slug) !== null) {
            throw new UpdateException(__('translation.marketplace.error_installed', ['name' => $item['name']]));
        }
        if (! self::compatible($item['requires'])) {
            throw new UpdateException(__('translation.marketplace.error_requires', ['name' => $item['name'], 'requires' => $item['requires']]));
        }

        return $this->put($type, $item);
    }

    /**
     * Bring an installed package to the catalog version (the old folder is
     * backed up, PackageStore).
     *
     * @return array{slug: string, name: string, version: string}
     */
    public function update(string $type, string $slug): array
    {
        $item = $this->withState($type, [$this->find($type, $slug)])[0];

        if ($item['state'] !== 'update') {
            throw new UpdateException(__('translation.marketplace.error_no_update', ['name' => $item['name']]));
        }

        return $this->put($type, $item);
    }

    /**
     * Does this CMS meet "requires"? "^1.2" and "1.2": same major version, at
     * least 1.2; ">=1.2": at least 1.2; empty: always.
     */
    public static function compatible(?string $requires): bool
    {
        if ($requires === null || $requires === '') {
            return true;
        }

        if (! preg_match('/^(\^|>=)?\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', trim($requires), $m)) {
            return false;
        }

        $minor = ($m[3] ?? '') === '' ? '0' : $m[3];
        $patch = ($m[4] ?? '') === '' ? '0' : $m[4];
        $minimum = $m[2].'.'.$minor.'.'.$patch;
        $current = (string) config('cms.version');

        if (version_compare($current, $minimum, '<')) {
            return false;
        }

        return $m[1] === '>=' || (int) explode('.', $current)[0] === (int) $m[2];
    }

    /** @return array{slug: string, name: string, version: string} */
    private function put(string $type, array $item): array
    {
        $lock = Cache::lock('cms.updates.package.'.$type.'.'.$item['slug'], 300);
        if (! $lock->get()) {
            throw new UpdateException(__('translation.updates.error_busy'));
        }

        $zip = storage_path('app/tmp-marketplace-'.uniqid().'.zip');

        try {
            $body = $this->remote->get($item['download'], (int) config('cms.updates.max_download_kb', 50 * 1024) * 1024, 'application/zip, application/octet-stream');

            // The catalog vouches for exactly this file
            if (! hash_equals($item['sha256'], hash('sha256', $body))) {
                throw new UpdateException(__('translation.marketplace.error_checksum', ['name' => $item['name']]));
            }
            if (! str_starts_with($body, "PK\x03\x04")) {
                throw new UpdateException(__('translation.updates.error_download'));
            }

            file_put_contents($zip, $body);
            $result = $this->installer->installFromPath($zip, $type, $item['slug']);

            if (! $result['success']) {
                throw new UpdateException((string) $result['error']);
            }

            return ['slug' => $item['slug'], 'name' => $item['name'], 'version' => (string) $result['version']];
        } finally {
            File::delete($zip);
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function find(string $type, string $slug): array
    {
        foreach ($this->catalog($type)['items'] as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        throw new UpdateException(__('translation.marketplace.error_unknown'));
    }

    private function installedManifest(string $type, string $slug): ?array
    {
        return $type === 'theme' ? $this->themes->get($slug) : $this->plugins->get($slug);
    }

    /**
     * One catalog, from the cache unless $refresh. A failed fetch keeps the
     * last list and reports the error; it is retried after a few minutes.
     *
     * @return array{name: string, packages: list<array<string, mixed>>, error: ?string, fetched_at: ?string}
     */
    private function source(string $url, bool $refresh): array
    {
        $key = 'cms.marketplace.catalog.'.md5($url);
        $cached = Cache::get($key);

        if (! $refresh && is_array($cached)) {
            return $cached;
        }

        try {
            $catalog = $this->fetch($url) + ['error' => null, 'fetched_at' => now()->toIso8601String()];
        } catch (UpdateException $e) {
            $catalog = [
                'name' => $cached['name'] ?? $url,
                'packages' => $cached['packages'] ?? [],
                'error' => $e->getMessage(),
                'fetched_at' => $cached['fetched_at'] ?? null,
            ];
        }

        $minutes = $catalog['error'] === null ? (int) config('cms.marketplace.cache_minutes', 360) : 5;
        Cache::put($key, $catalog, now()->addMinutes(max(1, $minutes)));

        return $catalog;
    }

    /** @return array{name: string, packages: list<array<string, mixed>>} */
    private function fetch(string $url): array
    {
        $data = json_decode($this->remote->get($url, self::MAX_CATALOG_BYTES, 'application/json'), true);

        if (! is_array($data) || (int) ($data['schema'] ?? 0) !== 1 || ! is_array($data['packages'] ?? null)) {
            throw new UpdateException(__('translation.marketplace.error_catalog', ['url' => $url]));
        }

        $name = $this->text($data['name'] ?? '', 80) ?: (string) parse_url($url, PHP_URL_HOST);
        $packages = [];

        foreach ($data['packages'] as $package) {
            // Entries that do not follow the format are left out
            if (is_array($package) && ($item = $this->package($package, $name, $url)) !== null) {
                $packages[] = $item;
            }
        }

        return ['name' => $name, 'packages' => $packages];
    }

    /** A valid catalog entry, or null */
    private function package(array $package, string $source, string $sourceUrl): ?array
    {
        $type = $package['type'] ?? null;
        $slug = $package['slug'] ?? null;
        $version = $package['version'] ?? null;
        $requires = $package['requires'] ?? null;

        if (! in_array($type, self::TYPES, true)
            || ! is_string($slug) || ! preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', $slug)
            || ! is_string($version) || ! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)
            || ! RemoteFile::validUrl($package['download'] ?? null)
            || ! is_string($package['sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', strtolower($package['sha256']))
            || ($requires !== null && ! is_string($requires))) {
            return null;
        }

        $homepage = $package['homepage'] ?? null;

        return [
            'type' => $type,
            'slug' => $slug,
            'name' => $this->text($package['name'] ?? $slug, 80) ?: $slug,
            'description' => $this->text($package['description'] ?? '', 300),
            'author' => $this->text($package['author'] ?? '', 80),
            'version' => $version,
            'requires' => $requires !== null ? $this->text($requires, 20) : null,
            'license' => $this->text($package['license'] ?? '', 40),
            'download' => $package['download'],
            'sha256' => strtolower($package['sha256']),
            'homepage' => RemoteFile::validUrl($homepage) ? $homepage : null,
            'source' => $source,
            'source_url' => $sourceUrl,
            'screenshot' => $this->screenshot($type, $slug, $package['screenshot'] ?? null),
        ];
    }

    /**
     * Store the preview image locally — the backend does not load images
     * from foreign hosts. Returns the file name in screenshotDir() or null.
     */
    private function screenshot(string $type, string $slug, mixed $url): ?string
    {
        if (! RemoteFile::validUrl($url)) {
            return null;
        }

        try {
            $bytes = $this->remote->get((string) $url, self::MAX_SCREENSHOT_BYTES, 'image/png, image/jpeg, image/webp');
        } catch (UpdateException) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        $extension = self::IMAGE_TYPES[$info['mime'] ?? ''] ?? null;
        if ($extension === null) {
            return null;
        }

        $file = "{$type}-{$slug}.{$extension}";
        File::ensureDirectoryExists(self::screenshotDir());
        File::put(self::screenshotDir().'/'.$file, $bytes);

        return $file;
    }

    private function text(mixed $value, int $length): string
    {
        return is_scalar($value) ? mb_substr(trim(strip_tags((string) $value)), 0, $length) : '';
    }
}
