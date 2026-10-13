<?php

namespace Tests\Unit\Helpers;

use App\Helpers\HtmlHelper;
use Tests\TestCase;

class HtmlHelperTest extends TestCase
{
    public function test_clean_removes_script_tags(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script>';
        $result = HtmlHelper::clean($html);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_clean_removes_onclick(): void
    {
        $html = '<p onclick="alert(1)">Click me</p>';
        $result = HtmlHelper::clean($html);

        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_clean_allows_safe_html(): void
    {
        $html = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <em>italic</em></p>';
        $result = HtmlHelper::clean($html);

        $this->assertStringContainsString('<h1>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('<em>', $result);
    }

    public function test_clean_allows_links_with_href(): void
    {
        $html = '<a href="https://example.com" target="_blank">Link</a>';
        $result = HtmlHelper::clean($html);

        $this->assertStringContainsString('href="https://example.com"', $result);
    }

    public function test_clean_blocks_javascript_urls(): void
    {
        $html = '<a href="javascript:alert(1)">Bad link</a>';
        $result = HtmlHelper::clean($html);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_clean_empty_input(): void
    {
        $this->assertEquals('', HtmlHelper::clean(''));
        $this->assertEquals('', HtmlHelper::clean(null));
    }

    public function test_clean_css_removes_expression(): void
    {
        $css = 'body { background: expression(alert(1)); }';
        $result = HtmlHelper::cleanCss($css);

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringContainsString('/* removed */', $result);
    }

    public function test_clean_css_removes_javascript_url(): void
    {
        $css = 'body { background: url("javascript:alert(1)"); }';
        $result = HtmlHelper::cleanCss($css);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_clean_css_removes_import(): void
    {
        $css = '@import url("https://evil.com/style.css"); body { color: red; }';
        $result = HtmlHelper::cleanCss($css);

        $this->assertStringNotContainsString('@import', $result);
    }

    public function test_clean_css_allows_safe_css(): void
    {
        $css = 'body { color: red; font-size: 16px; margin: 0; }';
        $result = HtmlHelper::cleanCss($css);

        $this->assertEquals($css, $result);
    }

    public function test_clean_css_empty_input(): void
    {
        $this->assertEquals('', HtmlHelper::cleanCss(''));
        $this->assertEquals('', HtmlHelper::cleanCss(null));
    }

    public function test_resolve_storage_paths(): void
    {
        $html = '<img src="storage/media/2026/03/photo.jpg" />';
        $result = HtmlHelper::resolveStoragePaths($html);

        $this->assertStringContainsString('/storage/media/2026/03/photo.jpg', $result);
        $this->assertStringNotContainsString('src="storage/', $result);
    }

    public function test_clean_keeps_layout_classes_and_text_markup(): void
    {
        $result = HtmlHelper::clean('<p class="text-center text-gray-600">Hallo <mark>wichtig</mark> <small>klein</small></p><h2 class="mb-4">Titel</h2>');

        $this->assertStringContainsString('<p class="text-center text-gray-600">', $result);
        $this->assertStringContainsString('<mark>wichtig</mark>', $result);
        $this->assertStringContainsString('<small>klein</small>', $result);
        $this->assertStringContainsString('<h2 class="mb-4">', $result);
    }

    public function test_internal_links_get_the_full_address(): void
    {
        $result = HtmlHelper::clean('<p><a href="/contact">Kontakt</a> <a href="https://example.com">extern</a> <a href="//cdn.example.com/x">cdn</a></p>');

        $this->assertStringContainsString('href="'.url('/contact').'"', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('href="//cdn.example.com/x"', $result);
        $this->assertSame(url('/about'), HtmlHelper::safeUrl('/about'));
        $this->assertSame('#', HtmlHelper::safeUrl('javascript:alert(1)'));
    }

    public function test_clean_keeps_language_of_parts(): void
    {
        $result = HtmlHelper::clean('<p lang="fr">Bonjour <span lang="en-GB" class="x">hello</span></p><h2 lang="de">Titel</h2><ul><li lang="es">Hola</li></ul><blockquote lang="it">Ciao</blockquote><table><tbody><tr><td lang="nl">Hallo</td></tr></tbody></table>');

        $this->assertStringContainsString('<p lang="fr">', $result);
        $this->assertStringContainsString('<span lang="en-gb" class="x">hello</span>', $result);
        $this->assertStringContainsString('<h2 lang="de">', $result);
        $this->assertStringContainsString('<li lang="es">', $result);
        $this->assertStringContainsString('<blockquote lang="it">', $result);
        $this->assertStringContainsString('<td lang="nl">', $result);
    }

    public function test_clean_drops_invalid_language_codes(): void
    {
        $result = HtmlHelper::clean('<p lang="de&quot; onmouseover=&quot;alert(1)">a</p><span lang="12$">b</span><span lang="x" onclick="alert(1)">c</span>');

        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('lang="12$"', $result);
        $this->assertStringContainsString('<p>a</p>', $result);
    }

    public function test_clean_keeps_semantic_text_markup(): void
    {
        $result = HtmlHelper::clean('<p><abbr title="World Wide Web">WWW</abbr> <q cite="https://example.com/source">Quote</q> <cite>Book</cite></p><dl><dt>Term</dt><dd>Definition</dd></dl>');

        $this->assertStringContainsString('<abbr title="World Wide Web">WWW</abbr>', $result);
        $this->assertStringContainsString('<q cite="https://example.com/source">Quote</q>', $result);
        $this->assertStringContainsString('<cite>Book</cite>', $result);
        $this->assertStringContainsString('<dl><dt>Term</dt><dd>Definition</dd></dl>', $result);
    }

    public function test_clean_blocks_dangerous_quote_sources(): void
    {
        $result = HtmlHelper::clean('<q cite="javascript:alert(1)">q</q><blockquote cite="javascript:alert(1)">b</blockquote>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_clean_keeps_accessible_tables(): void
    {
        $result = HtmlHelper::clean('<table><caption>Prices</caption><thead><tr><th scope="col" abbr="Qty" colspan="2">Quantity</th><th scope="foo">X</th></tr></thead><tbody><tr><th scope="row" rowspan="2">A</th><td headers="h1 h2" colspan="2" rowspan="1">1</td></tr></tbody><tfoot><tr><td>Total</td></tr></tfoot></table>');

        $this->assertStringContainsString('<caption>Prices</caption>', $result);
        $this->assertStringContainsString('<th scope="col" abbr="Qty" colspan="2">', $result);
        $this->assertStringContainsString('<th>X</th>', $result);
        $this->assertStringContainsString('<th scope="row" rowspan="2">', $result);
        $this->assertStringContainsString('<td headers="h1 h2" colspan="2" rowspan="1">', $result);
        $this->assertStringContainsString('<tfoot><tr><td>Total</td></tr></tfoot>', $result);
    }

    public function test_clean_keeps_heading_ids_for_anchors_but_not_reserved_or_duplicate_ones(): void
    {
        $result = HtmlHelper::clean('<h2 id="pricing">Pricing</h2><h3 id="pricing">Again</h3><h2 id="main-content">Hijack</h2><p id="para">Text</p><h2 id="x&quot; onclick=&quot;y">Bad</h2>');

        $this->assertStringContainsString('<h2 id="pricing">Pricing</h2>', $result);
        $this->assertStringContainsString('<h3>Again</h3>', $result);
        $this->assertStringContainsString('<h2>Hijack</h2>', $result);
        $this->assertStringContainsString('<p>Text</p>', $result);
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_clean_keeps_link_labels_and_titles(): void
    {
        $result = HtmlHelper::clean('<a href="https://example.com" title="Example site" aria-label="Read more about pricing">Read more</a><a href="https://example.com" aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">x</a>');

        $this->assertStringContainsString('title="Example site"', $result);
        $this->assertStringContainsString('aria-label="Read more about pricing"', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_clean_respects_the_link_target_chosen_in_the_editor(): void
    {
        $result = HtmlHelper::clean('<a href="https://example.com">Same tab</a>');

        $this->assertStringNotContainsString('target=', $result);
        $this->assertStringNotContainsString(__('translation.frontend.opens_in_new_tab'), $result);
    }

    public function test_new_tab_links_get_safe_rel_and_a_screen_reader_note(): void
    {
        $result = HtmlHelper::clean('<a href="https://example.com" target="_blank" rel="opener">Partner</a>');
        $note = __('translation.frontend.opens_in_new_tab');

        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertMatchesRegularExpression('#rel="[^"]*\bnoopener\b[^"]*"#', $result);
        $this->assertMatchesRegularExpression('#rel="[^"]*\bnoreferrer\b[^"]*"#', $result);
        $this->assertDoesNotMatchRegularExpression('#rel="[^"]*(?<!no)opener\b#', $result);
        $this->assertStringContainsString('Partner<span class="sr-only"> '.e($note).'</span></a>', $result);
    }

    public function test_new_tab_links_with_aria_label_carry_the_note_in_the_label(): void
    {
        $result = HtmlHelper::clean('<a href="https://example.com" target="_blank" aria-label="Partner site">Partner</a>');
        $note = __('translation.frontend.opens_in_new_tab');

        $this->assertStringContainsString('aria-label="'.e('Partner site '.$note).'"', $result);
        $this->assertStringNotContainsString('sr-only', $result);
    }

    public function test_new_tab_note_is_added_only_once(): void
    {
        $once = HtmlHelper::announceNewTabLinks('<a href="https://example.com" target="_blank">Partner</a>');

        $this->assertSame($once, HtmlHelper::announceNewTabLinks($once));
        $this->assertSame(1, substr_count($once, 'sr-only'));
    }

    public function test_clean_keeps_decorative_image_marking_only(): void
    {
        $result = HtmlHelper::clean('<img src="https://example.com/a.png" alt="" role="presentation"><img src="https://example.com/b.png" alt="Chart" role="button">');

        $this->assertStringContainsString('alt="" role="presentation"', $result);
        $this->assertStringNotContainsString('role="button"', $result);
        $this->assertStringContainsString('alt="Chart"', $result);
    }
}
