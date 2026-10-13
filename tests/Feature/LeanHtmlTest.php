<?php

namespace Tests\Feature;

use App\Http\Middleware\ServeIconSprite;
use App\Models\Page;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Support\HtmlMinifier;
use App\Support\Icon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Lean source: minify HTML (can be switched off) and icons as a sprite —
 * without changing anything about the rendering.
 */
class LeanHtmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    // ── Minification ───────────────────────────────────────────────

    public function test_minifier_drops_indentation_and_comments_but_keeps_raw_blocks(): void
    {
        $html = "<!DOCTYPE html>\n<html>\n    <body>\n        <!-- gone -->\n        <p>Some   text</p>\n"
            ."        <pre>  stays\n    so</pre>\n        <textarea>  a\n  b</textarea>\n"
            ."        <p class=\"whitespace-pre-line\">Line 1\nLine 2</p>\n"
            ."        <!--[if BLOCK]><![endif]--><span>a</span>\n        <span>b</span>\n    </body>\n</html>";

        $min = HtmlMinifier::minify($html);

        $this->assertStringNotContainsString('<!-- gone -->', $min);
        // Spaces in the text are kept (white-space: pre-wrap displays them)
        $this->assertStringContainsString('<p>Some   text</p>', $min);
        $this->assertStringContainsString("<pre>  stays\n    so</pre>", $min);
        $this->assertStringContainsString("<textarea>  a\n  b</textarea>", $min);
        $this->assertStringContainsString('<!--[if BLOCK]><![endif]-->', $min);
        // One space between tags (same as the line break for the browser) …
        $this->assertStringContainsString('<span>a</span> <span>b</span>', $min);
        // … line breaks in the text are kept (white-space: pre-line)
        $this->assertStringContainsString("Line 1\nLine 2", $min);
        $this->assertSame(3, substr_count($min, "\n"), 'only in pre, textarea and the text with a line break');
    }

    public function test_attribute_values_stay_exactly_as_they_are(): void
    {
        // The page builder's blocks come as JSON in the attribute — including indented code
        $json = '{"code":"if (a) {\n    b();   // three spaces\n}","text":"Second   paragraph"}';
        $html = "<html><body>\n    <div\n        class=\"a   b\"\n        data-page-builder='".$json."'\n        data-x=\"a > b\"\n    ></div>\n</body></html>";

        $min = HtmlMinifier::minify($html);

        $this->assertStringContainsString("<div class=\"a   b\" data-page-builder='".$json."' data-x=\"a > b\"></div>", $min);
    }

    public function test_the_page_builder_preview_is_not_minified(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create(['content_type' => 'blocks', 'blocks' => [
            ['id' => 't1', 'type' => 'text', 'data' => ['content' => "<ul>\n  <li>one</li>\n</ul>"], 'settings' => []],
        ]]);

        $html = $this->actingAs($admin)->get(route('backend.pages.canvas', $page))->assertOk()->getContent();

        $this->assertStringContainsString("<ul>\n  <li>one</li>\n</ul>", $html);
    }

    public function test_inline_css_is_minified_without_touching_strings(): void
    {
        $css = "\n  /* Comment */\n  .a ,\n  .b {\n    color : red;\n    margin: 0 auto;\n  }\n  .c::after { content: \"/* stays */ ; { }\"; }\n  @media (min-width: 40rem) {\n    .d { width: calc(100% - 1rem); }\n  }\n";

        $this->assertSame(
            '.a,.b{color : red;margin: 0 auto}.c::after{content: "/* stays */ ; { }"}@media (min-width: 40rem){.d{width: calc(100% - 1rem)}}',
            HtmlMinifier::css($css)
        );
    }

    public function test_inline_javascript_loses_comments_and_indentation_but_keeps_lines(): void
    {
        $js = "\n    // Comment\n    var a = 1\n    /* Block\n       across lines */\n    var b = 'http://x' // stays\n\n    if (a) {\n        b = a\n    }\n";

        $this->assertSame("var a = 1\nvar b = 'http://x' // stays\nif (a) {\nb = a\n}", HtmlMinifier::script($js));

        // Template strings can contain indentation that matters
        $template = "\n    const t = `\n      one\n    `;\n";
        $this->assertSame($template, HtmlMinifier::script($template));
    }

    public function test_json_scripts_are_compacted_and_other_types_left_alone(): void
    {
        $html = "<html><body><script type=\"application/json\" id=\"d\">{\n  \"a\": \"</b>\",\n  \"b\": [1, 2]\n}</script>"
            ."<script type=\"text/template\">\n  <p>  stays</p>\n</script><script src=\"/x.js\"></script></body></html>";

        $min = HtmlMinifier::minify($html);

        $this->assertStringContainsString('<script type="application/json" id="d">{"a":"\\u003C/b\\u003E","b":[1,2]}</script>', $min);
        $this->assertStringContainsString("<script type=\"text/template\">\n  <p>  stays</p>\n</script>", $min);
        $this->assertStringContainsString('<script src="/x.js"></script>', $min);
    }

    public function test_inline_scripts_and_styles_end_up_on_one_line(): void
    {
        $html = "<html><head><style>\n  .a {\n    color: #ffffff;\n  }\n</style></head><body>\n"
            ."<script>\n  // Comment\n  var a = 1;\n  var b = a;\n  const t = `two\n  lines`;\n  window.result = [a, b, t];\n</script>\n"
            ."<script type=\"module\">\n  let x = 1\n  let y = x\n</script>\n</body></html>";

        $min = HtmlMinifier::minify($html);

        preg_match('#<style>(.*?)</style>#s', $min, $style);
        preg_match('#<script>(.*?)</script>#s', $min, $script);
        preg_match('#<script type="module">(.*?)</script>#s', $min, $module);

        $this->assertStringNotContainsString("\n", $style[1]);
        $this->assertStringNotContainsString('Comment', $script[1]);
        // Only the template string keeps its line break
        $this->assertSame(1, substr_count($script[1], "\n"));
        $this->assertStringContainsString("`two\n  lines`", $script[1]);
        // Without a semicolon the line break ends the statement — it is kept
        $this->assertSame("let x=1\nlet y=x", $module[1]);
    }

    public function test_statements_after_blocks_are_joined_without_breaking_else_catch_or_while(): void
    {
        $js = "if (a) {\n  x();\n}\nelse {\n  y();\n}\nvar f = function () {\n  return 1;\n}\nwindow.z = 1;\n"
            ."try {\n  a();\n} catch (e) {\n  b();\n}\nfinally {\n  c();\n}\ndo {\n  i++;\n}\nwhile (i < 3);\n";

        $min = HtmlMinifier::minify('<html><body><script>'.$js.'</script></body></html>');
        preg_match('#<script>(.*?)</script>#s', $min, $script);

        // The break stays only before "while": do … while needs it without ";",
        // a function expression before it needs a separator
        $this->assertSame(1, substr_count($script[1], "\n"));
        $this->assertStringContainsString("}\nwhile(i<3)", $script[1]);
        $this->assertStringContainsString('};window.z=1', $script[1]);
        $this->assertDoesNotMatchRegularExpression('/\};(else|catch|finally|while)\b/', $script[1]);
    }

    public function test_custom_properties_lose_their_line_breaks(): void
    {
        $min = HtmlMinifier::minify("<html><head><style>:root {\n  --glow: radial-gradient(1rem at 0 0, red, transparent),\n          radial-gradient(2rem at 1 1, blue, transparent);\n}</style></head></html>");

        $this->assertStringContainsString('transparent), radial-gradient(2rem', $min);
        $this->assertStringNotContainsString("\n", $min);
    }

    public function test_the_minifier_never_reads_files(): void
    {
        // Content that looks like an existing path stays text
        $file = base_path('composer.json');
        $min = HtmlMinifier::minify('<html><head><style>'.$file.'</style><style>@import "'.$file.'";</style></head><body><script>'.$file.'</script></body></html>');

        $this->assertStringNotContainsString('"require"', $min);
        $this->assertStringContainsString('@import', $min);
    }

    public function test_pages_are_minified_and_keep_the_copyright(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('<!-- Copyright ©', $html);
        $this->assertDoesNotMatchRegularExpression('/\n[ \t]+</', preg_replace('#<(pre|textarea|script|style)\b.*?</\1>#s', '', $html));
    }

    public function test_minify_can_be_switched_off(): void
    {
        Setting::updateOrCreate(['key' => 'html.minify'], ['value' => '0']);
        app(EloquentSettingRepository::class)->clearCache();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/\n[ \t]+</', $html);
    }

    // ── Icon-Sprite ────────────────────────────────────────────────

    public function test_pages_reference_each_icon_once_in_a_sprite(): void
    {
        $html = $this->actingAs($this->createAdmin())->get(route('dashboard'))->assertOk()->getContent();

        preg_match_all('/<symbol id="ui-i-([a-z0-9-]+)"/', $html, $symbols);
        preg_match_all('/<use href="#ui-i-([a-z0-9-]+)">/', $html, $uses);

        $this->assertNotEmpty($uses[1]);
        $this->assertSame(count($symbols[1]), count(array_unique($symbols[1])), 'every icon once');
        $this->assertSame([], array_diff(array_unique($uses[1]), $symbols[1]), 'every reference has its symbol');
        $this->assertStringContainsString('<symbol id="ui-i-', substr($html, (int) strripos($html, '</body>') - 20000));
    }

    public function test_fragments_and_json_get_complete_svgs(): void
    {
        Icon::useSprite(true);
        $svg = Icon::svg('check');
        Icon::useSprite(false);
        $this->assertStringContainsString('<use href="#ui-i-check">', $svg);

        $fragment = ServeIconSprite::apply(new Response('<div>'.$svg.'</div>', 200, ['Content-Type' => 'text/html']));
        $this->assertStringNotContainsString('<use', $fragment->getContent());
        $this->assertStringContainsString('viewBox="0 0 24 24"', $fragment->getContent());
        $this->assertStringContainsString('<path', $fragment->getContent());

        $json = ServeIconSprite::apply(response()->json(['html' => '<span>'.$svg.'</span>']));
        $decoded = json_decode($json->getContent(), true)['html'];
        $this->assertStringNotContainsString('<use', $decoded);
        $this->assertStringContainsString('<path', $decoded);
    }

    public function test_fetch_requests_get_inline_icons(): void
    {
        Icon::useSprite(false);
        $this->assertStringNotContainsString('<use', (string) Icon::svg('check'));
        $this->assertStringContainsString('<path', (string) Icon::svg('check'));
    }
}
