<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Services\ThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The separately maintained themes follow the default theme's landmarks,
 * names and image handling (WCAG 1.3.1, 2.5.3, 1.1.1). Themes that are not
 * checked out (separate repositories) are skipped.
 */
class ThemeParityTest extends TestCase
{
    use RefreshDatabase;

    public static function themes(): array
    {
        return ['default' => ['default'], 'inverys' => ['inverys'], 'ember' => ['ember']];
    }

    private function useTheme(string $theme): void
    {
        if (! is_dir(base_path('themes/'.$theme))) {
            $this->markTestSkipped("Theme {$theme} is not checked out.");
        }

        $this->assertTrue(app(ThemeManager::class)->activate($theme));
    }

    private function page(array $attributes = []): void
    {
        Page::create([
            'title' => 'Our Team',
            'slug' => 'our-team',
            'is_active' => true,
            'content_type' => 'html',
            'content' => '<p>Text</p>',
            ...$attributes,
        ]);
    }

    #[DataProvider('themes')]
    public function test_page_header_and_header_image_sit_inside_main(string $theme): void
    {
        $this->useTheme($theme);
        Media::factory()->image()->create(['path' => 'media/2026/10/team.jpg', 'alt_text' => 'The team on the office roof', 'width' => 1600, 'height' => 900]);
        $this->page(['header_image' => 'storage/media/2026/10/team.jpg', 'aria_label' => 'Team & roles']);

        $html = $this->get('/our-team')->assertOk()->getContent();

        // At most the site header is a <header> outside <main>; the header image is inside too
        $outsideMain = preg_replace('#<main\b.*</main>#s', '', $html);
        $this->assertLessThanOrEqual(1, substr_count($outsideMain, '<header'), 'No second banner landmark');
        $this->assertDoesNotMatchRegularExpression('#<img[^>]*team\.jpg#', $outsideMain);

        $main = preg_match('#<main\b[^>]*>(.*)</main>#s', $html, $m) ? $m[1] : '';
        $this->assertMatchesRegularExpression('#<h1\b[^>]*>\s*Our Team\s*</h1>#', $main);

        // The ARIA label names the main region, never the heading
        $this->assertDoesNotMatchRegularExpression('#<h1[^>]*aria-label#', $html);
        $this->assertMatchesRegularExpression('#<main id="main-content"[^>]*aria-label="Team &amp; roles"#', $html);

        // Header image: library alt text, size, high priority
        $this->assertSame(1, preg_match('#<img[^>]*team\.jpg"[^>]*>#', $main, $img));
        $this->assertStringContainsString('alt="The team on the office roof"', $img[0]);
        $this->assertStringContainsString('width="1600" height="900"', $img[0]);
        $this->assertStringContainsString('fetchpriority="high"', $img[0]);
    }

    #[DataProvider('themes')]
    public function test_decorative_header_image_has_an_empty_alt(string $theme): void
    {
        $this->useTheme($theme);
        Media::factory()->image()->create(['path' => 'media/2026/10/waves.jpg', 'alt_text' => 'Waves', 'is_decorative' => true]);
        $this->page(['header_image' => 'storage/media/2026/10/waves.jpg']);

        $html = $this->get('/our-team')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<img src="[^"]*waves\.jpg"[^>]* alt=""#', $html);
        $this->assertStringNotContainsString('alt="Waves"', $html);
    }
}
