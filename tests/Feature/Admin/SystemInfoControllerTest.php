<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class SystemInfoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_system_info(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.system.info'))
            ->assertOk();
    }

    public function test_system_info_page_shows_cms_version(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.system.info'))
            ->assertOk()
            ->assertSee(config('cms.version'));
    }

    public function test_admin_can_clear_cache(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.system.clear-cache'))
            ->assertRedirect(route('backend.system.info'));
    }

    public function test_editor_without_show_system_gets_403(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.system.info'))
            ->assertForbidden();
    }

    // ── Components (SBOM) ───────────────────────────────────────

    public function test_components_page_lists_runtime_packages_plugins_and_themes(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.system.components'))
            ->assertOk()
            ->assertSee('laravel/framework')
            ->assertSee('plugins/blog')
            ->assertSee('themes/default')
            ->assertSee(route('backend.system.sbom.download'), false)
            // Development packages only on request
            ->assertDontSee('phpunit/phpunit');
    }

    public function test_components_page_includes_dev_packages_on_request(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.system.components', ['dev' => 1]))
            ->assertOk()
            ->assertSee('phpunit/phpunit');
    }

    public function test_components_page_filters_by_kind_and_search(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.system.components', ['kind' => 'plugin']))
            ->assertOk()
            ->assertSee('plugins/blog')
            ->assertDontSee('laravel/framework');

        $this->actingAs($admin)
            ->get(route('backend.system.components', ['q' => 'laravel/framework']))
            ->assertOk()
            ->assertSee('laravel/framework')
            ->assertDontSee('plugins/blog');
    }

    public function test_sbom_download_is_valid_cyclonedx(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->get(route('backend.system.sbom.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.cyclonedx+json; charset=utf-8');

        $this->assertStringContainsString(
            'inverys-cms-'.config('cms.version').'-localhost-sbom.cdx.json',
            (string) $response->headers->get('Content-Disposition')
        );

        $bom = json_decode($response->getContent(), true);
        $this->assertIsArray($bom);
        $this->assertSame('CycloneDX', $bom['bomFormat']);
        $this->assertSame('1.5', $bom['specVersion']);
        $this->assertStringStartsWith('urn:uuid:', $bom['serialNumber']);
        $this->assertArrayHasKey('timestamp', $bom['metadata']);
        $this->assertSame('inverys/cms', $bom['metadata']['component']['name']);
        $this->assertSame(config('cms.version'), $bom['metadata']['component']['version']);

        $purls = array_filter(array_column($bom['components'], 'purl'));
        $this->assertNotEmpty(preg_grep('~^pkg:composer/laravel/framework@\d~', $purls));
        $this->assertNotEmpty($bom['dependencies']);
    }

    public function test_release_sbom_download(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.system.sbom.release', 'composer'))
            ->assertOk()
            ->assertDownload();
    }

    public function test_unknown_release_sbom_is_not_routed(): void
    {
        $this->actingAs($this->createAdmin())
            ->get('/backend/system-info/components/release/passwd/download')
            ->assertNotFound();
    }

    public function test_editor_without_show_system_cannot_see_or_download_the_sbom(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.system.components'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.system.sbom.download'))->assertForbidden();
        $this->actingAs($editor)->get(route('backend.system.sbom.release', 'composer'))->assertForbidden();
    }

    public function test_sbom_strings_exist_in_english_and_german(): void
    {
        $en = require base_path('lang/en/translation.php');
        $de = require base_path('plugins/language-de/lang/de/translation.php');

        foreach ([['system', 'sbom'], ['system', 'tabs'], ['dashboard', 'security'], ['dashboard', 'health']] as [$group, $key]) {
            $this->assertNotEmpty($en[$group][$key] ?? null, "en: {$group}.{$key}");
            $this->assertSame(
                array_keys(Arr::dot($en[$group][$key])),
                array_keys(Arr::dot($de[$group][$key] ?? [])),
                "de: {$group}.{$key}"
            );
        }
        $this->assertSame('Komponenten (SBOM)', $de['system']['sbom']['title']);
        $this->assertSame('System & Sicherheit', $de['dashboard']['system_security']);
    }
}
