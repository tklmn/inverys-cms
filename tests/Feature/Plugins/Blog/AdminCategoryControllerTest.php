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
use Plugins\Blog\Models\BlogCategory;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
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

    public function test_admin_can_view_categories_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.blog.categories.index'))
            ->assertOk();
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.blog.categories.create'))
            ->assertOk();
    }

    public function test_admin_can_store_a_new_category(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.blog.categories.store'), [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Tech articles',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    public function test_admin_can_update_a_category(): void
    {
        $admin = $this->createAdmin();

        $category = BlogCategory::create([
            'name' => 'Original Name',
            'slug' => 'original-name',
        ]);

        $this->actingAs($admin)
            ->put(route('backend.blog.categories.update', $category), [
                'name' => 'Updated Name',
                'slug' => 'original-name',
                'description' => 'Updated description',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_a_category(): void
    {
        $admin = $this->createAdmin();

        $category = BlogCategory::create([
            'name' => 'Delete Me',
            'slug' => 'delete-me',
        ]);

        $this->actingAs($admin)
            ->delete(route('backend.blog.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
    }

    public function test_store_requires_name_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.blog.categories.store'), [
                'slug' => 'no-name',
                'description' => 'Missing name field',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_editor_can_delete_a_category(): void
    {
        $editor = $this->createEditor();

        $category = BlogCategory::create([
            'name' => 'Editor Delete',
            'slug' => 'editor-delete',
        ]);

        $this->actingAs($editor)
            ->delete(route('backend.blog.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
    }

    public function test_user_without_delete_permission_gets_403(): void
    {
        $role = Role::create(['name' => 'category-reviewer', 'is_system' => false]);
        $role->permissions()->attach(Permission::whereIn('name', ['access-dashboard', 'show-blog-category', 'edit-blog-category'])->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $category = BlogCategory::create([
            'name' => 'Protected',
            'slug' => 'protected',
        ]);

        $this->actingAs($user)
            ->delete(route('backend.blog.categories.destroy', $category))
            ->assertForbidden();

        $this->assertDatabaseHas('blog_categories', ['id' => $category->id]);
    }
}
