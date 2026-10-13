<?php

namespace Tests\Unit\Support;

use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Support\Sbom\CycloneDx;
use App\Support\Sbom\Inventory;
use Tests\TestCase;

/**
 * Bill of materials of the installation (System → Components): reads
 * installed.json correctly and builds a valid CycloneDX 1.5 document from it.
 */
class SbomInventoryTest extends TestCase
{
    private function inventory(): Inventory
    {
        return new Inventory(
            app(PluginManager::class),
            app(ThemeManager::class),
            base_path('tests/Fixtures/sbom/installed.json'),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function byPackage(): array
    {
        return collect($this->inventory()->components())->keyBy('package')->all();
    }

    public function test_reads_packages_versions_and_licences_from_installed_json(): void
    {
        $items = $this->byPackage();

        $this->assertSame('library', $items['monolog/monolog']['kind']);
        // Leading "v" removed as in the release SBOM
        $this->assertSame('3.9.0', $items['monolog/monolog']['version']);
        $this->assertSame(['MIT'], $items['monolog/monolog']['licenses']);
        $this->assertSame('https://github.com/Seldaek/monolog', $items['monolog/monolog']['url']);
        $this->assertSame('10d85740180ecba7896c87e06a166e0c95a0e3b6', $items['monolog/monolog']['commit']);
        $this->assertSame('pkg:composer/monolog/monolog@3.9.0', $items['monolog/monolog']['purl']);

        $this->assertSame(['BSD-3-Clause', 'GPL-2.0-only', 'GPL-3.0-only'], $items['nette/utils']['licenses']);
        // support.source takes precedence over homepage and VCS URL
        $this->assertSame('https://github.com/nette/utils/tree/v4.0.8', $items['nette/utils']['url']);
        // No source without an https URL (the button is disabled)
        $this->assertNull($items['symfony/polyfill-mbstring']['url']);
    }

    public function test_separates_dev_packages_via_dev_package_names(): void
    {
        $items = $this->byPackage();

        $this->assertSame('dev', $items['phpunit/phpunit']['kind']);
        $this->assertTrue($items['phpunit/phpunit']['dev']);
        $this->assertFalse($items['monolog/monolog']['dev']);

        $summary = $this->inventory()->summary();
        $this->assertSame(1, $summary['counts']['dev']);
        $this->assertSame(4, $summary['counts']['library']);
        $this->assertTrue($summary['dev_mode']);
        $this->assertTrue($summary['readable']);
    }

    public function test_composer_plugin_is_listed_once_as_plugin(): void
    {
        $plugin = $this->byPackage()['acme/fixture-plugin'];

        $this->assertSame('plugin', $plugin['kind']);
        $this->assertSame('fixture-plugin', $plugin['slug']);
        $this->assertTrue($plugin['composer']);
        // Not in the plugins table → not active
        $this->assertSame('disabled', $plugin['state']);
        $this->assertSame(['GPL-2.0-or-later OR MIT'], $plugin['licenses']);

        $matches = array_filter($this->inventory()->components(), fn ($i) => $i['slug'] === 'fixture-plugin');
        $this->assertCount(1, $matches);
    }

    public function test_bundled_plugins_and_themes_come_from_their_folders(): void
    {
        $items = $this->byPackage();

        $this->assertSame('plugin', $items['inverys-plugin-blog']['kind']);
        $this->assertSame('plugins/blog', $items['inverys-plugin-blog']['path']);
        $this->assertFalse($items['inverys-plugin-blog']['composer']);
        $this->assertSame('theme', $items['inverys-theme-default']['kind']);
        $this->assertContains($items['inverys-theme-default']['state'], ['active', 'inactive']);
    }

    public function test_unreadable_installed_json_yields_no_packages(): void
    {
        $inventory = new Inventory(app(PluginManager::class), app(ThemeManager::class), base_path('tests/Fixtures/sbom/missing.json'));

        $this->assertFalse($inventory->summary()['readable']);
        $this->assertSame(0, $inventory->summary()['counts']['library']);
    }

    public function test_cyclonedx_document_has_expected_structure(): void
    {
        $bom = $this->inventory()->cycloneDx('example.test');

        $this->assertSame('CycloneDX', $bom['bomFormat']);
        $this->assertSame('1.5', $bom['specVersion']);
        $this->assertMatchesRegularExpression('/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $bom['serialNumber']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $bom['metadata']['timestamp']);
        $this->assertSame('operations', $bom['metadata']['lifecycles'][0]['phase']);
        $this->assertSame(config('cms.version'), $bom['metadata']['component']['version']);
        $this->assertSame('pkg:composer/inverys/cms@'.config('cms.version'), $bom['metadata']['component']['purl']);
        $this->assertContains(['name' => 'inverys:host', 'value' => 'example.test'], $bom['metadata']['properties']);

        $components = collect($bom['components'])->keyBy('name');
        $this->assertSame('pkg:composer/monolog/monolog@3.9.0', $components['monolog/monolog']['purl']);
        $this->assertSame('required', $components['monolog/monolog']['scope']);
        $this->assertSame('optional', $components['phpunit/phpunit']['scope']);
        $this->assertSame([['expression' => 'GPL-2.0-or-later OR MIT']], $components['acme/fixture-plugin']['licenses']);
        $this->assertContains(['name' => 'inverys:enabled', 'value' => 'false'], $components['acme/fixture-plugin']['properties']);
        $this->assertSame('platform', $components['php']['type']);
        $this->assertStringStartsWith('cpe:2.3:a:php:php:', $components['php']['cpe']);
        $this->assertSame('plugins/blog', collect($components['inverys-plugin-blog']['properties'])->firstWhere('name', 'inverys:path')['value']);
    }

    public function test_dependency_graph_resolves_requirements_and_replacements(): void
    {
        $bom = $this->inventory()->cycloneDx();
        $deps = collect($bom['dependencies'])->keyBy('ref');

        // Platform requirements (php, ext-json) are dropped
        $this->assertSame(['pkg:composer/psr/log@3.0.2'], $deps['pkg:composer/monolog/monolog@3.9.0']['dependsOn']);
        // symfony/polyfill-mb is resolved to polyfill-mbstring via "replace"
        $this->assertSame(['pkg:composer/symfony/polyfill-mbstring@1.33.0'], $deps['pkg:composer/acme/fixture-plugin@2.1.0']['dependsOn']);

        // Every reference in the graph points to an existing component
        $refs = array_merge(array_column($bom['components'], 'bom-ref'), [$bom['metadata']['component']['bom-ref']]);
        $this->assertSame(count($refs), count(array_unique($refs)));
        foreach ($bom['dependencies'] as $dependency) {
            foreach (array_merge([$dependency['ref']], $dependency['dependsOn']) as $ref) {
                $this->assertContains($ref, $refs);
            }
        }
    }

    public function test_download_file_name_contains_version_and_sanitised_host(): void
    {
        $this->assertSame('inverys-cms-'.config('cms.version').'-cms.example.org-sbom.cdx.json', Inventory::fileName('CMS.Example.org'));
        $this->assertSame('inverys-cms-'.config('cms.version').'-bad-host-sbom.cdx.json', Inventory::fileName('bad host!/'));
        $this->assertSame('inverys-cms-'.config('cms.version').'-localhost-sbom.cdx.json', Inventory::fileName(''));
    }

    public function test_release_script_uses_the_shared_builder(): void
    {
        // The release SBOM is built with the same component and keeps its format
        $output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('sbom/generate-composer-sbom.php')).' 2>/dev/null');
        $bom = json_decode((string) $output, true);

        $this->assertIsArray($bom);
        $this->assertSame('1.5', $bom['specVersion']);
        $this->assertSame('build', $bom['metadata']['lifecycles'][0]['phase']);
        $this->assertSame('inverys-cms sbom/generate-composer-sbom.php', $bom['metadata']['tools']['components'][0]['name']);
        $this->assertSame(config('cms.version'), $bom['metadata']['component']['version']);
        $this->assertArrayNotHasKey('properties', $bom['metadata']);
    }

    public function test_licence_list_distinguishes_ids_and_expressions(): void
    {
        $this->assertSame(
            [['license' => ['id' => 'MIT']], ['expression' => 'MIT OR GPL-2.0']],
            CycloneDx::licenseList(['MIT', 'MIT OR GPL-2.0', ' '])
        );
    }
}
