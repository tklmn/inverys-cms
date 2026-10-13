<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * Featured images: alt text from the media library, responsive markup,
 * high priority on the post page.
 */
class BlogFeaturedImageTest extends TestCase
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
        Post::query()->forceDelete();
        BlogCategory::query()->delete();
    }

    /** Plugin routes loaded inside a test end up behind the page catch-all — put it back at the end */
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

    private function makePost(string $image): Post
    {
        return Post::create([
            'title' => 'Summer trip',
            'slug' => 'summer-trip',
            'content' => '<p>We went to the lake.</p>',
            'featured_image' => $image,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    private function featuredImg(string $html): string
    {
        $this->assertSame(1, preg_match('#<img[^>]*summer\.jpg"[^>]*>#', $html, $m), 'Featured image is rendered');

        return $m[0];
    }

    public function test_post_page_uses_the_library_alt_text_and_high_priority(): void
    {
        Media::factory()->create([
            'path' => 'media/2026/10/summer.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Canoes on a calm lake',
            'width' => 1600,
            'height' => 900,
            'variants' => [['width' => 768, 'height' => 432, 'path' => 'media/2026/10/summer-768w.webp', 'mime' => 'image/webp']],
        ]);
        $this->makePost('media/2026/10/summer.jpg');

        $img = $this->featuredImg($this->get('/blog/summer-trip')->assertOk()->getContent());

        $this->assertStringContainsString('alt="Canoes on a calm lake"', $img);
        $this->assertStringContainsString('fetchpriority="high"', $img);
        $this->assertStringContainsString('loading="eager"', $img);
        $this->assertStringContainsString('width="1600" height="900"', $img);
        $this->assertStringContainsString('summer-768w.webp 768w', $img);
    }

    public function test_decorative_and_unknown_images_keep_an_empty_alt(): void
    {
        Media::factory()->create(['path' => 'media/2026/10/summer.jpg', 'alt_text' => 'Lake', 'is_decorative' => true]);
        $this->makePost('media/2026/10/summer.jpg');

        $this->assertStringContainsString('alt=""', $this->featuredImg($this->get('/blog/summer-trip')->getContent()));

        Media::query()->forceDelete();
        $this->assertStringContainsString('alt=""', $this->featuredImg($this->get('/blog/summer-trip')->getContent()));
    }

    public function test_list_uses_the_library_alt_text(): void
    {
        Media::factory()->create(['path' => 'media/2026/10/summer.jpg', 'alt_text' => 'Canoes on a calm lake']);
        $this->makePost('media/2026/10/summer.jpg');

        $img = $this->featuredImg($this->get('/blog')->assertOk()->getContent());

        $this->assertStringContainsString('alt="Canoes on a calm lake"', $img);
        $this->assertStringNotContainsString('fetchpriority', $img);
    }
}
