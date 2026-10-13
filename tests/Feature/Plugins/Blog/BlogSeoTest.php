<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * What the blog tells search engines: article data and BlogPosting for
 * posts, CollectionPage for lists, page numbers, date filters and the feed link.
 */
class BlogSeoTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
        $this->loadPlugins(['blog' => 'Blog']);
        $this->pageRouteLast();
        // Without the demo content of the installation
        Post::query()->forceDelete();
        BlogCategory::query()->delete();
    }

    /**
     * In the app, plugin routes are registered before the page catch-all
     * (/{slug}); loaded inside a test they end up behind it and /blog would
     * be taken for a page. Put the catch-all back at the end.
     */
    private function pageRouteLast(): void
    {
        $routes = new RouteCollection;
        $catchAll = null;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->getName() === 'page.show') {
                $catchAll = $route;

                continue;
            }
            $routes->add($route);
        }

        $routes->add($catchAll);
        Route::setRoutes($routes);
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'title' => 'We launched',
            'slug' => 'we-launched',
            'content' => '<p>The new website is online.</p>',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    /** @return array<string, array<string, mixed>> @type => node */
    private function graph(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m));
        $nodes = [];
        foreach (json_decode($m[1], true, 512, JSON_THROW_ON_ERROR)['@graph'] as $node) {
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

    public function test_post_is_an_article_with_blog_posting(): void
    {
        $author = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $post = $this->makePost(['excerpt' => 'Short summary.', 'featured_image' => 'media/launch.jpg', 'author_id' => $author->id]);
        $post->categories()->attach(BlogCategory::create(['name' => 'News', 'slug' => 'news']));

        $html = $this->get('/blog/we-launched')->assertOk()->getContent();

        $this->assertSame('article', $this->metaContent($html, 'property', 'og:type'));
        $this->assertSame('Short summary.', $this->metaContent($html, 'name', 'description'));
        $this->assertSame('http://localhost/storage/media/launch.jpg', $this->metaContent($html, 'property', 'og:image'));
        $this->assertSame($post->published_at->format(DATE_ATOM), $this->metaContent($html, 'property', 'article:published_time'));
        $this->assertNotNull($this->metaContent($html, 'property', 'article:modified_time'));
        $this->assertSame('News', $this->metaContent($html, 'property', 'article:section'));
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/blog/we-launched">', $html);

        $graph = $this->graph($html);
        $this->assertSame('We launched', $graph['BlogPosting']['headline']);
        $this->assertSame('http://localhost/blog/we-launched', $graph['BlogPosting']['mainEntityOfPage']);
        $this->assertSame(['http://localhost/storage/media/launch.jpg'], $graph['BlogPosting']['image']);
        $this->assertSame('Person', $graph['BlogPosting']['author']['@type']);
        $this->assertSame(['Home', 'Blog', 'We launched'], array_column($graph['BreadcrumbList']['itemListElement'], 'name'));
    }

    public function test_post_description_falls_back_to_its_text(): void
    {
        $this->makePost();

        $this->assertSame('The new website is online.', $this->metaContent($this->get('/blog/we-launched')->getContent(), 'name', 'description'));
    }

    public function test_blog_overview_and_category_are_collection_pages(): void
    {
        $category = BlogCategory::create(['name' => 'News', 'slug' => 'news', 'description' => 'What is new.']);
        $category->posts()->attach($this->makePost());

        $graph = $this->graph($this->get('/blog')->assertOk()->getContent());
        $this->assertSame('CollectionPage', $graph['CollectionPage']['@type']);

        $html = $this->get('/blog/category/news')->assertOk()->getContent();
        $this->assertSame('What is new.', $this->metaContent($html, 'name', 'description'));
        $this->assertSame(['Home', 'Blog'], array_slice(array_column($this->graph($html)['BreadcrumbList']['itemListElement'], 'name'), 0, 2));
    }

    public function test_page_numbers_beyond_the_last_page_do_not_exist(): void
    {
        Setting::updateOrCreate(['key' => 'blog.posts_per_page'], ['value' => '1']);
        $this->makePost(['slug' => 'first', 'title' => 'First']);
        $this->makePost(['slug' => 'second', 'title' => 'Second', 'published_at' => now()->subDays(2)]);

        $this->get('/blog?page=3')->assertNotFound();

        $html = $this->get('/blog?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/blog?page=2">', $html);
        $this->assertMatchesRegularExpression('#<title>Blog – Page 2 – [^<]+</title>#', $html);
        // The way back to page 1 has no ?page=1
        $this->assertStringContainsString('href="http://localhost/blog" rel="prev"', $html);
        $this->assertStringNotContainsString('page=1"', $html);

        $this->get('/blog')->assertSee('<link rel="canonical" href="http://localhost/blog">', false);
    }

    public function test_date_filters_are_not_indexed(): void
    {
        $this->makePost();

        $this->get('/blog?year='.now()->year)->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/blog')->assertDontSee('noindex', false);
    }

    public function test_feed_is_announced_in_the_head(): void
    {
        Page::factory()->create(['slug' => 'about-us', 'title' => 'About us']);

        $this->get('/about-us')->assertSee('<link rel="alternate" type="application/rss+xml"', false)
            ->assertSee('href="http://localhost/blog/feed"', false);
    }

    public function test_unpublished_preview_is_noindex(): void
    {
        $this->actingAs($this->createAdmin());
        $this->makePost(['is_active' => false]);

        $html = $this->get('/blog/we-launched')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertSame(1, substr_count($html, '<meta name="robots"'));
    }
}
