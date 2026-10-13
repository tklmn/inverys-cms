<?php

namespace Tests\Feature\Admin;

use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Extensions → Plugins: a plugin's preview image ("screenshot" in plugin.json)
 * replaces the puzzle icon, served from its assets/ like its other files.
 */
class PluginPreviewTest extends TestCase
{
    use RefreshDatabase;

    private string $plugins;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        $this->plugins = sys_get_temp_dir().'/inverys-preview-'.uniqid();
        foreach (['with-preview' => 'assets/screenshot.png', 'outside-assets' => 'screenshot.png', 'no-preview' => null] as $slug => $shot) {
            File::ensureDirectoryExists($this->plugins.'/'.$slug.'/assets');
            File::put($this->plugins.'/'.$slug.'/plugin.json', json_encode(array_filter([
                'slug' => $slug, 'name' => ucfirst($slug), 'version' => '1.0.0', 'provider' => 'Plugins\\X\\Provider', 'screenshot' => $shot,
            ])));
            if ($shot !== null) {
                File::put($this->plugins.'/'.$slug.'/'.$shot, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
            }
        }
        config(['cms.plugins_path' => $this->plugins]);
        app(PluginManager::class)->discover();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->plugins);
        parent::tearDown();
    }

    public function test_plugin_list_shows_the_preview_from_assets_only(): void
    {
        $html = $this->actingAs($this->createAdmin())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ext-assets/plugin/with-preview/screenshot.png', $html);
        // Outside assets/ the file would not be served — no broken image
        $this->assertStringNotContainsString('ext-assets/plugin/outside-assets/', $html);
        $this->assertSame(1, substr_count($html, 'class="ui-list-thumb"'));

        $this->get('/ext-assets/plugin/with-preview/screenshot.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}
