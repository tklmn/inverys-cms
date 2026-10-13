<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PluginManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

class BlogCategoryModelTest extends TestCase
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

    public function test_can_create_a_category(): void
    {
        $category = BlogCategory::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Tech articles',
        ]);

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    public function test_posts_relationship_works(): void
    {
        $category = BlogCategory::create([
            'name' => 'News',
            'slug' => 'news',
        ]);

        $post = Post::create([
            'title' => 'Breaking News',
            'slug' => 'breaking-news',
            'content' => '<p>Something happened</p>',
        ]);

        $category->posts()->attach($post->id);

        $this->assertCount(1, $category->fresh()->posts);
        $this->assertEquals('Breaking News', $category->fresh()->posts->first()->title);
    }

    public function test_category_can_have_multiple_posts(): void
    {
        $category = BlogCategory::create([
            'name' => 'Tutorials',
            'slug' => 'tutorials',
        ]);

        $post1 = Post::create([
            'title' => 'Tutorial One',
            'slug' => 'tutorial-one',
            'content' => '<p>First tutorial</p>',
        ]);

        $post2 = Post::create([
            'title' => 'Tutorial Two',
            'slug' => 'tutorial-two',
            'content' => '<p>Second tutorial</p>',
        ]);

        $category->posts()->attach([$post1->id, $post2->id]);

        $this->assertCount(2, $category->fresh()->posts);
    }

    public function test_category_deletion_removes_record(): void
    {
        $category = BlogCategory::create([
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $categoryId = $category->id;
        $category->delete();

        $this->assertDatabaseMissing('blog_categories', ['id' => $categoryId]);
    }
}
