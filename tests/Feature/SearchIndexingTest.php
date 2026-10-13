<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * robots.txt modes (automatic, custom content, off), sitemap line and
 * blocking search engines (X-Robots-Tag + <meta name="robots">).
 */
class SearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    private const NOINDEX_META = '<meta name="robots" content="noindex, nofollow">';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    private function set(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => 'seo.'.$key], ['value' => $value]);
    }

    private function robots(): string
    {
        return $this->get('/robots.txt')->assertOk()->getContent();
    }

    public function test_automatic_mode_without_sitemap_line(): void
    {
        $this->set('robots_sitemap_line', 'off');

        $this->assertSame("User-agent: *\nDisallow:\n", $this->robots());
    }

    public function test_custom_mode_serves_the_written_file_plus_sitemap_line(): void
    {
        $this->set('robots_mode', 'custom');
        $this->set('robots_custom', "User-agent: *\nDisallow: /internal/");
        $this->set('robots_extra', 'User-agent: IgnoredInCustomMode');

        $this->assertSame("User-agent: *\nDisallow: /internal/\n\nSitemap: ".route('sitemap')."\n", $this->robots());
    }

    public function test_custom_mode_keeps_its_own_sitemap_line(): void
    {
        $this->set('robots_mode', 'custom');
        $this->set('robots_custom', "User-agent: *\nAllow: /\nsitemap: https://cdn.example.org/sitemap.xml");

        $this->assertSame("User-agent: *\nAllow: /\nsitemap: https://cdn.example.org/sitemap.xml\n", $this->robots());
    }

    public function test_custom_mode_without_sitemap_line(): void
    {
        $this->set('robots_mode', 'custom');
        $this->set('robots_custom', "User-agent: *\nDisallow: /internal/");
        $this->set('robots_sitemap_line', 'off');

        $this->assertSame("User-agent: *\nDisallow: /internal/\n", $this->robots());
    }

    public function test_sitemap_line_needs_an_enabled_sitemap(): void
    {
        $this->set('robots_mode', 'custom');
        $this->set('robots_custom', 'User-agent: *');
        $this->set('sitemap_enabled', 'off');

        $this->assertSame("User-agent: *\n", $this->robots());
    }

    public function test_off_mode_has_no_robots_txt(): void
    {
        $this->set('robots_mode', 'off');

        $this->get('/robots.txt')->assertNotFound();
        // The sitemap is not affected by this
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_blocking_search_engines_overrides_every_robots_mode(): void
    {
        $this->set('robots_allow_indexing', 'off');

        foreach (['auto', 'custom', 'off'] as $mode) {
            $this->set('robots_mode', $mode);
            $this->set('robots_custom', "User-agent: *\nAllow: /");

            $this->assertSame("User-agent: *\nDisallow: /\n", $this->robots(), "mode {$mode}");
        }
    }

    public function test_blocked_frontend_sends_noindex_header_and_meta(): void
    {
        Page::factory()->root()->create();
        Page::factory()->create(['slug' => 'about-us']);
        $this->set('robots_allow_indexing', 'off');

        foreach (['/', '/about-us', '/search'] as $uri) {
            $response = $this->get($uri);
            $response->assertOk();
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'), $uri);
            $this->assertStringContainsString(self::NOINDEX_META, $response->getContent(), $uri);
        }

        // Responses without HTML, too
        $this->assertSame('noindex, nofollow', $this->get('/sitemap.xml')->headers->get('X-Robots-Tag'));
        $this->assertSame('noindex, nofollow', $this->get('/robots.txt')->headers->get('X-Robots-Tag'));
    }

    public function test_blocked_page_drops_its_own_robots_meta(): void
    {
        // The page's own "index, follow" would contradict the block — it is dropped
        Page::factory()->create(['slug' => 'indexed', 'meta_robots' => 'index, follow']);
        $this->assertStringContainsString('content="index, follow"', $this->get('/indexed')->getContent());

        $this->set('robots_allow_indexing', 'off');
        $html = $this->get('/indexed')->getContent();

        $this->assertStringNotContainsString('content="index, follow"', $html);
        $this->assertStringContainsString(self::NOINDEX_META, $html);
    }

    public function test_blocking_applies_to_every_theme(): void
    {
        Page::factory()->root()->create();
        $this->set('robots_allow_indexing', 'off');

        foreach (array_keys(app(ThemeManager::class)->all()) as $theme) {
            Setting::updateOrCreate(['key' => 'active_theme'], ['value' => $theme]);
            Setting::updateOrCreate(['key' => 'theme'], ['value' => $theme]);

            $this->assertStringContainsString(self::NOINDEX_META, $this->get('/')->assertOk()->getContent(), $theme);
        }
    }

    public function test_allowed_frontend_has_no_noindex(): void
    {
        Page::factory()->root()->create();

        $response = $this->get('/');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $this->assertStringNotContainsString(self::NOINDEX_META, $response->getContent());
    }

    public function test_backend_and_api_are_not_marked(): void
    {
        $this->set('robots_allow_indexing', 'off');
        $admin = $this->createAdmin();

        $backend = $this->actingAs($admin)->get(route('dashboard'));
        $backend->assertOk();
        $this->assertFalse($backend->headers->has('X-Robots-Tag'));
        $this->assertStringNotContainsString(self::NOINDEX_META, $backend->getContent());

        $api = $this->getJson('/api/v1/pages');
        $this->assertFalse($api->headers->has('X-Robots-Tag'));
    }
}
