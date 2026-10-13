<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Support\Redirects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Plugins\Blog\Models\Post;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * Settings → SEO → Redirects: manual redirects, automatic ones on slug
 * changes, resolution before the 404 and protection against loops.
 */
class RedirectsTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->admin = $this->createAdmin();
    }

    public function test_admin_manages_redirects(): void
    {
        $this->actingAs($this->admin)->get(route('backend.settings.seo.redirects'))->assertOk()
            ->assertSee(__('translation.redirects.title'));

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.redirects.store'), ['source_path' => 'old-page/', 'target' => '/new-page', 'status_code' => 302])
            ->assertRedirect(route('backend.settings.seo.redirects'))
            ->assertSessionHasNoErrors();

        $redirect = Redirect::sole();
        $this->assertSame('/old-page', $redirect->source_path);
        $this->assertSame(302, $redirect->status_code);
        $this->assertFalse($redirect->is_automatic);

        $this->actingAs($this->admin)
            ->put(route('backend.settings.seo.redirects.update', $redirect), ['source_path' => '/old-page', 'target' => 'https://example.org/elsewhere', 'status_code' => 301])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://example.org/elsewhere', $redirect->fresh()->target);

        $this->actingAs($this->admin)->delete(route('backend.settings.seo.redirects.destroy', $redirect))->assertRedirect();
        $this->assertSame(0, Redirect::count());
    }

    public function test_editors_without_the_seo_permission_have_no_access(): void
    {
        $this->actingAs($this->createEditor())->get(route('backend.settings.seo.redirects'))->assertForbidden();
    }

    public function test_invalid_targets_duplicates_and_the_home_page_are_rejected(): void
    {
        Redirect::create(['source_path' => '/taken', 'target' => '/somewhere']);

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.redirects.store'), ['source_path' => '/', 'target' => 'javascript:alert(1)', 'status_code' => 307])
            ->assertSessionHasErrors(['source_path', 'target', 'status_code']);

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.redirects.store'), ['source_path' => '/taken', 'target' => '/other', 'status_code' => 301])
            ->assertSessionHasErrors('source_path');
    }

    public function test_redirect_loops_are_rejected(): void
    {
        Redirect::create(['source_path' => '/b', 'target' => '/c']);
        Redirect::create(['source_path' => '/c', 'target' => '/a']);

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.redirects.store'), ['source_path' => '/a', 'target' => '/b', 'status_code' => 301])
            ->assertSessionHasErrors('source_path');

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.redirects.store'), ['source_path' => '/self', 'target' => '/self', 'status_code' => 301])
            ->assertSessionHasErrors('source_path');
    }

    public function test_missing_address_is_redirected_and_counted(): void
    {
        Redirect::create(['source_path' => '/old-page', 'target' => '/new-page', 'status_code' => 301]);

        $this->get('/old-page?ref=mail')->assertStatus(301)->assertRedirect('http://localhost/new-page?ref=mail');

        $redirect = Redirect::sole();
        $this->assertSame(1, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function test_addresses_without_a_route_are_redirected_too(): void
    {
        Redirect::create(['source_path' => '/old/section/page', 'target' => 'https://example.org/', 'status_code' => 302]);

        $this->get('/old/section/page')->assertStatus(302)->assertRedirect('https://example.org/');
    }

    public function test_a_live_page_wins_over_a_redirect(): void
    {
        Page::factory()->create(['slug' => 'contact', 'title' => 'Contact']);
        Redirect::create(['source_path' => '/contact', 'target' => '/elsewhere']);

        $this->get('/contact')->assertOk();
    }

    public function test_chains_are_followed_and_circles_end_in_404(): void
    {
        Redirect::create(['source_path' => '/one', 'target' => '/two']);
        Redirect::create(['source_path' => '/two', 'target' => '/three']);
        $this->get('/one')->assertRedirect('http://localhost/three');

        // Written straight into the database, past the validation
        Redirect::create(['source_path' => '/three', 'target' => '/one']);
        $this->get('/one')->assertNotFound();
    }

    public function test_changing_a_page_slug_adds_a_redirect(): void
    {
        $page = Page::factory()->create(['slug' => 'about', 'title' => 'About']);

        $page->update(['slug' => 'about-us']);
        $this->get('/about')->assertStatus(301)->assertRedirect('http://localhost/about-us');
        $this->assertTrue(Redirect::sole()->is_automatic);

        // A second change shortens the chain
        $page->update(['slug' => 'team']);
        $this->get('/about')->assertRedirect('http://localhost/team');
        $this->assertSame('/team', Redirect::where('source_path', '/about')->value('target'));

        // Back to an earlier address: no redirect away from the live page, no loop
        $page->update(['slug' => 'about']);
        $this->get('/about')->assertOk();
        $this->assertFalse(Redirect::where('source_path', '/about')->exists());
        $this->get('/team')->assertRedirect('http://localhost/about');
    }

    public function test_changing_a_page_slug_in_the_editor_adds_a_redirect(): void
    {
        $page = Page::factory()->create(['slug' => 'services', 'title' => 'Services']);

        $this->actingAs($this->admin)
            ->put(route('backend.pages.update', $page), ['title' => 'Services', 'slug' => 'what-we-do'])
            ->assertSessionHasNoErrors();

        $this->get('/services')->assertRedirect('http://localhost/what-we-do');
    }

    public function test_changing_a_post_slug_adds_a_redirect(): void
    {
        Storage::fake('public');
        $this->loadPlugins(['blog' => 'Blog']);

        $post = Post::create(['title' => 'Launch', 'slug' => 'launch', 'is_active' => true, 'published_at' => now()->subDay()]);
        $post->update(['slug' => 'we-launched']);

        $this->get('/blog/launch')->assertStatus(301)->assertRedirect('http://localhost/blog/we-launched');
        $this->get('/blog/we-launched')->assertOk();
    }

    public function test_paths_are_normalized(): void
    {
        $this->assertSame('/a/b', Redirects::normalizePath('a//b/?x=1#top'));
        $this->assertSame('/a', Redirects::normalizePath('http://localhost/a/'));
        $this->assertSame('/', Redirects::normalizePath(''));
        $this->assertSame('/new?x=1', Redirects::normalizeTarget('http://localhost/new/?x=1'));
        $this->assertSame('https://example.org/x', Redirects::normalizeTarget(' https://example.org/x '));
    }
}
