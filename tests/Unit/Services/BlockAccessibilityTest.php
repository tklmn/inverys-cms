<?php

namespace Tests\Unit\Services;

use App\Services\BlockRenderer;
use App\Support\BlockDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Accessibility options of the page builder blocks: language, alt texts,
 * new-tab links, heading levels, landmarks, media alternatives and widgets.
 */
class BlockAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    private function render(array $block): string
    {
        return (new BlockRenderer)->render([$block + ['id' => 'b1', 'data' => [], 'settings' => []]]);
    }

    private function hint(): string
    {
        return __('translation.frontend.opens_new_tab');
    }

    /* ── Validators ─────────────────────────────────────────────── */

    public function test_language_codes_are_validated_and_normalised(): void
    {
        $this->assertSame('de', BlockDesign::lang('de'));
        $this->assertSame('en-GB', BlockDesign::lang('en_gb'));
        $this->assertSame('sr-Latn-RS', BlockDesign::lang(' sr-latn-rs '));
        $this->assertSame('es-419', BlockDesign::lang('es-419'));
        $this->assertSame('', BlockDesign::lang('x'));
        $this->assertSame('', BlockDesign::lang('en" onclick="x'));
        $this->assertSame('', BlockDesign::lang('german'));
        $this->assertSame('', BlockDesign::lang(null));
        $this->assertSame('', BlockDesign::lang(['de']));
    }

    public function test_element_heading_level_alt_role_and_target_helpers(): void
    {
        $this->assertSame('nav', BlockDesign::element('nav', 'div'));
        $this->assertSame('div', BlockDesign::element('main', 'div'));
        $this->assertSame('section', BlockDesign::element(null, 'section'));

        $this->assertSame('h4', BlockDesign::headingLevel('h4', 'h3'));
        $this->assertSame('h3', BlockDesign::headingLevel('h1', 'h3'));
        $this->assertSame('', BlockDesign::headingLevel('h7', ''));

        $this->assertSame('A cat', BlockDesign::alt(' A cat ', false));
        $this->assertSame('', BlockDesign::alt('A cat', true));
        $this->assertSame('Title', BlockDesign::alt('', false, 'Title'));
        $this->assertSame('', BlockDesign::alt('', true, 'Title'));

        $this->assertSame('status', BlockDesign::alertRole(null));
        $this->assertSame('alert', BlockDesign::alertRole('alert'));
        $this->assertSame('status', BlockDesign::alertRole('evil'));
        $this->assertNull(BlockDesign::alertRole('none'));

        $this->assertTrue(BlockDesign::newTab('_blank'));
        $this->assertFalse(BlockDesign::newTab('_self', true));
        $this->assertTrue(BlockDesign::newTab(null, true));
        $this->assertFalse(BlockDesign::newTab(null));
    }

    public function test_attributes_are_attached_to_the_first_element_but_never_to_a_script(): void
    {
        $this->assertSame('<div lang="de" class="x"></div>', BlockDesign::attachAttributes('<div class="x"></div>', ' lang="de"'));
        $this->assertSame("<!-- c -->\n<p lang=\"de\">", BlockDesign::attachAttributes("<!-- c -->\n<p>", ' lang="de"'));
        $this->assertSame('<script>x</script>', BlockDesign::attachAttributes('<script>x</script>', ' lang="de"'));
    }

    /* ── Language ───────────────────────────────────────────────── */

    public function test_block_language_is_marked_only_where_it_differs(): void
    {
        $german = $this->render(['type' => 'quote', 'data' => ['text' => 'Hallo'], 'settings' => ['lang' => 'de']]);
        $this->assertMatchesRegularExpression('/^\s*<figure lang="de"/', $german);

        $same = $this->render(['type' => 'quote', 'data' => ['text' => 'Hello'], 'settings' => ['lang' => 'en']]);
        $this->assertStringNotContainsString('lang=', $same);

        $invalid = $this->render(['type' => 'quote', 'data' => ['text' => 'Hello'], 'settings' => ['lang' => '"><script>']]);
        $this->assertStringNotContainsString('lang=', $invalid);
    }

    public function test_nested_blocks_compare_with_the_surrounding_language(): void
    {
        $html = $this->render(['type' => 'section', 'settings' => ['lang' => 'de'], 'children' => [
            ['id' => 'c1', 'type' => 'quote', 'data' => ['text' => 'Inside'], 'settings' => ['lang' => 'de']],
            ['id' => 'c2', 'type' => 'quote', 'data' => ['text' => 'English'], 'settings' => ['lang' => 'en']],
        ]]);

        $this->assertMatchesRegularExpression('/^\s*<section lang="de"/', $html);
        $this->assertSame(1, substr_count($html, 'lang="de"'));
        $this->assertStringContainsString('<figure lang="en"', $html);
    }

    /* ── Images ─────────────────────────────────────────────────── */

    public function test_decorative_images_get_an_empty_alt_and_others_their_text(): void
    {
        $described = $this->render(['type' => 'image', 'data' => ['src' => 'https://example.com/a.jpg', 'alt' => 'A cat']]);
        $decorative = $this->render(['type' => 'image', 'data' => ['src' => 'https://example.com/a.jpg', 'alt' => 'A cat'], 'settings' => ['decorative' => true]]);

        $this->assertStringContainsString('alt="A cat"', $described);
        $this->assertStringContainsString('alt=""', $decorative);
        $this->assertStringNotContainsString('A cat', $decorative);
    }

    public function test_card_image_has_its_own_alt_text_with_the_title_as_fallback(): void
    {
        $own = $this->render(['type' => 'card', 'data' => ['title' => 'Team', 'image' => 'https://example.com/t.jpg', 'image_alt' => 'Five people laughing']]);
        $legacy = $this->render(['type' => 'card', 'data' => ['title' => 'Team', 'image' => 'https://example.com/t.jpg']]);
        $decorative = $this->render(['type' => 'card', 'data' => ['title' => 'Team', 'image' => 'https://example.com/t.jpg'], 'settings' => ['image_decorative' => true]]);

        $this->assertStringContainsString('alt="Five people laughing"', $own);
        $this->assertStringContainsString('alt="Team"', $legacy);
        $this->assertStringContainsString('alt=""', $decorative);
    }

    public function test_slider_logos_hero_and_background_images_honour_the_options(): void
    {
        $slider = $this->render(['type' => 'slider', 'data' => ['slides' => [
            ['image' => 'https://example.com/1.jpg', 'title' => 'One', 'alt' => 'A bridge at night'],
            ['image' => 'https://example.com/2.jpg', 'title' => 'Two', 'decorative' => true],
        ]]]);
        $this->assertStringContainsString('alt="A bridge at night"', $slider);
        $this->assertStringContainsString('alt=""', $slider);

        $logos = $this->render(['type' => 'logos', 'data' => ['items' => [['image' => 'https://example.com/l.svg', 'alt' => 'Acme', 'decorative' => true]]]]);
        $this->assertStringContainsString('alt=""', $logos);

        $hero = $this->render(['type' => 'hero', 'data' => ['title' => 'Hi', 'image' => 'https://example.com/h.jpg', 'image_alt' => 'Dashboard'], 'settings' => ['image_decorative' => true]]);
        $this->assertStringNotContainsString('alt="Dashboard"', $hero);

        $informative = $this->render(['type' => 'section', 'data' => ['bg_alt' => 'Crowd at a concert'], 'settings' => ['bg_image' => 'https://example.com/bg.jpg', 'bg_decorative' => false], 'children' => []]);
        $legacy = $this->render(['type' => 'section', 'data' => ['bg_alt' => 'Crowd at a concert'], 'settings' => ['bg_image' => 'https://example.com/bg.jpg'], 'children' => []]);
        $this->assertStringContainsString('alt="Crowd at a concert"', $informative);
        $this->assertStringContainsString('class="site-bg-media"', $legacy);
        $this->assertStringNotContainsString('Crowd at a concert', $legacy);
    }

    /* ── Links ──────────────────────────────────────────────────── */

    public function test_new_tab_links_announce_it_to_screen_readers(): void
    {
        $html = $this->render(['type' => 'button', 'data' => ['text' => 'Docs', 'url' => 'https://example.com', 'target' => '_blank']]);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('<span class="sr-only"> '.$this->hint().'</span>', $html);

        $same = $this->render(['type' => 'button', 'data' => ['text' => 'Docs', 'url' => 'https://example.com', 'target' => '_self']]);
        $this->assertStringNotContainsString('sr-only', $same);
    }

    public function test_accessible_name_includes_the_new_tab_hint(): void
    {
        $html = $this->render(['type' => 'link-group', 'data' => ['links' => [
            ['text' => 'More', 'url' => '/a', 'target' => '_blank', 'aria_label' => 'More about pricing'],
            ['text' => 'Other', 'url' => '/b', 'aria_label' => 'Other offers'],
        ]]]);

        $this->assertStringContainsString('aria-label="More about pricing '.e($this->hint()).'"', $html);
        $this->assertStringContainsString('aria-label="Other offers"', $html);
    }

    public function test_buttons_without_a_target_keep_opening_external_links_in_a_new_tab(): void
    {
        $legacy = $this->render(['type' => 'buttons', 'data' => ['items' => [['text' => 'Partner', 'url' => 'https://partner.example']]]]);
        $explicit = $this->render(['type' => 'buttons', 'data' => ['items' => [['text' => 'Partner', 'url' => 'https://partner.example', 'target' => '_self']]]]);

        $this->assertStringContainsString('target="_blank"', $legacy);
        $this->assertStringContainsString($this->hint(), $legacy);
        $this->assertStringNotContainsString('target="_blank"', $explicit);
    }

    public function test_logos_card_pricing_hero_and_slider_links_have_a_target_option(): void
    {
        $logosLegacy = $this->render(['type' => 'logos', 'data' => ['items' => [['image' => 'https://example.com/l.svg', 'alt' => 'Acme', 'url' => 'https://acme.example']]]]);
        $logosSame = $this->render(['type' => 'logos', 'data' => ['items' => [['image' => 'https://example.com/l.svg', 'alt' => 'Acme', 'url' => 'https://acme.example', 'target' => '_self']]]]);
        $this->assertStringContainsString('target="_blank"', $logosLegacy);
        $this->assertStringNotContainsString('target="_blank"', $logosSame);

        $card = $this->render(['type' => 'card', 'data' => ['title' => 'T', 'url' => '/x', 'link_aria_label' => 'Read more about T'], 'settings' => ['link_target' => '_blank']]);
        $this->assertStringContainsString('target="_blank"', $card);
        $this->assertStringContainsString('aria-label="Read more about T '.e($this->hint()).'"', $card);

        $pricing = $this->render(['type' => 'pricing', 'data' => ['plans' => [['name' => 'Pro', 'button_text' => 'Buy', 'button_url' => '/buy', 'button_target' => '_blank']]]]);
        $this->assertStringContainsString('target="_blank"', $pricing);

        $hero = $this->render(['type' => 'hero', 'data' => ['title' => 'Hi', 'primary_text' => 'Go', 'primary_url' => '/go'], 'settings' => ['primary_target' => '_blank']]);
        $this->assertStringContainsString('target="_blank"', $hero);
        $this->assertStringContainsString($this->hint(), $hero);

        $slider = $this->render(['type' => 'slider', 'data' => ['slides' => [['title' => 'One', 'url' => '/one', 'aria_label' => 'Learn more about one']]]]);
        $this->assertStringContainsString('aria-label="Learn more about one"', $slider);
    }

    /* ── Headings ───────────────────────────────────────────────── */

    public function test_heading_levels_are_configurable(): void
    {
        $this->assertStringContainsString('<h4 class="site-card-title"', $this->render(['type' => 'card', 'data' => ['title' => 'T'], 'settings' => ['title_level' => 'h4']]));
        $this->assertStringContainsString('<h3 class="site-card-title"', $this->render(['type' => 'card', 'data' => ['title' => 'T'], 'settings' => ['title_level' => 'h1']]));
        $this->assertStringContainsString('<h2 class="site-plan-name"', $this->render(['type' => 'pricing', 'data' => ['plans' => [['name' => 'Pro']]], 'settings' => ['title_level' => 'h2']]));
        $this->assertMatchesRegularExpression('/<h2 class="text-xl[^"]*">\s*One/', $this->render(['type' => 'slider', 'data' => ['slides' => [['title' => 'One']]], 'settings' => ['title_level' => 'h2']]));

        $this->assertStringContainsString('<p class="site-alert-title"', $this->render(['type' => 'alert', 'data' => ['title' => 'Note']]));
        $this->assertStringContainsString('<h2 class="site-alert-title"', $this->render(['type' => 'alert', 'data' => ['title' => 'Note'], 'settings' => ['title_level' => 'h2']]));
    }

    public function test_accordion_triggers_sit_in_headings_and_panels_are_labelled(): void
    {
        $html = $this->render(['type' => 'accordion', 'settings' => ['title_level' => 'h3', 'allow_multiple' => true], 'children' => [
            ['id' => 'i1', 'type' => 'accordion-item', 'data' => ['title' => 'First'], 'settings' => ['open' => true], 'children' => []],
            ['id' => 'i2', 'type' => 'accordion-item', 'data' => ['title' => 'Second'], 'settings' => [], 'children' => []],
        ]]);

        $this->assertStringContainsString('data-allow-multiple', $html);
        $this->assertSame(2, substr_count($html, '<h3 class="site-accordion-heading">'));
        $this->assertStringContainsString('aria-labelledby="accordion-trigger-i1"', $html);
        $this->assertMatchesRegularExpression('/id="accordion-trigger-i1"[^>]*aria-expanded="true"/s', $html);
        $this->assertMatchesRegularExpression('/id="accordion-panel-i1"[^>]*class="site-accordion-panel\s*"/', $html);
        $this->assertMatchesRegularExpression('/id="accordion-panel-i2"[^>]*class="site-accordion-panel hidden"/', $html);

        $plain = $this->render(['type' => 'accordion', 'children' => [['id' => 'i3', 'type' => 'accordion-item', 'data' => ['title' => 'X'], 'children' => []]]]);
        $this->assertStringNotContainsString('site-accordion-heading', $plain);
        $this->assertDoesNotMatchRegularExpression('/<div class="block-accordion[^>]*data-allow-multiple/', $plain);
    }

    /* ── Landmarks and widgets ──────────────────────────────────── */

    public function test_container_and_section_become_named_landmarks(): void
    {
        $nav = $this->render(['type' => 'container', 'data' => ['aria_label' => 'Topics'], 'settings' => ['element' => 'nav'], 'children' => []]);
        $this->assertMatchesRegularExpression('/^\s*<nav [^>]*aria-label="Topics"/', $nav);
        $this->assertStringContainsString('</nav>', $nav);

        // A plain div takes no name
        $div = $this->render(['type' => 'container', 'data' => ['aria_label' => 'Topics'], 'children' => []]);
        $this->assertMatchesRegularExpression('/^\s*<div /', $div);
        $this->assertStringNotContainsString('aria-label', $div);

        $aside = $this->render(['type' => 'section', 'data' => ['aria_label' => 'Related'], 'settings' => ['element' => 'aside'], 'children' => []]);
        $this->assertMatchesRegularExpression('/^\s*<aside [^>]*aria-label="Related"/', $aside);
    }

    public function test_alert_role_divider_and_code_options(): void
    {
        $this->assertStringContainsString('role="status"', $this->render(['type' => 'alert', 'data' => ['type' => 'danger', 'content' => 'x']]));
        $this->assertStringContainsString('role="alert"', $this->render(['type' => 'alert', 'data' => ['content' => 'x'], 'settings' => ['role' => 'alert']]));
        $this->assertStringNotContainsString('role=', $this->render(['type' => 'alert', 'data' => ['content' => 'x'], 'settings' => ['role' => 'none']]));

        $this->assertStringContainsString('aria-hidden="true"', $this->render(['type' => 'divider', 'settings' => ['decorative' => true]]));
        $this->assertStringNotContainsString('aria-hidden', $this->render(['type' => 'divider']));

        $code = $this->render(['type' => 'code', 'data' => ['code' => 'x', 'aria_label' => 'Install command']]);
        $this->assertStringContainsString('tabindex="0"', $code);
        $this->assertStringContainsString('aria-label="Install command"', $code);
    }

    public function test_slider_is_a_carousel_with_pause_button_and_hidden_inactive_slides(): void
    {
        $slides = [['title' => 'One'], ['title' => 'Two']];

        $legacy = $this->render(['type' => 'slider', 'data' => ['slides' => $slides]]);
        $this->assertStringContainsString('aria-roledescription="'.__('translation.frontend.slider_carousel').'"', $legacy);
        $this->assertMatchesRegularExpression('/<button\s+type="button"\s+data-slider-toggle/', $legacy);
        $this->assertStringContainsString('data-autoplay="true"', $legacy);
        $this->assertSame(1, substr_count($legacy, 'inert aria-hidden="true"'));
        $this->assertStringContainsString('aria-current="true"', $legacy);

        $manual = $this->render(['type' => 'slider', 'data' => ['slides' => $slides, 'autoplay' => false, 'aria_label' => 'Stories']]);
        $this->assertDoesNotMatchRegularExpression('/<button\s+type="button"\s+data-slider-toggle/', $manual);
        $this->assertStringContainsString('data-autoplay="false"', $manual);
        $this->assertStringContainsString('aria-label="Stories"', $manual);
        $this->assertStringContainsString('aria-live="polite"', $manual);
    }

    public function test_tabs_script_is_output_once_and_the_tablist_can_be_named(): void
    {
        $tabs = fn (string $id) => ['id' => $id, 'type' => 'tabs', 'data' => ['aria_label' => 'Plans'], 'children' => [
            ['id' => $id.'-a', 'type' => 'tab-item', 'data' => ['title' => 'A'], 'children' => []],
        ]];

        // As on a page: the blocks render inside the page view, where @once spans all blocks
        $html = Blade::render('{!! (new \App\Services\BlockRenderer)->render($blocks) !!}', ['blocks' => [$tabs('t1'), $tabs('t2')]]);

        $this->assertSame(1, substr_count($html, 'function tabsSwitch'));
        $this->assertMatchesRegularExpression('/role="tablist"\s+aria-label="Plans"/', $html);
        $this->assertStringContainsString('role="tabpanel" aria-labelledby="tab-t1-a" tabindex="0"', $html);
    }

    /* ── Video ──────────────────────────────────────────────────── */

    public function test_video_gets_captions_transcript_title_and_forced_controls(): void
    {
        $html = $this->render(['type' => 'video', 'data' => [
            'url' => 'https://example.com/clip.mp4',
            'title' => 'Product tour',
            'captions' => 'https://example.com/clip.de.vtt',
            'captions_label' => 'Deutsch',
            'transcript' => "First paragraph.\n\nSecond <b>paragraph</b>.",
            'transcript_url' => 'https://example.com/transcript',
        ], 'settings' => ['autoplay' => true, 'controls' => false, 'captions_srclang' => 'de']]);

        $this->assertStringContainsString('<track kind="captions" src="https://example.com/clip.de.vtt" srclang="de" label="Deutsch" default>', $html);
        $this->assertStringContainsString('aria-label="Product tour"', $html);
        $this->assertMatchesRegularExpression('/<video[^>]*\bcontrols\b/s', $html);
        $this->assertStringContainsString('<p>First paragraph.</p>', $html);
        $this->assertStringContainsString('Second &lt;b&gt;paragraph&lt;/b&gt;.', $html);
        $this->assertStringContainsString('href="https://example.com/transcript"', $html);

        $plain = $this->render(['type' => 'video', 'data' => ['url' => 'https://example.com/clip.mp4'], 'settings' => ['controls' => false]]);
        $this->assertStringNotContainsString('<track', $plain);
        $this->assertStringNotContainsString('site-video-transcript', $plain);
        $this->assertDoesNotMatchRegularExpression('/<video[^>]*\bcontrols\b/s', $plain);
    }

    /* ── Stats ──────────────────────────────────────────────────── */

    public function test_stats_count_up_keeps_the_final_value_for_screen_readers(): void
    {
        $items = [['value' => '1,200', 'label' => 'Customers']];

        $animated = $this->render(['type' => 'stats', 'data' => ['items' => $items], 'settings' => ['animate' => true]]);
        $this->assertStringContainsString('data-count-up', $animated);
        $this->assertStringContainsString('<span class="sr-only">1,200</span><span aria-hidden="true" data-count-up-value>1,200</span>', $animated);

        $static = $this->render(['type' => 'stats', 'data' => ['items' => $items]]);
        $this->assertStringNotContainsString('data-count-up', $static);
        $this->assertStringContainsString('<span class="site-stat-value">1,200</span>', $static);
        $this->assertStringContainsString('role="list"', $static);
        $this->assertStringContainsString('role="listitem"', $static);
    }
}
