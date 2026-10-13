<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Http\Controllers\BlogController;
use Plugins\Blog\Models\Post;
use Plugins\Blog\Search\BlogSearchProvider;
use Tests\TestCase;

/**
 * Frontend preview of unpublished posts: only with edit-post, with a notice,
 * noindex and no caching — everyone else gets a 404.
 */
class BlogPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->createBlogTables();

        Plugin::create([
            'slug' => 'blog',
            'name' => 'Blog',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);
        Setting::create(['key' => 'blog.url_prefix', 'value' => 'blog']);
        Setting::create(['key' => 'blog.posts_per_page', 'value' => '10']);

        $pm = app(PluginManager::class);
        $pm->discover();
        $pm->boot();
        $pm->getLoadedProviders()['blog']->syncPermissions();

        app('router')->getRoutes()->refreshNameLookups();
    }

    private function createBlogTables(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->string('content_type', 20)->default('html');
            $table->json('blocks')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blog_category_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete();
            $table->unique(['post_id', 'blog_category_id']);
        });
    }

    private function makePost(string $slug, bool $active, ?string $publishedAt): Post
    {
        return Post::create([
            'title' => 'Title '.$slug,
            'slug' => $slug,
            'content' => '<p>Body of '.$slug.'</p>',
            'is_active' => $active,
            'published_at' => $publishedAt,
        ]);
    }

    private function draft(): Post
    {
        return $this->makePost('draft-post', false, null);
    }

    private function scheduled(): Post
    {
        return $this->makePost('scheduled-post', true, now()->addDays(3)->toDateTimeString());
    }

    private function published(): Post
    {
        return $this->makePost('published-post', true, now()->subDay()->toDateTimeString());
    }

    public function test_admin_sees_draft_with_preview_banner_and_noindex(): void
    {
        $this->draft();

        $response = $this->actingAs($this->createAdmin())->get('/blog/draft-post');

        $response->assertOk()
            ->assertSee('Body of draft-post', false)
            ->assertSee('site-alert-preview', false)
            ->assertSee(__('blog::blog.preview_draft'))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_editor_sees_scheduled_post_with_date_in_banner(): void
    {
        $post = $this->scheduled();

        $this->actingAs($this->createEditor())->get('/blog/scheduled-post')
            ->assertOk()
            ->assertSee('site-alert-preview', false)
            ->assertSee(__('blog::blog.preview_scheduled', ['date' => format_datetime($post->published_at)]))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_guest_gets_404_for_draft_and_scheduled_posts(): void
    {
        $this->draft();
        $this->scheduled();

        $this->get('/blog/draft-post')->assertNotFound();
        $this->get('/blog/scheduled-post')->assertNotFound();
    }

    public function test_user_without_edit_permission_gets_404(): void
    {
        $this->draft();
        $this->scheduled();

        // May see posts in the backend, but not edit them
        $role = Role::create(['name' => 'post-viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('name', ['access-dashboard', 'show-post'])->pluck('id'));
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role->id);

        $this->actingAs($viewer)->get('/blog/draft-post')->assertNotFound();
        $this->actingAs($viewer)->get('/blog/scheduled-post')->assertNotFound();
        $this->actingAs($this->createUser())->get('/blog/scheduled-post')->assertNotFound();
    }

    public function test_published_post_is_unchanged(): void
    {
        $this->published();

        foreach ([null, $this->createAdmin()] as $user) {
            $request = $user ? $this->actingAs($user) : $this;

            $request->get('/blog/published-post')
                ->assertOk()
                ->assertSee('Body of published-post', false)
                ->assertDontSee('site-alert-preview', false)
                ->assertDontSee('noindex', false)
                ->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_prev_next_never_links_unpublished_posts(): void
    {
        $this->published();
        $this->scheduled();
        $this->makePost('inactive-post', false, now()->toDateTimeString());

        // Public: no link to the scheduled or inactive post
        $this->get('/blog/published-post')
            ->assertOk()
            ->assertDontSee('Title scheduled-post')
            ->assertDontSee('Title inactive-post');

        // In the preview, too, prev/next only leads to published posts
        $this->actingAs($this->createAdmin())->get('/blog/scheduled-post')
            ->assertOk()
            ->assertSee('Title published-post')
            ->assertDontSee('Title inactive-post');
    }

    public function test_listing_and_feed_exclude_unpublished_posts_even_for_admins(): void
    {
        $this->published();
        $this->draft();
        $this->scheduled();
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // Listing directly via the controller: in tests the page route /{slug}
        // comes before the late-registered plugin route /blog
        $view = app(BlogController::class)->index(Request::create('/blog'));
        $this->assertSame(['published-post'], $view->getData()['posts']->pluck('slug')->all());

        $this->get('/blog/feed')
            ->assertOk()
            ->assertSee('Title published-post')
            ->assertDontSee('Title draft-post')
            ->assertDontSee('Title scheduled-post');

        $hits = (new BlogSearchProvider)->search('Title', ['title' => true]);
        $this->assertSame(['Title published-post'], $hits->pluck('title')->all());
    }

    public function test_backend_list_offers_preview_for_unpublished_posts(): void
    {
        $this->draft();
        $this->published();

        $this->actingAs($this->createEditor())->get(route('backend.blog.index'))
            ->assertOk()
            ->assertSee('href="'.route('blog.show', 'draft-post').'"', false)
            ->assertSee(__('blog::blog.preview'))
            ->assertSee('href="'.route('blog.show', 'published-post').'"', false)
            ->assertSee(__('blog::blog.view_post'));
    }

    public function test_backend_list_disables_preview_without_edit_permission(): void
    {
        $this->draft();

        $role = Role::create(['name' => 'post-viewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('name', ['access-dashboard', 'show-post'])->pluck('id'));
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role->id);

        $this->actingAs($viewer)->get(route('backend.blog.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('blog.show', 'draft-post').'"', false)
            ->assertSee(__('blog::blog.preview_unavailable'));
    }

    public function test_edit_page_links_to_preview(): void
    {
        $post = $this->scheduled();

        $this->actingAs($this->createEditor())->get(route('backend.blog.edit', $post))
            ->assertOk()
            ->assertSee('href="'.route('blog.show', 'scheduled-post').'"', false)
            ->assertSee(__('blog::blog.preview'));
    }
}
