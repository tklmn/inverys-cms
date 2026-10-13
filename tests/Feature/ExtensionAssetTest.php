<?php

namespace Tests\Feature;

use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Files from the assets/ of themes and plugins — and nothing else.
 */
class ExtensionAssetTest extends TestCase
{
    use RefreshDatabase;

    private string $themes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        $this->themes = sys_get_temp_dir().'/inverys-assets-'.uniqid();
        File::ensureDirectoryExists($this->themes.'/demo/assets/css');
        File::put($this->themes.'/demo/theme.json', json_encode(['slug' => 'demo', 'name' => 'Demo', 'version' => '1.2.0']));
        File::put($this->themes.'/demo/assets/css/theme.css', '.demo{color:red}');
        File::put($this->themes.'/demo/assets/.secret.css', 'x');
        File::put($this->themes.'/demo/assets/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        File::put($this->themes.'/demo/views.php', '<?php echo 1;');
        config(['cms.themes_path' => $this->themes]);
        app(ThemeManager::class)->discover();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themes);
        parent::tearDown();
    }

    public function test_serves_theme_css_with_long_cache_when_versioned(): void
    {
        $url = app(ThemeManager::class)->getAssetUrl('demo', 'css/theme.css');
        $this->assertStringEndsWith('/ext-assets/theme/demo/css/theme.css?v=1.2.0', $url);

        $response = $this->get($url)->assertOk();
        $this->assertSame('.demo{color:red}', $response->streamedContent() ?: file_get_contents($this->themes.'/demo/assets/css/theme.css'));
        $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_svg_is_sandboxed(): void
    {
        $this->get('/ext-assets/theme/demo/icon.svg')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    public function test_nothing_outside_assets_hidden_or_of_unknown_type(): void
    {
        foreach ([
            '/ext-assets/theme/demo/../theme.json',
            '/ext-assets/theme/demo/..%2Ftheme.json',
            '/ext-assets/theme/demo/.secret.css',
            '/ext-assets/theme/demo/../views.php',
            '/ext-assets/theme/demo/css/missing.css',
            '/ext-assets/theme/unknown/css/theme.css',
            '/ext-assets/other/demo/css/theme.css',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
