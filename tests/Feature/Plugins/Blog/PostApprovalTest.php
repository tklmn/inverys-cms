<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\PluginManager;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

/**
 * publish-post/schedule-post are the approval step: a role with edit-post
 * but without them must not make a post live, nor change a live one, on any
 * path (backend form, revision restore, API).
 */
class PostApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
        $this->createBlogTables();

        Plugin::create([
            'slug' => 'blog',
            'name' => 'Blog',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);
        Setting::create(['key' => 'blog.url_prefix', 'value' => 'blog']);

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

    /** May edit and save drafts, but needs approval to publish */
    private function author(): User
    {
        $role = Role::create(['name' => 'author']);
        $role->permissions()->sync(Permission::whereIn('name', [
            'access-dashboard', 'show-post', 'edit-post', 'draft-post', 'api-access',
        ])->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function publishedPost(): Post
    {
        return Post::create([
            'title' => 'Approved title',
            'slug' => 'approved',
            'content' => '<p>Approved</p>',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_restoring_a_published_revision_needs_publish_post(): void
    {
        $post = $this->publishedPost();

        // A reviewer takes the post offline; the snapshot before keeps is_active = true
        $this->actingAs($this->createAdmin());
        $post->createRevision();
        $post->update(['is_active' => false, 'published_at' => null]);
        $revision = $post->revisions()->first();
        $this->assertTrue((bool) $revision->data['is_active']);

        $this->actingAs($this->author())
            ->post(route('backend.revisions.restore', ['type' => 'post', 'id' => $post->id, 'revision' => $revision->id]))
            ->assertForbidden();

        $this->assertFalse($post->fresh()->isPublished());
    }

    public function test_restoring_a_draft_revision_works_without_publish_post(): void
    {
        $post = Post::create(['title' => 'First draft', 'slug' => 'draft', 'is_active' => false]);
        $this->actingAs($this->createAdmin());
        $post->createRevision();
        $post->update(['title' => 'Second draft']);
        $revision = $post->revisions()->first();

        $this->actingAs($this->author())
            ->post(route('backend.revisions.restore', ['type' => 'post', 'id' => $post->id, 'revision' => $revision->id]))
            ->assertRedirect();

        $this->assertSame('First draft', $post->fresh()->title);
    }

    public function test_api_cannot_change_a_live_post_without_publish_post(): void
    {
        $post = $this->publishedPost();
        $token = ApiTokenFactory::createWithPlainText($this->author())['plain_text'];

        $this->putJson("/api/v1/blog/posts/{$post->id}", ['title' => 'Changed live'], ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();

        $this->assertSame('Approved title', $post->fresh()->title);
    }

    public function test_api_can_still_edit_a_draft_without_publish_post(): void
    {
        $post = Post::create(['title' => 'Draft', 'slug' => 'draft', 'is_active' => false]);
        $token = ApiTokenFactory::createWithPlainText($this->author())['plain_text'];

        $this->putJson("/api/v1/blog/posts/{$post->id}", ['title' => 'Draft changed'], ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertSame('Draft changed', $post->fresh()->title);
    }

    public function test_backend_form_refuses_a_live_post_without_publish_post(): void
    {
        $post = $this->publishedPost();

        $this->actingAs($this->author())
            ->put(route('backend.blog.update', $post), [
                'title' => 'Changed live',
                'post_status' => 'published',
            ])
            ->assertForbidden();

        $this->assertSame('Approved title', $post->fresh()->title);
    }
}
