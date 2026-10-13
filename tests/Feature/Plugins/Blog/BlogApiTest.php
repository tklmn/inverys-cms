<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Services\PluginManager;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->enableBlogPlugin();

        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);
        $this->token = $result['plain_text'];
    }

    protected function enableBlogPlugin(): void
    {
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

        app('router')->getRoutes()->refreshNameLookups();

        // Add blog permissions to admin role
        $blogPerms = [
            'show-post', 'create-post', 'edit-post', 'delete-post',
            'publish-post', 'schedule-post', 'draft-post',
            'show-blog-category', 'create-blog-category', 'edit-blog-category', 'delete-blog-category',
            'blog-settings',
        ];

        $adminRole = Role::where('name', 'admin')->first();
        $adminPermIds = $adminRole->permissions()->pluck('permissions.id')->toArray();

        foreach ($blogPerms as $permName) {
            $perm = Permission::firstOrCreate(['name' => $permName]);
            $adminPermIds[] = $perm->id;
        }

        $adminRole->permissions()->sync(array_unique($adminPermIds));
    }

    protected function createBlogTables(): void
    {
        if (! Schema::hasTable('posts')) {
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
                $table->index('published_at');
                $table->index('is_active');
            });
        }

        if (! Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_category_post')) {
            Schema::create('blog_category_post', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete();
                $table->unique(['post_id', 'blog_category_id']);
            });
        }
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    // -- Public read endpoints ------------------------------------------------

    public function test_can_list_published_posts(): void
    {
        Post::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'content' => '<p>Content</p>',
            'is_active' => true,
            'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/blog/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Published Post']);
    }

    public function test_can_show_a_single_post_by_slug(): void
    {
        Post::create([
            'title' => 'Single Post',
            'slug' => 'single-post',
            'content' => '<p>Content</p>',
            'is_active' => true,
            'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/blog/posts/single-post')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Single Post']);
    }

    public function test_can_list_categories(): void
    {
        BlogCategory::create(['name' => 'Tech', 'slug' => 'tech']);
        BlogCategory::create(['name' => 'News', 'slug' => 'news']);

        $this->getJson('/api/v1/blog/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_show_a_category(): void
    {
        $category = BlogCategory::create([
            'name' => 'Tutorials',
            'slug' => 'tutorials',
        ]);

        $this->getJson("/api/v1/blog/categories/{$category->slug}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tutorials']);
    }

    public function test_draft_posts_are_not_shown_in_public_listing(): void
    {
        Post::create([
            'title' => 'Published',
            'slug' => 'published',
            'content' => '<p>Visible</p>',
            'is_active' => true,
            'published_at' => now()->subHour(),
        ]);

        Post::create([
            'title' => 'Draft',
            'slug' => 'draft',
            'content' => '<p>Hidden</p>',
            'is_active' => false,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/blog/posts');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Published'])
            ->assertJsonMissing(['title' => 'Draft']);
    }

    // -- Authenticated write endpoints ----------------------------------------

    public function test_authenticated_user_can_create_a_post(): void
    {
        $this->postJson('/api/v1/blog/posts', [
            'title' => 'API Created Post',
            'content' => '<p>Created via API</p>',
        ], $this->authHeaders())
            ->assertCreated();

        $this->assertDatabaseHas('posts', ['title' => 'API Created Post']);
    }

    public function test_authenticated_user_can_update_a_post(): void
    {
        $post = Post::create([
            'title' => 'Original',
            'slug' => 'original',
            'content' => '<p>Original content</p>',
        ]);

        $this->putJson("/api/v1/blog/posts/{$post->id}", [
            'title' => 'Updated via API',
        ], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated via API',
        ]);
    }

    public function test_authenticated_user_can_delete_a_post(): void
    {
        $post = Post::create([
            'title' => 'Delete via API',
            'slug' => 'delete-via-api',
            'content' => '<p>Content</p>',
        ]);

        $response = $this->deleteJson("/api/v1/blog/posts/{$post->id}", [], $this->authHeaders());

        $response->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_unauthenticated_user_cannot_create_post(): void
    {
        $this->postJson('/api/v1/blog/posts', [
            'title' => 'Should Fail',
            'content' => '<p>No auth</p>',
        ])->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_post(): void
    {
        $post = Post::create([
            'title' => 'Existing',
            'slug' => 'existing',
            'content' => '<p>Content</p>',
        ]);

        $this->putJson("/api/v1/blog/posts/{$post->id}", [
            'title' => 'Should Fail',
        ])->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_delete_post(): void
    {
        $post = Post::create([
            'title' => 'Existing',
            'slug' => 'existing-delete',
            'content' => '<p>Content</p>',
        ]);

        $this->deleteJson("/api/v1/blog/posts/{$post->id}")
            ->assertUnauthorized();
    }
}
