<?php

declare(strict_types=1);

namespace App\Support\Sbom;

/**
 * Builds a CycloneDX 1.5 document (JSON) for Inverys CMS.
 *
 * One place for both SBOMs:
 *
 *  - the release SBOM (`sbom/generate-composer-sbom.php`, reads composer.lock
 *    and the bundled plugins/themes from git),
 *  - the installation SBOM in the backend (`Inventory`, reads
 *    vendor/composer/installed.json and everything present on the server).
 *
 * Deliberately free of Laravel dependencies: the script loads this file
 * directly so it also runs without an autoloader. Order and keys of the
 * output are part of the format — given the same inputs, the release SBOM
 * must stay identical byte for byte (stable diffs).
 */
final class CycloneDx
{
    public const SPEC_VERSION = '1.5';

    public const REPOSITORY_URL = 'https://github.com/tklmn/inverys-cms';

    /** @var list<array<string, mixed>> */
    private array $components = [];

    /** @var array<string, string> package name (lowercase) → bom-ref */
    private array $refsByName = [];

    /** @var array<string, list<string>> bom-ref → required package names */
    private array $requiresByRef = [];

    /** @var list<string> plugins/themes outside of Composer */
    private array $extensionRefs = [];

    /** @var list<string> further components without dependencies of their own (platform) */
    private array $extraRefs = [];

    /**
     * @param  list<string>  $licenses  SPDX identifiers from composer.json
     */
    public function __construct(
        private readonly string $rootName,
        private readonly string $version,
        private readonly array $licenses = [],
        private readonly ?string $description = null,
    ) {}

    public function rootRef(): string
    {
        return 'pkg:composer/'.$this->rootName.'@'.$this->version;
    }

    /**
     * Licenses in CycloneDX format: SPDX expressions ("MIT OR GPL-2.0") as
     * "expression", plain identifiers as "license.id".
     *
     * @param  array<int, mixed>  $ids
     * @return list<array<string, mixed>>
     */
    public static function licenseList(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $out[] = preg_match('/\s(OR|AND|WITH)\s|[()]/', $id)
                ? ['expression' => $id]
                : ['license' => ['id' => $id]];
        }

