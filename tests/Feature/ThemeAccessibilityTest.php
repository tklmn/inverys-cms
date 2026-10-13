<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landmarks and names in the default theme's page layout (WCAG 1.3.1,
 * 2.5.3, 1.1.1).
 */
class ThemeAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $attributes = []): Page
    {
        return Page::create([
            'title' => 'Our Team',
            'slug' => 'our-team',
            'is_active' => true,
            'content_type' => 'html',
            'content' => '<p>Text</p>',
            ...$attributes,
        ]);
    }

    public function test_page_header_is_not_a_second_banner_landmark(): void
    {
        $this->page();

        $html = $this->get('/our-team')->assertOk()->getContent();

        // Only the site header is a <header> outside <main>
        $outsideMain = preg_replace('#<main\b.*</main>#s', '', $html);
        $this->assertSame(1, substr_count($outsideMain, '<header'));

        $main = preg_match('#<main\b[^>]*>(.*)</main>#s', $html, $m) ? $m[1] : '';
        $this->assertStringContainsString('class="site-page-head"', $main);
        $this->assertMatchesRegularExpression('#<h1 class="site-page-title">\s*Our Team\s*</h1>#', $main);
    }

    public function test_aria_label_never_replaces_the_visible_heading(): void
    {
        $this->page(['aria_label' => 'Team & roles']);

        $response = $this->get('/our-team')->assertOk();
        $html = $response->getContent();

        $this->assertDoesNotMatchRegularExpression('#<h1[^>]*aria-label#', $html);
        $this->assertMatchesRegularExpression('#<main id="main-content"[^>]*aria-label="Team &amp; roles"#', $html);
    }

    public function test_header_image_alt_comes_from_the_media_library(): void
    {
        Media::factory()->image()->create(['path' => 'media/2026/10/team.jpg', 'alt_text' => 'The team on the office roof']);
        $this->page(['header_image' => 'storage/media/2026/10/team.jpg']);

        $this->get('/our-team')->assertOk()->assertSee('alt="The team on the office roof"', false);
    }

    public function test_decorative_header_image_keeps_an_empty_alt(): void
    {
        Media::factory()->image()->create(['path' => 'media/2026/10/waves.jpg', 'alt_text' => 'Waves', 'is_decorative' => true]);
        $this->page(['header_image' => 'storage/media/2026/10/waves.jpg']);

        $html = $this->get('/our-team')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<img src="[^"]*waves\.jpg"[^>]* alt=""#', $html);
        $this->assertStringNotContainsString('alt="Waves"', $html);
    }
}
