<?php

namespace Tests\Feature\Plugins\Blog;

use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PluginManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\Post;
use Tests\TestCase;

/**
 * Blog post revisions via the generic revision routes
 * (App\Support\Revisionables): list, compare, preview, restore.
 */
class PostRevisionTest extends TestCase
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

    /** Two sessions: "First title" → "Second title" → "Third title" */
    private function history(): Post
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $post = Post::create([
            'title' => 'First title',
            'slug' => 'sample-post',
            'content' => '<p>First text</p>',
            'author_id' => $admin->id,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ])->fresh();

        $this->travel(15)->minutes();
        $post->update(['title' => 'Second title', 'content' => '<p>Second text</p>']);
        $this->travel(15)->minutes();
        $post->update(['title' => 'Third title']);

        return $post->fresh();
    }

    private function routeFor(string $name, Post $post, array $extra = []): string
    {
        return route('backend.revisions.'.$name, ['type' => 'post', 'id' => $post->id] + $extra);
    }

    public function test_updating_a_post_keeps_earlier_states(): void
    {
        $post = $this->history();

        $this->assertSame(2, $post->revisions()->count());
        $this->assertSame('Second title', $post->revisions()->first()->data['title']);
    }

    public function test_list_uses_the_blog_field_labels(): void
    {
        $post = $this->history();

        $list = $this->getJson($this->routeFor('index', $post))->assertOk()->json('revisions');

        $this->assertCount(2, $list);
        $this->assertSame(__('blog::blog.post_title'), $list[0]['summary']);
        $this->assertTrue($list[0]['previewable']);
    }

    public function test_compare_with_current_state(): void
    {
        $post = $this->history();
        $oldest = $post->revisions()->get()->last();

        $diff = $this->getJson($this->routeFor('show', $post, ['revision' => $oldest->id]))->assertOk()->json('diff');

        $this->assertEqualsCanonicalizing(['title', 'content'], array_keys($diff));
        $this->assertSame(__('blog::blog.post_title'), $diff['title']['label']);
        $this->assertSame('First title', $diff['title']['revision']);
        $this->assertSame('Third title', $diff['title']['current']);
    }

    public function test_dates_compare_in_the_users_time_zone(): void
    {
        $post = $this->history();
        auth()->user()->setSetting('timezone', 'Europe/Berlin');
        $oldest = $post->revisions()->get()->last();

        // Stored as in the database, not as UTC text with "Z"
        $this->assertSame($post->getRawOriginal('published_at'), $oldest->data['published_at']);

        $diff = $this->getJson($this->routeFor('show', $post, ['revision' => $oldest->id]))->assertOk()->json('diff');
        $this->assertArrayNotHasKey('published_at', $diff);

        // A previously stored UTC text does not count as a change either
        $oldest->update(['data' => ['published_at' => $post->published_at->toJSON()] + $oldest->data]);
        $diff = $this->getJson($this->routeFor('show', $post, ['revision' => $oldest->id]))->assertOk()->json('diff');
        $this->assertArrayNotHasKey('published_at', $diff);
    }

    public function test_preview_renders_the_old_post_without_saving(): void
    {
        $post = $this->history();
        $oldest = $post->revisions()->get()->last();

        $html = $this->get($this->routeFor('preview', $post, ['revision' => $oldest->id]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->getContent();

        $this->assertStringContainsString('First text', $html);
        $this->assertStringContainsString(route('backend.blog.edit', $post), $html);
        $this->assertStringNotContainsString(__('blog::blog.preview_draft'), $html);
        $this->assertSame('Third title', $post->fresh()->title);
    }

    public function test_restore_saves_the_current_state_first_and_returns_to_the_editor(): void
    {
        $post = $this->history();
        $oldest = $post->revisions()->get()->last();

        $this->post($this->routeFor('restore', $post, ['revision' => $oldest->id]))
            ->assertRedirect(route('backend.blog.edit', ['post' => $post, 'tab' => 'revisions']));

        $publishedAt = $post->getRawOriginal('published_at');
        $post->refresh();
        $this->assertSame('First title', $post->title);
        $this->assertSame($publishedAt, $post->getRawOriginal('published_at'));
        $this->assertSame('Third title', $post->revisions()->first()->data['title']);
    }

    public function test_delete_a_revision(): void
    {
        $post = $this->history();
        $oldest = $post->revisions()->get()->last();

        $this->deleteJson($this->routeFor('destroy', $post, ['revision' => $oldest->id]))->assertOk();

        $this->assertSame(1, $post->revisions()->count());
    }

    public function test_revisions_need_the_edit_post_permission(): void
    {
        $post = $this->history();
        $revision = $post->revisions()->first();

        $this->actingAs($this->createUser());
        $this->getJson($this->routeFor('index', $post))->assertForbidden();
        $this->post($this->routeFor('restore', $post, ['revision' => $revision->id]))->assertForbidden();
    }

    public function test_a_revision_of_another_post_is_not_found(): void
    {
        $post = $this->history();
        $other = Post::create(['title' => 'Other', 'slug' => 'other', 'content' => '<p>x</p>']);

        $this->getJson($this->routeFor('show', $other, ['revision' => $post->revisions()->first()->id]))->assertNotFound();
    }

    public function test_unknown_type_is_not_found(): void
    {
        $this->actingAs($this->createAdmin());

        $this->getJson(route('backend.revisions.index', ['type' => 'nope', 'id' => 1]))->assertNotFound();
    }

    public function test_edit_page_shows_the_revisions_card(): void
    {
        $post = $this->history();

        $this->get(route('backend.blog.edit', $post))
            ->assertOk()
            ->assertSee(__('translation.pages.revisions.title'))
            ->assertSee('revisions\/post\/'.$post->id, false);
    }
}
