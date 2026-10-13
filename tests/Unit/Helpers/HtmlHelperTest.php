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
}
