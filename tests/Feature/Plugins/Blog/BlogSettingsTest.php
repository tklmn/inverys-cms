<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * The blog prefix is managed only in the blog settings; the menu entry
 * follows it. Another page with this slug would have vanished behind the
 * blog routes — that is rejected.
 */
class BlogSettingsTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->loadPlugins(['blog' => 'Blog']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'url_prefix' => 'blog',
            'posts_per_page' => 10,
            'columns' => 1,
            'sort_by' => 'published_at',
            'sort_direction' => 'desc',
        ], $overrides);
    }

    public function test_prefix_used_by_another_page_is_rejected(): void
    {
        Page::factory()->create(['slug' => 'news', 'title' => 'News archive']);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.blog.settings.update'), $this->payload(['url_prefix' => 'news']))
            ->assertSessionHasErrors('url_prefix');

        $this->assertSame('blog', Setting::where('key', 'blog.url_prefix')->value('value'));
    }

    public function test_blog_menu_entry_follows_the_prefix(): void
    {
        $menu = Page::where('slug', 'blog')->first()
            ?? Page::factory()->create(['slug' => 'blog', 'title' => 'Blog', 'external_url' => '/blog']);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.blog.settings.update'), $this->payload(['url_prefix' => 'journal']))
            ->assertSessionHasNoErrors();

        $this->assertSame('journal', Setting::where('key', 'blog.url_prefix')->value('value'));
        $this->assertSame('journal', $menu->fresh()->slug);
    }
}
