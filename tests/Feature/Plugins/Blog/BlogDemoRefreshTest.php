<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Page;
use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PluginManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

/**
 * cms:demo-refresh also brings the demo blog posts up to date — with a
 * revision first, own posts untouched, deleted demo posts not recreated.
 */
class BlogDemoRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');

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
        });

        Plugin::create(['slug' => 'blog', 'name' => 'Blog', 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
        Setting::create(['key' => 'blog.url_prefix', 'value' => 'blog']);
        $plugins = app(PluginManager::class);
        $plugins->discover();
        $plugins->boot();

        // The command only runs when demo pages exist
        Page::create(['title' => 'About Us', 'slug' => 'about', 'content_type' => 'blocks', 'is_active' => true, 'blocks' => []]);
    }

    public function test_demo_posts_get_the_current_texts_and_a_revision(): void
    {
        $demo = Post::create(['title' => 'Mastering the Page Builder', 'slug' => 'mastering-the-page-builder',
            'content' => '<p>The page builder offers 18 block types. Right-click a block.</p>', 'excerpt' => 'Old excerpt']);
        $own = Post::create(['title' => 'My own post', 'slug' => 'my-own-post', 'content' => '<p>Mine</p>']);

        $this->artisan('cms:demo-refresh', ['--force' => true])
            ->expectsOutputToContain('1 plugin entries')
            ->assertSuccessful();

        $demo->refresh();
        $this->assertStringContainsString('23 block types', $demo->content);
        $this->assertStringNotContainsString('Right-click', $demo->content);
        $this->assertNotSame('Old excerpt', $demo->excerpt);

        // The old state stays restorable
        $revision = $demo->revisions()->first();
        $this->assertSame('Demo content refresh', $revision->note);
        $this->assertStringContainsString('18 block types', $revision->data['content']);

        $this->assertSame('<p>Mine</p>', $own->fresh()->content);
    }

    public function test_deleted_demo_posts_are_not_recreated(): void
    {
        $this->artisan('cms:demo-refresh', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Post::withTrashed()->count());
    }
}
