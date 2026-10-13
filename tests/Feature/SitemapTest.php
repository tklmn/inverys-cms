<?php

namespace Tests\Feature;

use App\Contracts\ContentTranslator;
use App\Contracts\ExtensionRegistrar;
use App\Models\Page;
use App\Models\Setting;
use App\Sitemap\SitemapGenerator;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class SitemapTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const NS_XHTML = 'http://www.w3.org/1999/xhtml';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    private function fetchSitemap(string $uri = '/sitemap.xml', string $root = 'urlset'): DOMXPath
    {
        $response = $this->get($uri);

        $response->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsStringIgnoringCase('charset=utf-8', $response->headers->get('Content-Type'));

        return $this->parse($response, $root);
    }

    private function parse(TestResponse $response, string $root = 'urlset'): DOMXPath
    {
        $dom = new DOMDocument;
        $this->assertTrue(@$dom->loadXML($response->getContent()), 'Sitemap is not well-formed XML');
        $this->assertSame($root, $dom->documentElement->localName);
        $this->assertSame(self::NS_SITEMAP, $dom->documentElement->namespaceURI);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::NS_SITEMAP);
        $xpath->registerNamespace('xhtml', self::NS_XHTML);

        return $xpath;
    }

    /** @return list<string> */
    private function locs(DOMXPath $xpath): array
    {
        $locs = [];
        foreach ($xpath->query('/s:urlset/s:url/s:loc') as $node) {
            $locs[] = $node->textContent;
        }

        return $locs;
    }

    private function loadBlog(): void
    {
        // The blog's demo content writes images — not into the real storage
        Storage::fake('public');
        $this->loadPlugins(['blog' => 'Blog']);
    }

    public function test_sitemap_lists_home_and_active_pages_with_lastmod(): void
    {
        Page::factory()->root()->create(['updated_at' => '2026-01-02 03:04:05']);
        Page::factory()->create(['slug' => 'about-us', 'title' => 'About us']);

        $xpath = $this->fetchSitemap();
        $locs = $this->locs($xpath);

        $this->assertContains(route('home'), $locs);
        $this->assertContains(route('page.show', 'about-us'), $locs);
        // The home page only appears under the home URL
        $this->assertCount(2, $locs);

        $lastmod = $xpath->query('/s:urlset/s:url[s:loc="'.route('home').'"]/s:lastmod')->item(0)?->textContent;
        $this->assertNotNull($lastmod);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $lastmod);
        $this->assertStringStartsWith('2026-01-02', $lastmod);
    }

    public function test_sitemap_excludes_inactive_noindex_and_external_pages(): void
    {
        Page::factory()->create(['slug' => 'visible']);
        Page::factory()->inactive()->create(['slug' => 'hidden-inactive']);
        Page::factory()->create(['slug' => 'hidden-noindex', 'meta_robots' => 'noindex, follow']);
        Page::factory()->create(['slug' => 'hidden-none', 'meta_robots' => 'NONE']);
        Page::factory()->create(['slug' => 'hidden-external', 'external_url' => 'https://example.org/elsewhere']);
        $deleted = Page::factory()->create(['slug' => 'hidden-deleted']);
        $deleted->delete();

        $locs = $this->locs($this->fetchSitemap());

        $this->assertContains(route('page.show', 'visible'), $locs);
        foreach (['hidden-inactive', 'hidden-noindex', 'hidden-none', 'hidden-external', 'hidden-deleted'] as $slug) {
            $this->assertNotContains(route('page.show', $slug), $locs, "{$slug} must not be listed");
        }
    }

    public function test_sitemap_route_is_not_shadowed_by_the_page_route(): void
    {
        // Even a page with this slug must not hide the sitemap
        Page::factory()->create(['slug' => 'sitemap.xml', 'title' => 'Imposter']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertSame('sitemap', $this->app['router']->getRoutes()->match(
            request()->create('/sitemap.xml')
        )->getName());
        $this->assertStringNotContainsString('Imposter', $response->getContent());
        $this->parse($response);
    }

    public function test_sitemap_works_with_empty_content(): void
    {
        $this->assertSame([], $this->locs($this->fetchSitemap()));
    }

    public function test_sitemap_is_cached(): void
    {
        Page::factory()->create(['slug' => 'first']);
        $this->get('/sitemap.xml')->assertOk();

        Page::factory()->create(['slug' => 'second']);
        $this->assertStringNotContainsString(route('page.show', 'second'), $this->get('/sitemap.xml')->getContent());

        SitemapGenerator::forget();
        $this->assertStringContainsString(route('page.show', 'second'), $this->get('/sitemap.xml')->getContent());
    }

    public function test_plugins_can_register_sitemap_providers_as_callables(): void
    {
        app(ExtensionRegistrar::class)->registerSitemapProvider(fn () => [
            ['loc' => 'http://localhost/custom', 'lastmod' => '2026-05-06'],
            ['loc' => ''], // invalid — is skipped
        ]);
        app(ExtensionRegistrar::class)->registerSitemapProvider(function () {
            throw new \RuntimeException('broken plugin');
        });

        $xpath = $this->fetchSitemap();

        $this->assertSame(['http://localhost/custom'], $this->locs($xpath));
        $this->assertStringStartsWith('2026-05-06T', $xpath->query('//s:lastmod')->item(0)->textContent);
    }

    public function test_sitemap_includes_published_posts_but_not_drafts_or_scheduled(): void
    {
        $this->loadBlog();

        $published = Post::create(['title' => 'Live', 'slug' => 'live-post', 'is_active' => true, 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Draft', 'slug' => 'draft-post', 'is_active' => false, 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Unscheduled', 'slug' => 'no-date-post', 'is_active' => true, 'published_at' => null]);
        Post::create(['title' => 'Scheduled', 'slug' => 'scheduled-post', 'is_active' => true, 'published_at' => now()->addWeek()]);

        $withPosts = BlogCategory::create(['name' => 'With posts', 'slug' => 'with-posts']);
        $withPosts->posts()->attach($published);
        BlogCategory::create(['name' => 'Empty', 'slug' => 'empty-category']);

        $locs = $this->locs($this->fetchSitemap());

        $this->assertContains(route('blog.index'), $locs);
        $this->assertContains(route('blog.show', 'live-post'), $locs);
        $this->assertContains(route('blog.category', 'with-posts'), $locs);

        $this->assertNotContains(route('blog.show', 'draft-post'), $locs);
        $this->assertNotContains(route('blog.show', 'no-date-post'), $locs);
        $this->assertNotContains(route('blog.show', 'scheduled-post'), $locs);
        $this->assertNotContains(route('blog.category', 'empty-category'), $locs);

        // The "Blog" menu item is an external link and does not appear as a page
        $this->assertSame(1, count(array_keys($locs, route('blog.index'))));
    }

    public function test_plain_sitemap_has_no_hreflang_links(): void
    {
        Page::factory()->create(['slug' => 'about-us']);

        $xpath = $this->fetchSitemap();

        $this->assertSame(0, $xpath->query('//xhtml:link')->length);
    }

    private function loadMultilingual(): void
    {
        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
    }

    /** @return array<string, string> hreflang => href of a <url> */
    private function alternatesOf(DOMXPath $xpath, string $loc): array
    {
        $links = [];
        foreach ($xpath->query('/s:urlset/s:url[s:loc="'.$loc.'"]/xhtml:link[@rel="alternate"]') as $link) {
            $links[$link->getAttribute('hreflang')] = $link->getAttribute('href');
        }

        return $links;
    }

    public function test_multilingual_sitemap_xml_is_an_index_of_language_sitemaps(): void
    {
        $this->loadMultilingual();
        Page::factory()->create(['slug' => 'about-us']);

        $xpath = $this->fetchSitemap('/sitemap.xml', 'sitemapindex');

        $locs = [];
        foreach ($xpath->query('/s:sitemapindex/s:sitemap/s:loc') as $node) {
            $locs[] = $node->textContent;
        }

        $this->assertSame([route('sitemap.locale', 'en'), route('sitemap.locale', 'de')], $locs);
        $this->assertSame('http://localhost/sitemap-de.xml', route('sitemap.locale', 'de'));
        $this->assertSame(0, $xpath->query('//s:url')->length);
    }

    public function test_language_sitemaps_list_the_urls_of_their_language_with_alternates(): void
    {
        $this->loadMultilingual();

        Page::factory()->root()->create();
        $translated = Page::factory()->create(['slug' => 'about-us', 'title' => 'About us']);
        app(ContentTranslator::class)->save($translated, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns']);
        // Without a German translation: /de/imprint opens the original (fallback)
        Page::factory()->create(['slug' => 'imprint', 'title' => 'Imprint']);

        $en = $this->fetchSitemap('/sitemap-en.xml');
        $de = $this->fetchSitemap('/sitemap-de.xml');

        $this->assertEqualsCanonicalizing(
            ['http://localhost/', 'http://localhost/about-us', 'http://localhost/imprint'],
            $this->locs($en),
        );
        $this->assertEqualsCanonicalizing(
            ['http://localhost/de', 'http://localhost/de/ueber-uns', 'http://localhost/de/imprint'],
            $this->locs($de),
        );

        $expected = ['en' => 'http://localhost/about-us', 'de' => 'http://localhost/de/ueber-uns', 'x-default' => 'http://localhost/about-us'];
        $this->assertSame($expected, $this->alternatesOf($en, 'http://localhost/about-us'));
        $this->assertSame($expected, $this->alternatesOf($de, 'http://localhost/de/ueber-uns'));

        // The fallback URL can really be opened
        $this->get('/de/imprint')->assertOk();
        $this->get('/de/ueber-uns')->assertOk();
    }

    public function test_unknown_language_sitemap_is_not_found(): void
    {
        $this->loadMultilingual();

        $this->get('/sitemap-fr.xml')->assertNotFound();
    }

    public function test_without_language_sitemaps_the_default_language_sitemap_redirects(): void
    {
        $this->get('/sitemap-en.xml')->assertRedirect(route('sitemap'))->assertStatus(301);
        $this->get('/sitemap-de.xml')->assertNotFound();
    }

    public function test_language_sitemap_route_is_not_shadowed_by_the_page_route(): void
    {
        $this->loadMultilingual();
        Page::factory()->create(['slug' => 'sitemap-de.xml', 'title' => 'Imposter']);

        $this->assertSame('sitemap.locale', $this->app['router']->getRoutes()->match(
            request()->create('/sitemap-de.xml')
        )->getName());
        $this->assertStringNotContainsString('Imposter', $this->get('/sitemap-de.xml')->assertOk()->getContent());
    }

    public function test_disabled_sitemap_is_not_found_and_not_announced(): void
    {
        Setting::create(['key' => 'seo.sitemap_enabled', 'value' => 'off']);

        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/sitemap-en.xml')->assertNotFound();
        $this->assertStringNotContainsString('Sitemap:', $this->get('/robots.txt')->getContent());
    }

    public function test_providers_can_be_switched_off(): void
    {
        $this->loadBlog();

        $post = Post::create(['title' => 'Live', 'slug' => 'live-post', 'is_active' => true, 'published_at' => now()->subDay()]);
        BlogCategory::create(['name' => 'News', 'slug' => 'news-cat'])->posts()->attach($post);
        Page::factory()->create(['slug' => 'about-us']);

        Setting::create(['key' => 'seo.sitemap_include_blog', 'value' => 'off']);
        $locs = $this->locs($this->fetchSitemap());

        $this->assertContains(route('page.show', 'about-us'), $locs);
        $this->assertContains(route('blog.category', 'news-cat'), $locs);
        $this->assertNotContains(route('blog.show', 'live-post'), $locs);
        $this->assertNotContains(route('blog.index'), $locs);

        Setting::where('key', 'seo.sitemap_include_blog')->first()->update(['value' => 'on']);
        Setting::create(['key' => 'seo.sitemap_include_blog_categories', 'value' => 'off']);
        SitemapGenerator::forget();
        $locs = $this->locs($this->fetchSitemap());

        $this->assertContains(route('blog.show', 'live-post'), $locs);
        $this->assertNotContains(route('blog.category', 'news-cat'), $locs);
    }

    public function test_providers_announce_key_and_label_for_the_settings_page(): void
    {
        $this->loadBlog();

        $this->assertSame(
            ['blog' => 'blog::blog.sitemap_posts', 'blog_categories' => 'blog::blog.sitemap_categories'],
            app(SitemapGenerator::class)->toggleableProviders(),
        );
    }

    public function test_cache_can_be_turned_off(): void
    {
        Setting::create(['key' => 'seo.sitemap_cache_ttl', 'value' => '0']);
        Page::factory()->create(['slug' => 'first']);
        $this->get('/sitemap.xml')->assertOk();

        Page::factory()->create(['slug' => 'second']);

        $this->assertStringContainsString(route('page.show', 'second'), $this->get('/sitemap.xml')->getContent());
    }

    public function test_robots_txt_points_to_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nDisallow:\n\nSitemap: ".route('sitemap')."\n", $response->getContent());
    }

    public function test_robots_txt_contains_additional_rules(): void
    {
        Setting::create(['key' => 'seo.robots_extra', 'value' => "User-agent: BadBot\nDisallow: /"]);

        $this->assertSame(
            "User-agent: *\nDisallow:\n\nUser-agent: BadBot\nDisallow: /\n\nSitemap: ".route('sitemap')."\n",
            $this->get('/robots.txt')->getContent(),
        );
    }

    public function test_robots_txt_blocks_everything_in_staging_mode(): void
    {
        Setting::create(['key' => 'seo.robots_allow_indexing', 'value' => 'off']);
        Setting::create(['key' => 'seo.robots_extra', 'value' => "User-agent: Googlebot\nAllow: /"]);

        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->getContent());
    }
}