        return $out;
    }

    /** PHP itself, extensions and Composer APIs are not packages */
    public static function isPlatform(string $name): bool
    {
        return $name === 'php' || str_starts_with($name, 'ext-')
            || str_starts_with($name, 'lib-') || $name === 'composer-runtime-api' || $name === 'composer-plugin-api';
    }

    /** Version without a leading "v" (Composer writes "v7.1.0") */
    public static function normalizeVersion(mixed $version): string
    {
        return ltrim((string) ($version ?? '0.0.0'), 'v');
    }

    public static function purl(string $name, string $version): string
    {
        return 'pkg:composer/'.$name.'@'.$version;
    }

    /** Random UUID v4 for serialNumber */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * A Composer package (entry from composer.lock or installed.json).
     *
     * @param  array<string, mixed>  $pkg
     * @param  string  $scope  "required" (runtime) or "optional" (development)
     * @param  list<array{name: string, value: string}>  $properties  additional details (installation SBOM only)
     */
    public function addComposerPackage(array $pkg, string $scope, array $properties = []): void
    {
        $name = $pkg['name'] ?? null;
        if (! $name) {
            return;
        }
        $ver = self::normalizeVersion($pkg['version'] ?? '0.0.0');
        $purl = self::purl($name, $ver);

        $component = [
            'bom-ref' => $purl,
            'type' => 'library',
            'name' => $name,
            'version' => $ver,
            'purl' => $purl,
            'scope' => $scope,
        ];

        $authors = array_values(array_filter(array_map(
            fn ($a) => is_array($a) && ! empty($a['name']) ? trim($a['name'].(! empty($a['email']) ? ' <'.$a['email'].'>' : '')) : null,
            (array) ($pkg['authors'] ?? [])
        )));
        if ($authors) {
            $component['author'] = implode(', ', $authors);
        }
        if (! empty($pkg['description'])) {
            $component['description'] = $pkg['description'];
        }
        if ($licenses = self::licenseList((array) ($pkg['license'] ?? []))) {
            $component['licenses'] = $licenses;
        }

        // Composer leaves "shasum" empty for GitHub zipballs — take it over when set
        if (! empty($pkg['dist']['shasum'])) {
            $component['hashes'] = [['alg' => 'SHA-1', 'content' => $pkg['dist']['shasum']]];
        }

        $refs = [];
        if (! empty($pkg['source']['url'])) {
            $ref = ['type' => 'vcs', 'url' => $pkg['source']['url']];
            if (! empty($pkg['source']['reference'])) {
                $ref['comment'] = 'commit '.$pkg['source']['reference'];
            }
            $refs[] = $ref;
        }
        if (! empty($pkg['dist']['url'])) {
            $refs[] = ['type' => 'distribution', 'url' => $pkg['dist']['url']];
        }
        if (! empty($pkg['homepage'])) {
            $refs[] = ['type' => 'website', 'url' => $pkg['homepage']];
        }
        if ($refs) {
            $component['externalReferences'] = $refs;
        }
        if ($properties) {
            $component['properties'] = $properties;
        }

        $this->components[] = $component;
        $this->refsByName[strtolower($name)] = $purl;
        $this->requiresByRef[$purl] = array_values(array_filter(
            array_keys((array) ($pkg['require'] ?? [])),
            fn ($dep) => ! self::isPlatform((string) $dep)
        ));

        // Package replaces others (e.g. symfony/polyfill-*): resolve dependencies on them to it
        foreach (array_keys((array) ($pkg['replace'] ?? [])) as $replaced) {
            $this->refsByName[strtolower((string) $replaced)] ??= $purl;
        }
    }

    /**
     * A plugin or theme from plugins/ or themes/ (not via Composer).
     *
     * @param  'plugin'|'theme'  $kind
     * @param  array<string, mixed>  $manifest  plugin.json / theme.json
     * @param  string  $path  relative folder, e.g. "plugins/blog"
     * @param  string  $origin  word in the description: "bundled" (release) or "installed" (installation)
     * @param  list<string>|null  $licenses  null = license from the manifest, or none
     * @param  list<array{name: string, value: string}>  $properties  additional details after inverys:type/-path
     */
    public function addExtension(string $kind, array $manifest, string $path, string $origin = 'bundled', ?array $licenses = null, array $properties = []): ?string
    {
        if (empty($manifest['slug'])) {
            return null;
        }

        $ver = (string) ($manifest['version'] ?? $this->version);
        $ref = 'inverys-'.$kind.':'.$manifest['slug'].'@'.$ver;

        $component = [
            'bom-ref' => $ref,
            'type' => 'library',
            'name' => 'inverys-'.$kind.'-'.$manifest['slug'],
            'version' => $ver,
            'scope' => 'required',
            'description' => trim(($manifest['name'] ?? $manifest['slug']).' — '.$origin.' '.$kind.' ('.$path.'/). '.($manifest['description'] ?? '')),
        ];
        if (! empty($manifest['author'])) {
            $component['author'] = (string) $manifest['author'];
        }
        if ($list = self::licenseList((array) ($manifest['license'] ?? $licenses ?? []))) {
            $component['licenses'] = $list;
        }
        $component['properties'] = array_merge([
            ['name' => 'inverys:type', 'value' => $kind],
            ['name' => 'inverys:path', 'value' => $path],
        ], $properties);

        $this->components[] = $component;
        $this->extensionRefs[] = $ref;

        return $ref;
    }

    /**
     * Any further component (e.g. the PHP runtime as "platform").
     * The CMS root depends on it; it has no dependencies itself.
     *
     * @param  array<string, mixed>  $component  must contain "bom-ref"
     */
    public function addComponent(array $component): void
    {
        $this->components[] = $component;
        $this->extraRefs[] = (string) $component['bom-ref'];
    }

    /**
     * The finished document.
     *
     * @param  list<string>  $rootRequires  package names from composer.json "require"
     * @param  array{timestamp?: string, serial?: string, phase?: string, tool?: string, properties?: list<array{name: string, value: string}>}  $options
     * @return array<string, mixed>
     */
    public function build(array $rootRequires, array $options = []): array
    {
        $components = $this->components;
        // Fixed order so that repeated runs produce stable diffs
        usort($components, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $rootRef = $this->rootRef();
        $rootDepends = array_merge($this->resolve($rootRequires), $this->extensionRefs, $this->extraRefs);
        sort($rootDepends);

        $dependencies = [['ref' => $rootRef, 'dependsOn' => array_values(array_unique($rootDepends))]];
        foreach ($this->requiresByRef as $ref => $requires) {
            $dependencies[] = ['ref' => $ref, 'dependsOn' => $this->resolve($requires)];
        }
        foreach (array_merge($this->extensionRefs, $this->extraRefs) as $ref) {
            $dependencies[] = ['ref' => $ref, 'dependsOn' => []];
        }
        usort($dependencies, fn ($a, $b) => strcmp($a['ref'], $b['ref']));

        $rootComponent = [
            'bom-ref' => $rootRef,
            'type' => 'application',
            'name' => $this->rootName,
            'version' => $this->version,
            'purl' => $rootRef,
        ];
        if (! empty($this->description)) {
            $rootComponent['description'] = $this->description;
        }
        if ($licenses = self::licenseList($this->licenses)) {
            $rootComponent['licenses'] = $licenses;
        }
        $rootComponent['externalReferences'] = [
            ['type' => 'vcs', 'url' => self::REPOSITORY_URL],
            ['type' => 'issue-tracker', 'url' => self::REPOSITORY_URL.'/issues'],
            ['type' => 'security-contact', 'url' => self::REPOSITORY_URL.'/security/policy'],
        ];

        $metadata = [
            'timestamp' => $options['timestamp'] ?? gmdate('Y-m-d\TH:i:s\Z'),
            'lifecycles' => [['phase' => $options['phase'] ?? 'build']],
            'tools' => [
                'components' => [
                    ['type' => 'application', 'name' => $options['tool'] ?? 'inverys-cms sbom/generate-composer-sbom.php', 'version' => $this->version],
                ],
            ],
            'component' => $rootComponent,
        ];
        if (! empty($options['properties'])) {
            $metadata['properties'] = $options['properties'];
        }

        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => self::SPEC_VERSION,
            'serialNumber' => 'urn:uuid:'.($options['serial'] ?? self::uuid()),
            'version' => 1,
            'metadata' => $metadata,
            'components' => $components,
            'dependencies' => $dependencies,
        ];
    }

    /** @param array<string, mixed> $bom */
    public static function encode(array $bom): string
    {
        return json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Package names → bom-refs (platform requirements and unknown names are dropped).
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function resolve(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $name = (string) $name;
            if (self::isPlatform($name)) {
                continue;
            }
            if (isset($this->refsByName[strtolower($name)])) {
                $out[] = $this->refsByName[strtolower($name)];
            }
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}
