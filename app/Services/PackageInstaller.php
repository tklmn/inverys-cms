<?php

namespace App\Services;

use App\Services\Updates\PackageStore;
use App\Services\Updates\UpdateException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use ZipArchive;

class PackageInstaller
{
    /** File extensions that are never allowed inside a package ZIP. */
    private const BLOCKED_EXTENSIONS = [
        'phar', 'sh', 'bash', 'bat', 'cmd', 'exe', 'com', 'msi', 'dll', 'so',
    ];

    /** Filenames that are never allowed inside a package ZIP. */
    private const BLOCKED_FILES = [
        '.htaccess', '.htpasswd', '.env', 'web.config',
    ];

    /**
     * Install a theme from an uploaded ZIP file.
     *
     * @return array{success: bool, slug?: string, error?: string}
     */
    public function installTheme(UploadedFile $file): array
    {
        return $this->install($file, 'theme');
    }

    /**
     * Install a plugin from an uploaded ZIP file.
     *
     * @return array{success: bool, slug?: string, error?: string}
     */
    public function installPlugin(UploadedFile $file): array
    {
        return $this->install($file, 'plugin');
    }

    /**
     * @return array{success: bool, slug?: string, error?: string}
     */
    private function install(UploadedFile $file, string $type): array
    {
        return $this->installFromPath((string) $file->getRealPath(), $type);
    }

    /**
     * Check the ZIP, extract it and move it into place. If the package already
     * exists, it is an update: older versions are rejected, and the old
     * folder is backed up (PackageStore) instead of deleted.
     *
     * @return array{success: bool, slug?: string, version?: string, updated?: bool, error?: string}
     */
    public function installFromPath(string $zipPath, string $type, ?string $expectedSlug = null): array
    {
        try {
            $package = $this->extract($zipPath, $type);
        } catch (UpdateException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $slug = $package['manifest']['slug'];
            $version = (string) ($package['manifest']['version'] ?? '1.0.0');

            if ($expectedSlug !== null && $slug !== $expectedSlug) {
                return ['success' => false, 'error' => __('translation.updates.error_slug', ['slug' => $slug, 'expected' => $expectedSlug])];
            }

            $installed = $this->installedVersion($type, $slug);
            if ($installed !== null && version_compare($version, $installed, '<')) {
                return ['success' => false, 'error' => __('translation.updates.error_downgrade', ['version' => $version, 'installed' => $installed])];
            }

            app(PackageStore::class)->replace($type, $slug, $package['root'], $installed, $version);

            return ['success' => true, 'slug' => $slug, 'version' => $version, 'updated' => $installed !== null];
        } catch (UpdateException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            // Always clean up the temp directory
            if (is_dir($package['tmp'])) {
                File::deleteDirectory($package['tmp']);
            }
        }
    }

    /**
     * Check the ZIP and extract it into a temp folder.
     *
     * @return array{tmp: string, root: string, manifest: array<string, mixed>}
     */
    public function extract(string $zipPath, string $type): array
    {
        $manifestFile = $type === 'theme' ? 'theme.json' : 'plugin.json';

        // 1. Open the ZIP
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new UpdateException(__('Could not open ZIP file.'));
        }

        // 2. Security scan — check every entry before extracting
        $securityCheck = $this->scanZip($zip);
        if ($securityCheck !== null) {
            $zip->close();

            throw new UpdateException($securityCheck);
        }

        // 3. Extract to a temporary directory
        $tmpDir = storage_path('app/tmp-package-'.uniqid());
        $zip->extractTo($tmpDir);
        $zip->close();

        try {
            // 4. Find the manifest — either at root or one level deep (ZIP may contain a wrapper dir)
            $manifestPath = $this->findManifest($tmpDir, $manifestFile);
            if (! $manifestPath) {
                throw new UpdateException(__('Missing :file in the uploaded package.', ['file' => $manifestFile]));
            }

            $manifest = json_decode(File::get($manifestPath), true);
            if (! is_array($manifest) || empty($manifest['slug']) || ! is_string($manifest['slug'])) {
                throw new UpdateException(__('Invalid :file — "slug" field is required.', ['file' => $manifestFile]));
            }

            // 5. Validate slug format (alphanumeric + hyphens only)
            $slug = $manifest['slug'];
            if (! preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', $slug)) {
                throw new UpdateException(__('Invalid slug ":slug". Use lowercase letters, numbers, and hyphens only.', ['slug' => $slug]));
            }

            // 6. Plugin-specific: must have a provider class
            if ($type === 'plugin' && empty($manifest['provider'])) {
                throw new UpdateException(__('Invalid plugin.json — "provider" field is required.'));
            }
        } catch (UpdateException $e) {
            File::deleteDirectory($tmpDir);

            throw $e;
        }

        return ['tmp' => $tmpDir, 'root' => dirname($manifestPath), 'manifest' => $manifest];
    }

    /** Version of the installed package (from its manifest) or null */
    private function installedVersion(string $type, string $slug): ?string
    {
        $manifest = (rtrim((string) config($type === 'theme' ? 'cms.themes_path' : 'cms.plugins_path'), '/')).'/'.$slug.'/'.($type === 'theme' ? 'theme.json' : 'plugin.json');

        if (! is_file($manifest)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($manifest), true);

        return is_array($data) ? (string) ($data['version'] ?? '1.0.0') : '1.0.0';
    }

    /**
     * Scan every entry in the ZIP for security issues.
     * Returns an error message string on failure, null on success.
     */
    private function scanZip(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            // Path traversal
            if (str_contains($entry, '..') || str_starts_with($entry, '/')) {
                return __('Blocked: ZIP contains a path traversal entry (:entry).', ['entry' => $entry]);
            }

            // Blocked filenames
            $basename = basename($entry);
            if (in_array(strtolower($basename), self::BLOCKED_FILES, true)) {
                return __('Blocked: ZIP contains a forbidden file (:file).', ['file' => $basename]);
            }

            // Blocked extensions
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                return __('Blocked: ZIP contains a file with forbidden extension (:file).', ['file' => $basename]);
            }
        }

        return null;
    }

    /**
     * Find the manifest file at the ZIP root or one directory level deep.
     */
    private function findManifest(string $dir, string $filename): ?string
    {
        // Direct at root
        if (File::exists($dir.'/'.$filename)) {
            return $dir.'/'.$filename;
        }

        // One level deep (common when ZIP contains a single wrapper folder)
        foreach (File::directories($dir) as $subdir) {
            if (File::exists($subdir.'/'.$filename)) {
                return $subdir.'/'.$filename;
            }
        }

        return null;
    }
}
