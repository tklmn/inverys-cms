<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Services\PluginManager;
use App\Services\ThemeManager;
use Illuminate\Support\Facades\Cache;

/**
 * Which versions are available? CMS, plugins and themes with their source.
 *
 * A plugin's/theme's source is "repository" ("owner/repo" on GitHub) in
 * plugin.json or theme.json; the CMS itself comes from cms.updates.repository.
 * The result of the last check is cached (cms:check-updates checks
 * daily, the update page on demand).
 */
final class UpdateChecker
{
    public const CACHE_KEY = 'cms.updates.status';

    public function __construct(
        private readonly GitHubReleases $releases,
        private readonly PluginManager $plugins,
        private readonly ThemeManager $themes,
        private readonly PackageStore $store,
    ) {}

    /**
     * All packages with their installed version and how they are updated.
     * manager: core | zip | git | composer | none (no source)
     *
     * @return list<array{key: string, type: string, slug: string, name: string, installed: string, repository: ?string, manager: string}>
     */
    public function packages(): array
    {
        $packages = [[
            'key' => 'core',
            'type' => 'core',
            'slug' => 'inverys-cms',
            'name' => 'Inverys CMS',
            'installed' => (string) config('cms.version'),
            'repository' => GitHubReleases::validRepository(config('cms.updates.repository')) ? config('cms.updates.repository') : null,
            'manager' => 'core',
        ]];

        foreach (['plugin' => $this->plugins->all(), 'theme' => $this->themes->all()] as $type => $manifests) {
            foreach ($manifests as $slug => $manifest) {
                $repository = $manifest['repository'] ?? null;
                $manager = $this->store->manager($type, $manifest['path'] ?? null);

                $packages[] = [
                    'key' => $type.':'.$slug,
                    'type' => $type,
                    'slug' => (string) $slug,
                    'name' => __((string) ($manifest['name'] ?? $slug)),
                    'installed' => (string) ($manifest['version'] ?? '1.0.0'),
                    'repository' => GitHubReleases::validRepository($repository) ? $repository : null,
                    'manager' => $manager === 'zip' && ! GitHubReleases::validRepository($repository) ? 'none' : $manager,
                ];
            }
        }

        return $packages;
    }

    /** Query all sources and remember the result */
    public function check(): array
    {
        $releases = [];

        foreach ($this->packages() as $package) {
            if ($package['repository'] === null) {
                continue;
            }

            try {
                $releases[$package['key']] = $this->releases->latest($package['repository']);
            } catch (UpdateException $e) {
                $releases[$package['key']] = ['error' => $e->getMessage()];
            }
        }

        $status = ['checked_at' => now()->toIso8601String(), 'releases' => $releases];
        Cache::forever(self::CACHE_KEY, $status);

        return $status;
    }

    /**
     * Packages with the result of the last check.
     *
     * @return list<array<string, mixed>>
     */
    public function status(): array
    {
        $releases = Cache::get(self::CACHE_KEY)['releases'] ?? [];

        return array_map(function (array $package) use ($releases) {
            $release = $releases[$package['key']] ?? null;
            $latest = $release['version'] ?? null;

            return $package + [
                'latest' => $latest,
                'release' => $release && ! isset($release['error']) ? $release : null,
                'error' => $release['error'] ?? null,
                'available' => $latest !== null && version_compare($latest, $package['installed'], '>'),
            ];
        }, $this->packages());
    }

    /** Time of the last check, or null */
    public function checkedAt(): ?string
    {
        return Cache::get(self::CACHE_KEY)['checked_at'] ?? null;
    }

    /**
     * Newer versions per type, for the lists under Extensions.
     *
     * @return array<string, array<string, mixed>> slug => status (only with a newer version)
     */
    public function availableFor(string $type): array
    {
        $out = [];
        foreach ($this->status() as $item) {
            if ($item['type'] === $type && $item['available']) {
                $out[$item['slug']] = $item;
            }
        }

        return $out;
    }
}
