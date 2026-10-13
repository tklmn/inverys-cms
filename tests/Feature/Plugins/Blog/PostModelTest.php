<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Plugin;
use App\Models\Setting;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->enableBlogPlugin();
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

    public function test_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => '<p>Hello World</p>',
            'author_id' => $user->id,
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);
    }

    public function test_post_defaults_to_inactive(): void
    {
        $post = Post::create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'content' => '<p>Draft content</p>',
        ]);

        // Refresh from DB to get the column default
        $post->refresh();

        $this->assertFalse($post->is_active);
    }

    public function test_published_post_has_is_active_true(): void
    {
        $post = Post::create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'content' => '<p>Published content</p>',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $this->assertTrue($post->is_active);
        $this->assertNotNull($post->published_at);
    }

    public function test_slug_is_fillable(): void
    {
        $post = Post::create([
            'title' => 'Slug Test',
            'slug' => 'custom-slug-value',
            'content' => '<p>Content</p>',
        ]);

        $this->assertEquals('custom-slug-value', $post->slug);
    }

    public function test_author_relationship_works(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Author Test',
            'slug' => 'author-test',
            'content' => '<p>Content</p>',
            'author_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertEquals($user->id, $post->author->id);
    }

    public function test_categories_relationship_works(): void
    {
        $post = Post::create([
            'title' => 'Category Test',
            'slug' => 'category-test',
            'content' => '<p>Content</p>',
        ]);

        $category = BlogCategory::create([
            'name' => 'Tech',
            'slug' => 'tech',
        ]);

        $post->categories()->attach($category->id);

        $this->assertCount(1, $post->fresh()->categories);
        $this->assertEquals('Tech', $post->fresh()->categories->first()->name);
    }

    public function test_soft_delete_works(): void
    {
        $post = Post::create([
            'title' => 'Delete Test',
            'slug' => 'delete-test',
            'content' => '<p>Content</p>',
        ]);

        $post->delete();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertNull(Post::find($post->id));
        $this->assertNotNull(Post::withTrashed()->find($post->id));
    }

    public function test_published_scope_returns_only_published_posts(): void
    {
        Post::create([
            'title' => 'Published',
            'slug' => 'published',
            'content' => '<p>Published</p>',
            'is_active' => true,
            'published_at' => now()->subHour(),
        ]);

        Post::create([
            'title' => 'Draft',
            'slug' => 'draft',
            'content' => '<p>Draft</p>',
            'is_active' => false,
        ]);

        Post::create([
            'title' => 'Scheduled',
            'slug' => 'scheduled',
            'content' => '<p>Scheduled</p>',
            'is_active' => true,
            'published_at' => now()->addWeek(),
        ]);

        Post::create([
            'title' => 'No Date',
            'slug' => 'no-date',
            'content' => '<p>No date</p>',
            'is_active' => true,
            'published_at' => null,
        ]);

        $published = Post::published()->get();

        $this->assertCount(1, $published);
        $this->assertEquals('Published', $published->first()->title);
    }
}
