<?php

declare(strict_types=1);

namespace App\Support\Sbom;

use App\Models\Plugin;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use Illuminate\Support\Str;

/**
 * What is actually installed on this server (System → Components).
 *
 * Sources:
 *  - vendor/composer/installed.json — the installed Composer packages, with
 *    development packages flagged (`dev-package-names`). Unlike composer.lock,
 *    this shows whether the install used `--no-dev`.
 *  - PluginManager / ThemeManager — all discovered plugins and themes, including
 *    uploaded ones; activation from the `plugins` table or the active theme.
 *  - the PHP runtime with its loaded extensions (platform).
 *
 * npm packages are not included: they only build the assets and are not present
 * on the server. They appear only in the release SBOM (sbom/npm.cdx.json).
 */
final class Inventory
{
    public const KIND_LIBRARY = 'library';

    public const KIND_DEV = 'dev';

    public const KIND_PLUGIN = 'plugin';

    public const KIND_THEME = 'theme';

    /** Order of the groups in the list */
    public const KINDS = [self::KIND_PLUGIN, self::KIND_THEME, self::KIND_LIBRARY, self::KIND_DEV];

    /** Bundled release SBOMs (bin/release.sh), relative to the project */
    public const RELEASE_FILES = [
        'composer' => 'sbom/composer.cdx.json',
        'npm' => 'sbom/npm.cdx.json',
    ];

    /** Composer types that represent a plugin or theme */
    private const EXTENSION_TYPES = ['inverys-plugin' => self::KIND_PLUGIN, 'inverys-theme' => self::KIND_THEME];

    private ?array $composer = null;

    private ?array $components = null;

