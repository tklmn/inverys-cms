<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu items pointing to the own website are stored as a path and resolved
 * when read — that way they survive domain and directory changes.
 */
class SiteLinkPathsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_own_full_url_is_stored_as_path(): void
    {
        config(['app.url' => 'https://example.test/cms']);

        $page = Page::factory()->create(['external_url' => 'https://example.test/cms/blog']);

        $this->assertSame('/blog', $page->getRawOriginal('external_url'));
    }

    public function test_foreign_url_is_stored_unchanged(): void
    {
        $page = Page::factory()->create(['external_url' => 'https://github.com/tklmn']);

        $this->assertSame('https://github.com/tklmn', $page->getRawOriginal('external_url'));
        $this->assertSame('https://github.com/tklmn', $page->external_url);
    }

    public function test_path_resolves_against_the_current_site(): void
    {
        $page = Page::factory()->create(['external_url' => '/blog']);

        $this->assertSame(url('/blog'), $page->external_url);
    }

    public function test_admin_form_accepts_paths_and_rejects_unsafe_values(): void
    {
        $admin = $this->createAdmin();
        $payload = ['title' => 'Blog', 'slug' => 'blog-link', 'content_type' => 'html', 'is_active' => true];

        $this->actingAs($admin)
            ->post(route('backend.pages.store'), $payload + ['external_url' => '/blog'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pages', ['slug' => 'blog-link', 'external_url' => '/blog']);

        foreach (['javascript:alert(1)', '//evil.example/x', 'blog'] as $bad) {
            $this->actingAs($admin)
                ->post(route('backend.pages.store'), ['slug' => 'bad-'.md5($bad)] + $payload + ['external_url' => $bad])
                ->assertSessionHasErrors('external_url');
        }
    }
}
