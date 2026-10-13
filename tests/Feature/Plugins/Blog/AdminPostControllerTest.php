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
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

class AdminPostControllerTest extends TestCase
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

        // Permissions as in production: the plugin's syncPermissions()
        $pm->getLoadedProviders()['blog']->syncPermissions();
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

    public function test_admin_can_view_posts_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.blog.index'))
            ->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.blog.create'))
            ->assertOk();
    }

    public function test_admin_can_store_a_new_post(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.blog.store'), [
                'title' => 'My New Blog Post',
                'slug' => 'my-new-blog-post',
                'content' => '<p>This is the content.</p>',
                'post_status' => 'draft',
                'published_at' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'title' => 'My New Blog Post',
            'slug' => 'my-new-blog-post',
        ]);
    }

    public function test_admin_can_edit_a_post(): void
    {
        $admin = $this->createAdmin();

        $post = Post::create([
            'title' => 'Edit Me',
            'slug' => 'edit-me',
            'content' => '<p>Original</p>',
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('backend.blog.edit', $post))
            ->assertOk();
    }

    public function test_admin_can_update_a_post(): void
    {
        $admin = $this->createAdmin();

        $post = Post::create([
            'title' => 'Original Title',
            'slug' => 'original-title',
            'content' => '<p>Original content</p>',
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('backend.blog.update', $post), [
                'title' => 'Updated Title',
                'slug' => 'original-title',
                'content' => '<p>Updated content</p>',
                'post_status' => 'draft',
                'published_at' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_admin_can_delete_a_post(): void
    {
        $admin = $this->createAdmin();

        $post = Post::create([
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'content' => '<p>Content</p>',
            'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('backend.blog.destroy', $post))
            ->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_editor_can_delete_a_post(): void
    {
        $editor = $this->createEditor();

        $post = Post::create([
            'title' => 'Editor Delete',
            'slug' => 'editor-delete',
            'content' => '<p>Content</p>',
        ]);

        $this->actingAs($editor)
            ->delete(route('backend.blog.destroy', $post))
            ->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_user_without_delete_permission_gets_403(): void
    {
        $user = $this->createUserWithPermissions(['access-dashboard', 'show-post', 'edit-post']);

        $post = Post::create([
            'title' => 'Protected Post',
            'slug' => 'protected-post',
            'content' => '<p>Content</p>',
        ]);

        $this->actingAs($user)
            ->delete(route('backend.blog.destroy', $post))
            ->assertForbidden();

        $this->assertNotSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_editor_cannot_open_blog_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.blog.settings'))->assertForbidden();
        $this->actingAs($editor)->put(route('backend.blog.settings.update'), [])->assertForbidden();
    }

    /** @param  list<string>  $permissions */
    private function createUserWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'post-reviewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('name', $permissions)->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_requires_title_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.blog.store'), [
                'content' => '<p>Some content without title</p>',
                'post_status' => 'draft',
            ])
            ->assertSessionHasErrors('title');
    }
}