    private ?array $releaseBundled = null;

    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly ThemeManager $themeManager,
        private readonly ?string $installedPath = null,
        private readonly ?string $basePath = null,
    ) {}

    /**
     * Installed Composer packages.
     *
     * @return array{packages: list<array<string, mixed>>, dev_names: array<string, true>, dev_mode: bool, readable: bool}
     */
    public function composer(): array
    {
        if ($this->composer !== null) {
            return $this->composer;
        }

        $path = $this->installedPath ?? $this->path('vendor/composer/installed.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($data)) {
            return $this->composer = ['packages' => [], 'dev_names' => [], 'dev_mode' => false, 'readable' => false];
        }

        // Composer 2: {packages, dev, dev-package-names}; Composer 1: just the list
        $packages = array_values(array_filter($data['packages'] ?? (array_is_list($data) ? $data : []), 'is_array'));
        $devNames = array_fill_keys(array_map('strtolower', (array) ($data['dev-package-names'] ?? [])), true);

        return $this->composer = [
            'packages' => $packages,
            'dev_names' => $devNames,
            'dev_mode' => (bool) ($data['dev'] ?? $devNames !== []),
            'readable' => true,
        ];
    }

    /**
     * All components for the list, grouped by KINDS.
     *
     * A plugin/theme that came via Composer appears only once — as a plugin
     * or theme with its Composer name, not additionally as a library.
     *
     * @return list<array<string, mixed>>
     */
    public function components(): array
    {
        if ($this->components !== null) {
            return $this->components;
        }

        $composer = $this->composer();
        $plugins = $this->pluginStates();
        $activeTheme = $this->activeTheme();
        $pluginManifests = $this->pluginManager->all();
        $themeManifests = $this->themeManager->all();

        $items = [];
        $composerSlugs = [self::KIND_PLUGIN => [], self::KIND_THEME => []];

        foreach ($composer['packages'] as $pkg) {
            $name = (string) ($pkg['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $version = CycloneDx::normalizeVersion($pkg['version'] ?? '0.0.0');
            $dev = isset($composer['dev_names'][strtolower($name)]);
            $kind = self::EXTENSION_TYPES[$pkg['type'] ?? ''] ?? ($dev ? self::KIND_DEV : self::KIND_LIBRARY);

            $item = [
                'kind' => $kind,
                'name' => $name,
                'package' => $name,
                'slug' => null,
                'version' => $version,
                'licenses' => $this->licenseIds($pkg['license'] ?? []),
                'description' => (string) ($pkg['description'] ?? ''),
                'url' => $this->sourceUrl($pkg),
                'commit' => $pkg['source']['reference'] ?? null,
                'purl' => CycloneDx::purl($name, $version),
                'path' => null,
                'dev' => $dev,
                'state' => null,
                'composer' => true,
            ];

            if ($kind === self::KIND_PLUGIN || $kind === self::KIND_THEME) {
                $slug = (string) ($pkg['extra']['inverys']['slug'] ?? basename($name));
                $composerSlugs[$kind][$slug] = true;
                $manifest = $kind === self::KIND_PLUGIN ? ($pluginManifests[$slug] ?? []) : ($themeManifests[$slug] ?? []);

                $item['slug'] = $slug;
                $item['name'] = (string) ($manifest['name'] ?? $name);
                $item['path'] = isset($manifest['path']) ? $this->relative((string) $manifest['path']) : null;
                $item['state'] = $kind === self::KIND_PLUGIN
                    ? (($plugins[$slug] ?? false) ? 'enabled' : 'disabled')
                    : ($slug === $activeTheme ? 'active' : 'inactive');
            }

            $items[] = $item;
        }

        foreach ([self::KIND_PLUGIN => $pluginManifests, self::KIND_THEME => $themeManifests] as $kind => $manifests) {
            foreach ($manifests as $slug => $manifest) {
                if (isset($composerSlugs[$kind][$slug])) {
                    continue;
                }

                $items[] = [
                    'kind' => $kind,
                    'name' => (string) ($manifest['name'] ?? $slug),
                    'package' => 'inverys-'.$kind.'-'.$slug,
                    'slug' => (string) $slug,
                    'version' => (string) ($manifest['version'] ?? config('cms.version')),
                    'licenses' => $this->extensionLicenses($manifest),
                    'description' => (string) ($manifest['description'] ?? ''),
                    'url' => $this->manifestUrl($manifest),
                    'commit' => null,
                    'purl' => null,
                    'path' => $this->relative((string) ($manifest['path'] ?? '')),
                    'dev' => false,
                    'state' => $kind === self::KIND_PLUGIN
                        ? (($plugins[$slug] ?? false) ? 'enabled' : 'disabled')
                        : ($slug === $activeTheme ? 'active' : 'inactive'),
                    'composer' => false,
                ];
            }
        }

        $order = array_flip(self::KINDS);
        usort($items, fn ($a, $b) => [$order[$a['kind']], strtolower($a['name'])] <=> [$order[$b['kind']], strtolower($b['name'])]);

        return $this->components = $items;
    }

    /**
     * Key figures for the page header and dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $counts = array_fill_keys(self::KINDS, 0);
        $pluginsEnabled = 0;
        $activeTheme = null;

        foreach ($this->components() as $item) {
            $counts[$item['kind']]++;
            if ($item['state'] === 'enabled') {
                $pluginsEnabled++;
            }
            if ($item['state'] === 'active') {
                $activeTheme = $item['name'];
            }
        }

        return [
            'cms_version' => (string) config('cms.version'),
            'counts' => $counts,
            // What can be loaded at runtime — without development packages
            'runtime_total' => $counts[self::KIND_LIBRARY] + $counts[self::KIND_PLUGIN] + $counts[self::KIND_THEME],
            'plugins_enabled' => $pluginsEnabled,
            'active_theme' => $activeTheme,
            'dev_mode' => $this->composer()['dev_mode'],
            'readable' => $this->composer()['readable'],
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_extensions' => count(get_loaded_extensions()),
        ];
    }

    /**
     * The bundled release SBOMs (if present) with key data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function releaseSboms(): array
    {
        $out = [];
        foreach (self::RELEASE_FILES as $key => $relative) {
            $path = $this->path($relative);
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $valid = is_array($data) && ($data['bomFormat'] ?? null) === 'CycloneDX';

            $version = $valid ? (string) ($data['metadata']['component']['version'] ?? '') : null;
            $out[$key] = [
                'key' => $key,
                'file' => $relative,
                'path' => $path,
                'exists' => $valid,
                'version' => $version,
                'timestamp' => $valid ? ($data['metadata']['timestamp'] ?? null) : null,
                'count' => $valid ? count((array) ($data['components'] ?? [])) : 0,
                'matches' => $valid && $version === (string) config('cms.version'),
            ];
        }

        return $out;
    }

    /**
     * CycloneDX 1.5 SBOM of this installation.
     *
     * @return array<string, mixed>
     */
    public function cycloneDx(?string $host = null): array
    {
        $composerJson = $this->composerJson();
        $rootLicenses = (array) ($composerJson['license'] ?? []);
        $version = (string) config('cms.version');

        $bom = new CycloneDx(
            (string) ($composerJson['name'] ?? 'inverys/cms'),
            $version,
            $rootLicenses,
            $composerJson['description'] ?? null,
        );

        $composer = $this->composer();
        $byPackage = [];
        foreach ($this->components() as $item) {
            $byPackage[$item['package']] = $item;
        }

        foreach ($composer['packages'] as $pkg) {
            $name = (string) ($pkg['name'] ?? '');
            $item = $byPackage[$name] ?? null;
            $properties = [];
            if ($item && $item['slug'] !== null) {
                $properties = array_merge([
                    ['name' => 'inverys:type', 'value' => $item['kind']],
                    ['name' => 'inverys:slug', 'value' => $item['slug']],
                ], $this->stateProperties($item));
            }
            $bom->addComposerPackage($pkg, isset($composer['dev_names'][strtolower($name)]) ? 'optional' : 'required', $properties);
        }

        foreach ($this->components() as $item) {
            if ($item['composer'] || ! in_array($item['kind'], [self::KIND_PLUGIN, self::KIND_THEME], true)) {
                continue;
            }
            $manifest = ($item['kind'] === self::KIND_PLUGIN ? $this->pluginManager->get($item['slug']) : $this->themeManager->get($item['slug'])) ?? [];
            $bom->addExtension(
                $item['kind'],
                $manifest + ['slug' => $item['slug']],
                $item['path'] ?: $item['kind'].'s/'.$item['slug'],
                'installed',
                $item['licenses'],
                $this->stateProperties($item),
            );
        }

        $bom->addComponent($this->phpComponent());

        $properties = [['name' => 'inverys:sbom', 'value' => 'installation']];
        if ($host) {
            $properties[] = ['name' => 'inverys:host', 'value' => $host];
        }
        $properties[] = ['name' => 'inverys:composer-dev-mode', 'value' => $composer['dev_mode'] ? 'true' : 'false'];

        return $bom->build(array_keys((array) ($composerJson['require'] ?? [])), [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'phase' => 'operations',
            'tool' => 'inverys-cms backend (System info → Components)',
            'properties' => $properties,
        ]);
    }

    /**
     * Search text of a component — the basis for the filter in the controller
     * (?q=) and for data-filter-item in the view, so both find the same things.
     */
    public static function searchText(array $item): string
    {
        return Str::lower(implode(' ', array_filter([
            $item['name'],
            $item['slug'] !== null ? __($item['name']) : null,
            $item['package'],
            $item['version'],
            implode(' ', $item['licenses']),
            __('translation.system.sbom.kinds.'.$item['kind']),
        ])));
    }

    /** File name for the download: inverys-cms-{version}-{host}-sbom.cdx.json */
    public static function fileName(string $host): string
    {
        $host = trim((string) preg_replace('/[^a-z0-9.-]+/', '-', strtolower($host)), '-.') ?: 'localhost';

        return 'inverys-cms-'.config('cms.version').'-'.$host.'-sbom.cdx.json';
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** @return array<string, bool> slug → enabled */
    private function pluginStates(): array
    {
        try {
            // Deleted ones (trash) count as inactive, but their files are still there
            return Plugin::query()->pluck('is_enabled', 'slug')->map(fn ($v) => (bool) $v)->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function activeTheme(): string
    {
        return (string) ($this->themeManager->getActive() ?? config('cms.active_theme', 'default'));
    }

    /** @return list<array{name: string, value: string}> */
    private function stateProperties(array $item): array
    {
        return match ($item['state']) {
            'enabled', 'disabled' => [['name' => 'inverys:enabled', 'value' => $item['state'] === 'enabled' ? 'true' : 'false']],
            'active', 'inactive' => [['name' => 'inverys:active', 'value' => $item['state'] === 'active' ? 'true' : 'false']],
            default => [],
        };
    }

    /**
     * PHP runtime as a platform component; the loaded extensions as a
     * property, so scanners can match the PHP version via CPE.
     *
     * @return array<string, mixed>
     */
    private function phpComponent(): array
    {
        $release = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION;
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'bom-ref' => 'pkg:generic/php@'.$release,
            'type' => 'platform',
            'name' => 'php',
            'version' => PHP_VERSION,
            'purl' => 'pkg:generic/php@'.$release,
            'cpe' => 'cpe:2.3:a:php:php:'.$release.':*:*:*:*:*:*:*',
            'scope' => 'required',
            'properties' => [
                ['name' => 'inverys:php-sapi', 'value' => PHP_SAPI],
                ['name' => 'inverys:php-extensions', 'value' => implode(', ', $extensions)],
            ],
        ];
    }

    /** @return list<string> */
    private function licenseIds(mixed $licenses): array
    {
        return array_values(array_filter(array_map(fn ($l) => trim((string) $l), (array) $licenses), fn ($l) => $l !== ''));
    }

    /**
     * License of a plugin/theme: from the manifest; without one, the CMS
     * license applies only to what the release itself ships — for uploaded
     * packages that would be an unfounded claim.
     *
     * @return list<string>
     */
    private function extensionLicenses(array $manifest): array
    {
        if (! empty($manifest['license'])) {
            return $this->licenseIds($manifest['license']);
        }

        $path = $this->relative((string) ($manifest['path'] ?? ''));
        if ($path !== '' && isset($this->releaseBundled()[$path])) {
            return $this->licenseIds($this->composerJson()['license'] ?? []);
        }

        return [];
    }

    /** @return array<string, true> folders of the plugins/themes listed in the release SBOM */
    private function releaseBundled(): array
    {
        if ($this->releaseBundled !== null) {
            return $this->releaseBundled;
        }

        $data = json_decode((string) @file_get_contents($this->path(self::RELEASE_FILES['composer'])), true);
        $paths = [];
        foreach ((array) ($data['components'] ?? []) as $component) {
            foreach ((array) ($component['properties'] ?? []) as $property) {
                if (($property['name'] ?? null) === 'inverys:path') {
                    $paths[(string) $property['value']] = true;
                }
            }
        }

        return $this->releaseBundled = $paths;
    }

    private function sourceUrl(array $pkg): ?string
    {
        $candidates = [
            $pkg['support']['source'] ?? null,
            $pkg['homepage'] ?? null,
            isset($pkg['source']['url']) ? preg_replace('/\.git$/', '', (string) $pkg['source']['url']) : null,
        ];

        foreach ($candidates as $url) {
            if (is_string($url) && preg_match('~^https?://~i', $url)) {
                return $url;
            }
        }

        return null;
    }

    private function manifestUrl(array $manifest): ?string
    {
        foreach (['homepage', 'url', 'source'] as $key) {
            $url = $manifest[$key] ?? null;
            if (is_string($url) && preg_match('~^https?://~i', $url)) {
                return $url;
            }
        }

        return null;
    }

    private function composerJson(): array
    {
        return json_decode((string) @file_get_contents($this->path('composer.json')), true) ?: [];
    }

    private function path(string $relative): string
    {
        return rtrim($this->basePath ?? base_path(), '/').'/'.$relative;
    }

    private function relative(string $path): string
    {
        $base = rtrim($this->basePath ?? base_path(), '/').'/';

        return str_starts_with($path, $base) ? Str::after($path, $base) : $path;
    }
}
