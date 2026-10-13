<?php

namespace Tests\Unit\Services;

use App\Services\BlockRenderer;
use App\Support\BlockDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design options of the blocks (background, alignment, icons …) and the hero
 * and stats blocks.
 */
class BlockDesignOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function render(array $block): string
    {
        return (new BlockRenderer)->render([$block + ['id' => 'b1', 'data' => [], 'settings' => []]]);
    }

    public function test_surfaces_map_to_classes_and_dark_ones_invert_the_text(): void
    {
        $this->assertSame('site-surface-soft', BlockDesign::surface('soft'));
        $this->assertSame('site-surface-gradient site-inverted', BlockDesign::surface('gradient'));
        $this->assertSame('site-surface-dark site-inverted', BlockDesign::surface('dark'));
        // Unknown values never end up in the markup
        $this->assertSame('', BlockDesign::surface('bg-red-500" onclick="x'));
        $this->assertSame('', BlockDesign::surface(null));
        $this->assertSame('site-text-center', BlockDesign::align('center'));
        $this->assertSame('', BlockDesign::align('evil'));
    }

    public function test_anchor_is_sanitized_and_rendered_as_id(): void
    {
        $this->assertSame('contact', BlockDesign::anchor('#Contact'));
        $this->assertSame('a-onclick-b', BlockDesign::anchor('a" onclick="b'));
        $this->assertSame('', BlockDesign::anchor(null));

        $html = $this->render(['type' => 'section', 'settings' => ['anchor' => '#How We Work'], 'children' => []]);
        $this->assertStringContainsString('id="how-we-work"', $html);

        $none = $this->render(['type' => 'section', 'settings' => [], 'children' => []]);
        $this->assertStringNotContainsString(' id=', $none);
    }

    public function test_container_with_surface_becomes_a_rounded_panel_unless_full_width(): void
    {
        $panel = $this->render(['type' => 'container', 'settings' => ['full_width' => false, 'style' => 'tint'], 'children' => []]);
        $band = $this->render(['type' => 'container', 'settings' => ['full_width' => true, 'style' => 'tint'], 'children' => []]);

        $this->assertStringContainsString('site-surface-rounded', $panel);
        $this->assertStringNotContainsString('site-surface-rounded', $band);
        $this->assertStringContainsString('site-surface-tint', $band);
    }

    public function test_hero_renders_title_level_buttons_and_image(): void
    {
        $html = $this->render(['type' => 'hero', 'data' => [
            'eyebrow' => 'New', 'title' => 'Big title', 'text' => 'Intro',
            'primary_text' => 'Start', 'primary_url' => '/start', 'secondary_text' => 'More', 'secondary_url' => 'https://example.com',
            'image' => 'storage/media/demo/hero-abstract.svg', 'image_alt' => 'Alt',
        ], 'settings' => ['layout' => 'split', 'style' => 'gradient']]);

        $this->assertStringContainsString('<h1 class="site-hero-title">Big title</h1>', $html);
        $this->assertStringContainsString('site-inverted', $html);
        $this->assertStringContainsString('Start', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('alt="Alt"', $html);

        $cta = $this->render(['type' => 'hero', 'data' => ['title' => 'Call'], 'settings' => ['layout' => 'centered', 'level' => 'h2', 'size' => 'compact']]);
        $this->assertStringContainsString('<h2 class="site-hero-title">Call</h2>', $cta);
        $this->assertStringContainsString('site-hero-compact', $cta);
    }

    public function test_hero_blocks_unsafe_urls(): void
    {
        $html = $this->render(['type' => 'hero', 'data' => ['title' => 'T', 'primary_text' => 'Go', 'primary_url' => 'javascript:alert(1)']]);

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_stats_skip_empty_rows(): void
    {
        $html = $this->render(['type' => 'stats', 'data' => ['items' => [
            ['value' => '98 %', 'label' => 'Happy'],
            ['value' => '', 'label' => ''],
        ]], 'settings' => ['style' => 'cards']]);

        $this->assertSame(1, substr_count($html, 'site-stat-value'));
        $this->assertStringContainsString('98 %', $html);
        $this->assertStringContainsString('site-stats-cards', $html);

        $this->assertSame('', trim($this->render(['type' => 'stats', 'data' => ['items' => []]])));
    }

    public function test_card_and_button_icons_are_limited_to_the_allowed_set(): void
    {
        $card = $this->render(['type' => 'card', 'data' => ['title' => 'T', 'icon' => 'rocket'], 'settings' => ['style' => 'tinted', 'align' => 'center']]);
        $this->assertStringContainsString('site-icon-badge', $card);
        $this->assertStringContainsString('site-card-tinted', $card);
        $this->assertStringContainsString('site-card-center', $card);

        $unknown = $this->render(['type' => 'card', 'data' => ['title' => 'T', 'icon' => '../../etc/passwd']]);
        $this->assertStringNotContainsString('site-icon-badge', $unknown);

        $button = $this->render(['type' => 'button', 'data' => ['text' => 'Go', 'url' => '/x', 'icon' => 'arrow-right'], 'settings' => ['size' => 'lg', 'align' => 'center']]);
        $this->assertStringContainsString('site-btn-lg', $button);
        $this->assertStringContainsString('justify-center', $button);
        $this->assertStringContainsString('<svg', $button);
    }

    public function test_heading_eyebrow_and_gradient_text(): void
    {
        $html = $this->render(['type' => 'heading', 'data' => ['text' => 'Hello', 'level' => 'h2', 'alignment' => 'center', 'eyebrow' => 'Kicker'], 'settings' => ['style' => 'gradient']]);

        $this->assertStringContainsString('site-eyebrow', $html);
        $this->assertStringContainsString('Kicker', $html);
        $this->assertStringContainsString('site-text-gradient', $html);
    }
}
