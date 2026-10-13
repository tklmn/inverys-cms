<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Support\Seo\Descriptions;
use App\Support\Seo\LanguageCodes;
use App\Support\Seo\SeoContext;
use App\Support\Seo\SeoHead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The core SEO head hook: description, canonical, Open Graph, Twitter and
 * JSON-LD of every frontend page — and the title format from Settings → SEO.
 */
class SeoHeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    private function setting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    private function page(array $attributes = []): Page
    {
        return Page::factory()->create(array_merge([
            'title' => 'About us',
            'slug' => 'about-us',
            'meta_title' => null,
            'meta_description' => null,
            'content' => '<p>We build websites.</p>',
        ], $attributes));
    }

    /** @return array<string, mixed> the JSON-LD document of the page */
    private function jsonLd(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m), 'No JSON-LD in the page');
        $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $data['@context']);

        return $data;
    }

    /** @return array<string, array<string, mixed>> @type => node */
    private function graph(string $html): array
    {
        $nodes = [];
        foreach ($this->jsonLd($html)['@graph'] as $node) {
            $nodes[$node['@type']] = $node;
        }

        return $nodes;
    }

    private function metaContent(string $html, string $attribute, string $name): ?string
    {
        return preg_match('#<meta '.$attribute.'="'.preg_quote($name, '#').'" content="([^"]*)">#', $html, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)
            : null;
    }

    public function test_page_gets_a_self_referencing_canonical_without_query(): void
    {
        $this->page(['meta_description' => 'Who we are']);

        $html = $this->get('/about-us?utm_source=newsletter')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/about-us">', $html);
        $this->assertSame('http://localhost/about-us', $this->metaContent($html, 'property', 'og:url'));
        $this->assertSame(1, substr_count($html, '<meta name="description"'));
        $this->assertSame('Who we are', $this->metaContent($html, 'name', 'description'));
    }

    public function test_home_page_canonical_has_a_trailing_slash(): void
    {
        Page::factory()->root()->create(['title' => 'Welcome']);

        $this->get('/')->assertOk()->assertSee('<link rel="canonical" href="http://localhost/">', false);
    }

    public function test_explicit_canonical_url_is_an_override(): void
    {
        $this->page(['canonical_url' => 'https://example.org/original']);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="https://example.org/original">', $html);
        $this->assertSame('https://example.org/original', $this->metaContent($html, 'property', 'og:url'));
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
    }

    public function test_open_graph_and_twitter_tags_with_fallbacks(): void
    {
        $this->setting('site_name', 'Example Site');
        $this->page(['meta_title' => 'About our team', 'meta_description' => 'Who we are']);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame('website', $this->metaContent($html, 'property', 'og:type'));
        $this->assertSame('Example Site', $this->metaContent($html, 'property', 'og:site_name'));
        // og_title → meta_title → title; og_description → meta description
        $this->assertSame('About our team', $this->metaContent($html, 'property', 'og:title'));
        $this->assertSame('Who we are', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('en_US', $this->metaContent($html, 'property', 'og:locale'));
        // Without any image: the small card
        $this->assertSame('summary', $this->metaContent($html, 'name', 'twitter:card'));
        $this->assertSame('About our team', $this->metaContent($html, 'name', 'twitter:title'));
        $this->assertNull($this->metaContent($html, 'property', 'og:image'));
    }

    public function test_own_social_fields_and_image_with_size(): void
    {
        $this->page([
            'og_title' => 'Meet the team',
            'og_description' => 'Twelve people, one office.',
            'og_image' => 'apple-touch-icon.png',
        ]);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertSame('Meet the team', $this->metaContent($html, 'property', 'og:title'));
        $this->assertSame('Twelve people, one office.', $this->metaContent($html, 'property', 'og:description'));
        $this->assertSame('http://localhost/apple-touch-icon.png', $this->metaContent($html, 'property', 'og:image'));
        $this->assertSame('180', $this->metaContent($html, 'property', 'og:image:width'));
        $this->assertSame('180', $this->metaContent($html, 'property', 'og:image:height'));
        $this->assertSame('Meet the team', $this->metaContent($html, 'property', 'og:image:alt'));
        $this->assertSame('summary_large_image', $this->metaContent($html, 'name', 'twitter:card'));
        $this->assertSame('http://localhost/apple-touch-icon.png', $this->metaContent($html, 'name', 'twitter:image'));
    }

    public function test_image_falls_back_to_header_image_then_default_and_skips_svg(): void
    {
        $page = $this->page(['header_image' => 'storage/media/header.jpg', 'og_image' => 'storage/media/drawing.svg']);

        $html = $this->get('/about-us')->getContent();
        $this->assertSame('http://localhost/storage/media/header.jpg', $this->metaContent($html, 'property', 'og:image'));

        $page->update(['header_image' => null, 'og_image' => null]);
        $this->setting('seo.default_og_image', 'https://cdn.example.org/share.png');

        $html = $this->get('/about-us')->getContent();
        $this->assertSame('https://cdn.example.org/share.png', $this->metaContent($html, 'property', 'og:image'));
    }

    public function test_description_falls_back_to_subtitle_text_and_default(): void
    {
        $page = $this->page(['subtitle' => 'Small team, big websites']);
        $this->assertSame('Small team, big websites', $this->metaContent($this->get('/about-us')->getContent(), 'name', 'description'));

        $page->update(['subtitle' => null, 'blocks' => [Page::textBlock('<h2>Hello</h2><p>We build <strong>fast</strong> websites.</p>', 'b1')]]);
        $this->assertSame('Hello We build fast websites.', $this->metaContent($this->get('/about-us')->getContent(), 'name', 'description'));

        $page->update(['blocks' => []]);
        $this->setting('seo.default_description', 'A website about websites.');
        $this->assertSame('A website about websites.', $this->metaContent($this->get('/about-us')->getContent(), 'name', 'description'));
    }

    public function test_long_text_is_shortened_at_a_word_boundary(): void
    {
        $text = str_repeat('Lorem ipsum dolor sit amet ', 20);
        $short = Descriptions::pick($text);

        $this->assertLessThanOrEqual(Descriptions::MAX_LENGTH, mb_strlen($short));
        $this->assertStringEndsWith('…', $short);
        $this->assertStringNotContainsString('  ', $short);
    }

    public function test_home_page_has_website_with_search_action_and_organization(): void
    {
        $this->setting('site_name', 'Example Site');
        $this->setting('seo.social_profiles', "https://www.linkedin.com/company/example\nhttps://github.com/example");
        Page::factory()->root()->create(['title' => 'Welcome', 'meta_description' => 'Hello']);

        $graph = $this->graph($this->get('/')->assertOk()->getContent());

        $this->assertSame('Example Site', $graph['WebSite']['name']);
        $this->assertSame('http://localhost/', $graph['WebSite']['url']);
        $this->assertSame('http://localhost/search?q={search_term_string}', $graph['WebSite']['potentialAction']['target']['urlTemplate']);
        $this->assertSame('Example Site', $graph['Organization']['name']);
        $this->assertSame(['https://www.linkedin.com/company/example', 'https://github.com/example'], $graph['Organization']['sameAs']);
        $this->assertSame('http://localhost/images/logo.svg', $graph['Organization']['logo']['url']);
        $this->assertSame('WebPage', $graph['WebPage']['@type']);
        $this->assertArrayNotHasKey('BreadcrumbList', $graph);
    }

    public function test_no_search_action_when_guests_may_not_search(): void
    {
        $this->setting('search.guest_access', 'off');
        Page::factory()->root()->create();

        $graph = $this->graph($this->get('/')->getContent());

        $this->assertArrayNotHasKey('potentialAction', $graph['WebSite']);
    }

    public function test_subpage_has_web_page_and_breadcrumb_of_its_ancestors(): void
    {
        $root = Page::factory()->root()->create();
        $company = Page::factory()->create(['title' => 'Company', 'slug' => 'company']);
        $this->page(['parent_id' => $company->id]);

        $graph = $this->graph($this->get('/about-us')->assertOk()->getContent());

        $this->assertArrayNotHasKey('WebSite', $graph);
        $this->assertSame('http://localhost/about-us', $graph['WebPage']['url']);
        $items = $graph['BreadcrumbList']['itemListElement'];
        $this->assertSame(['Home', 'Company', 'About us'], array_column($items, 'name'));
        $this->assertSame(['http://localhost/', 'http://localhost/company', 'http://localhost/about-us'], array_column($items, 'item'));
        $this->assertSame([1, 2, 3], array_column($items, 'position'));
        $this->assertNotNull($root);
    }

    public function test_json_ld_cannot_close_its_script_element(): void
    {
        $this->page(['title' => 'Evil </script><script>alert(1)</script> & co']);

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertSame('Evil </script><script>alert(1)</script> & co', $this->graph($html)['WebPage']['name']);
        $this->assertMatchesRegularExpression('#<script type="application/ld\+json" nonce="[^"]+">#', $html);
    }

    public function test_robots_of_the_page_are_printed_once(): void
    {
        $this->page(['meta_robots' => 'noindex, follow']);

        $html = $this->get('/about-us')->getContent();

        $this->assertSame(1, substr_count($html, '<meta name="robots"'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        // A noindex page names no canonical address and carries no JSON-LD
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    public function test_search_results_are_noindex_follow(): void
    {
        $html = $this->get('/search?q=websites')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('websites', (string) $this->metaContent($html, 'name', 'description'));
    }

    public function test_error_pages_get_no_seo_tags(): void
    {
        $html = $this->get('/does-not-exist')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('og:title', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
    }

    public function test_verification_codes_appear_on_the_home_page_only(): void
    {
        $this->setting('seo.google_verification', 'abc123_-XYZ');
        $this->setting('seo.bing_verification', 'B1NG');
        Page::factory()->root()->create();
        $this->page();

        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123_-XYZ">', false)
            ->assertSee('<meta name="msvalidate.01" content="B1NG">', false);
        $this->get('/about-us')->assertDontSee('google-site-verification', false);
    }

    public function test_icons_and_manifest_are_linked_when_the_files_exist(): void
    {
        $this->page();

        $this->get('/about-us')
            ->assertSee('<link rel="apple-touch-icon" href="http://localhost/apple-touch-icon.png">', false)
            ->assertSee('<link rel="manifest" href="http://localhost/site.webmanifest">', false);
    }

    public function test_tags_the_theme_prints_itself_are_not_repeated(): void
    {
        $seo = app(SeoContext::class);
        $seo->fill(['title' => 'About', 'description' => 'From the context']);
        $seo->respond(200, '<head><meta name="description" content="From the theme"><link rel="canonical" href="https://example.org/x"><meta property="og:title" content="Theme">');

        $html = app(SeoHead::class)->render();

        $this->assertStringNotContainsString('<meta name="description"', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:type"', $html);
    }

    public function test_title_format_follows_the_settings(): void
    {
        $this->setting('site_name', 'Example Site');
        $this->page();

        $this->get('/about-us')->assertSee('<title>About us – Example Site</title>', false);

        $this->setting('seo.title_order', 'site_title');
        $this->setting('seo.title_separator', '|');
        $this->get('/about-us')->assertSee('<title>Example Site | About us</title>', false);

        $this->setting('seo.title_order', 'title');
        $this->get('/about-us')->assertSee('<title>About us</title>', false);
    }

    public function test_letter_case_variant_of_a_slug_redirects_to_the_stored_one(): void
    {
        $this->page();

        $this->get('/About-Us?x=1')->assertStatus(301)->assertRedirect('http://localhost/about-us?x=1');
    }

    public function test_language_codes_in_standard_formats(): void
    {
        $this->assertSame('pt-BR', LanguageCodes::hreflang('pt_BR'));
        $this->assertSame('zh-Hant', LanguageCodes::hreflang('zh_hant'));
        $this->assertSame('de', LanguageCodes::hreflang('de'));
        $this->assertSame('x-default', LanguageCodes::hreflang('x-default'));
        $this->assertSame('de_DE', LanguageCodes::ogLocale('de'));
        $this->assertSame('pt_BR', LanguageCodes::ogLocale('pt-BR'));
        $this->assertSame('en_US', LanguageCodes::ogLocale('en'));
        $this->assertNull(LanguageCodes::ogLocale('xx'));
    }
}
