<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Services\PackageInstaller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Bring a plugin or theme to the latest version from its source.
 *
 * Downloads the release, validates it like an upload (PackageInstaller) and swaps
 * the folder with a backup (PackageStore). Only for packages that live as
 * folders under plugins/ or themes/ and are not their own Git checkout.
 */
final class PackageUpdater
{
    // After the swap the next request shows the new version as
    // installed — the cached check does not need to run again for that
    public function __construct(
        private readonly UpdateChecker $checker,
        private readonly GitHubReleases $releases,
        private readonly PackageInstaller $installer,
        private readonly PackageStore $store,
    ) {}

    /** @return array{name: string, from: string, to: string} */
    public function update(string $type, string $slug): array
    {
        $package = $this->find($type, $slug);

        if ($package['manager'] !== 'zip') {
            throw new UpdateException(self::reason($package['manager']));
        }

        $lock = Cache::lock('cms.updates.package.'.$type.'.'.$slug, 300);
        if (! $lock->get()) {
            throw new UpdateException(__('translation.updates.error_busy'));
        }

        $zip = storage_path('app/tmp-update-'.uniqid().'.zip');

        try {
            // Always query fresh — the cached state can be a day old
            $release = $this->releases->latest((string) $package['repository']);

            if (! version_compare($release['version'], $package['installed'], '>')) {
                throw new UpdateException(__('translation.updates.error_current', ['name' => $package['name']]));
            }

            $this->releases->download($release, $zip);
            $result = $this->installer->installFromPath($zip, $type, $slug);

            if (! $result['success']) {
                throw new UpdateException((string) $result['error']);
            }

            return ['name' => $package['name'], 'from' => $package['installed'], 'to' => (string) $result['version']];
        } finally {
            File::delete($zip);
            $lock->release();
        }
    }

    /** Restore the previous state */
    public function restore(string $type, string $slug): array
    {
        $package = $this->find($type, $slug);

        $version = $this->store->restore($type, $slug, $package['installed']);

        return ['name' => $package['name'], 'version' => $version];
    }

    /** Why a package is not updated via the update page */
    public static function reason(string $manager): string
    {
        return match ($manager) {
            'git' => __('translation.updates.reason_git'),
            'composer' => __('translation.updates.reason_composer'),
            default => __('translation.updates.reason_none'),
        };
    }

    private function find(string $type, string $slug): array
    {
        foreach ($this->checker->packages() as $package) {
            if ($package['type'] === $type && $package['slug'] === $slug) {
                return $package;
            }
        }

        throw new UpdateException(__('translation.updates.error_unknown'));
    }
}
