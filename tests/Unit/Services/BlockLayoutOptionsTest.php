<?php

namespace Tests\Unit\Services;

use App\Services\BlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout options of the blocks: background image, spacing, column widths,
 * visibility, image options — and the buttons, pricing table and logos blocks.
 */
class BlockLayoutOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $block): string
    {
        return (new BlockRenderer)->render([$block + ['id' => 'b1', 'data' => [], 'settings' => []]]);
    }

    public function test_background_image_with_overlay(): void
    {
        $html = $this->render(['type' => 'section', 'settings' => ['bg_image' => 'storage/media/demo/hero-abstract.svg', 'bg_overlay' => 'dark'], 'children' => []]);

        $this->assertStringContainsString('site-has-bg site-bg-dark site-inverted', $html);
        $this->assertStringContainsString('class="site-bg-media"', $html);
        $this->assertStringContainsString('hero-abstract.svg', $html);

        $unsafe = $this->render(['type' => 'section', 'settings' => ['bg_image' => 'https://example.com/a.jpg" onerror="x', 'bg_overlay' => 'evil'], 'children' => []]);
        $this->assertStringNotContainsString('onerror="x', $unsafe);
        $this->assertStringContainsString('site-bg-dark', $unsafe);
    }

    public function test_container_gap_option(): void
    {
        $this->assertStringContainsString('site-gap-lg', $this->render(['type' => 'container', 'settings' => ['full_width' => true, 'gap' => 'lg'], 'children' => []]));
        $this->assertStringContainsString('site-gap-md', $this->render(['type' => 'container', 'settings' => ['full_width' => true], 'children' => []]));
    }

    public function test_columns_ratio_only_for_matching_column_count(): void
    {
        $two = fn (string $ratio) => $this->render(['type' => 'columns', 'settings' => ['ratio' => $ratio], 'children' => [
            ['id' => 'c1', 'type' => 'column', 'settings' => []], ['id' => 'c2', 'type' => 'column', 'settings' => []],
        ]]);

        $this->assertStringContainsString('md:grid-cols-[2fr_1fr]', $two('2-1'));
        $this->assertStringContainsString('md:grid-cols-2', $two('1-1-2'));
    }

    public function test_visibility_wraps_blocks_but_not_columns(): void
    {
        $this->assertStringStartsWith('<div class="site-only-desktop">', $this->render(['type' => 'heading', 'data' => ['text' => 'Hi'], 'settings' => ['visibility' => 'desktop']]));
        $this->assertStringStartsWith('<div class="site-only-mobile">', $this->render(['type' => 'heading', 'data' => ['text' => 'Hi'], 'settings' => ['visibility' => 'mobile']]));
        $this->assertStringNotContainsString('site-only-', $this->render(['type' => 'column', 'settings' => ['visibility' => 'mobile'], 'children' => []]));
    }

    public function test_image_ratio_corners_and_link(): void
    {
        $html = $this->render(['type' => 'image', 'data' => ['src' => 'a.jpg', 'alt' => 'A', 'url' => '/about'], 'settings' => ['ratio' => '16-9', 'corners' => 'round']]);

        $this->assertStringContainsString('aspect-video', $html);
        $this->assertStringContainsString('site-image-round', $html);
        $this->assertStringContainsString('/about"', $html);
    }

    public function test_card_portrait_and_quote_photo(): void
    {
        $this->assertStringContainsString('site-card-media-round', $this->render(['type' => 'card', 'data' => ['title' => 'Alex', 'image' => 'team.svg'], 'settings' => ['image_style' => 'round']]));
        $this->assertStringContainsString('site-quote-avatar', $this->render(['type' => 'quote', 'data' => ['text' => 'Great', 'author' => 'Sarah', 'image' => 'avatar.svg']]));
    }

    public function test_buttons_block(): void
    {
        $html = $this->render(['type' => 'buttons', 'data' => ['items' => [
            ['text' => 'Start', 'url' => '/start', 'style' => 'primary', 'icon' => 'arrow-right'],
            ['text' => 'More', 'url' => 'javascript:alert(1)', 'style' => 'evil'],
            ['text' => '', 'url' => '/empty'],
        ]], 'settings' => ['align' => 'center', 'size' => 'lg']]);

        $this->assertSame(2, substr_count($html, 'class="site-btn '));
        $this->assertStringContainsString('site-btn-primary site-btn-lg', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('site-text-center', $html);
    }

    public function test_pricing_block(): void
    {
        $html = $this->render(['type' => 'pricing', 'data' => ['plans' => [
            ['name' => 'Starter', 'price' => '19 €', 'period' => 'per month', 'features' => "One\nTwo\n\n", 'button_text' => 'Buy', 'button_url' => '/buy'],
            ['name' => 'Pro', 'price' => '49 €', 'featured' => true, 'badge' => 'Popular'],
            ['name' => ''],
        ]]]);

        $this->assertSame(2, substr_count($html, 'site-plan '));
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringContainsString('site-plan-featured', $html);
        $this->assertStringContainsString('Popular', $html);
    }

    public function test_logos_block(): void
    {
        $html = $this->render(['type' => 'logos', 'data' => ['items' => [
            ['image' => 'logo-1.svg', 'alt' => 'One', 'url' => 'https://example.com'],
            ['image' => 'logo-2.svg', 'alt' => 'Two'],
            ['image' => ''],
        ]], 'settings' => ['style' => 'muted']]);

        $this->assertSame(2, substr_count($html, '<img'));
        $this->assertStringContainsString('site-logos-muted', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }
}
