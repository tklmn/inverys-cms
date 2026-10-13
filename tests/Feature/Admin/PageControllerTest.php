<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_page_index(): void
    {
        $admin = $this->createAdmin();
        Page::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('backend.pages.index'))
            ->assertOk();
    }

    public function test_editor_can_view_page_index(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.pages.index'))
            ->assertOk();
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $this->get(route('backend.pages.index'))
            ->assertRedirect();
    }

    public function test_admin_can_create_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.pages.create'))
            ->assertOk();
    }

    public function test_blocks_survive_a_validation_error(): void
    {
        // The hidden field comes back as JSON text; double-encoded, the
        // builder then showed "undefined" blocks instead of the input
        $admin = $this->createAdmin();
        $page = Page::factory()->create();
        $blocks = json_encode([['id' => 'b1', 'type' => 'heading', 'data' => ['text' => 'Hello world']]]);

        $html = $this->actingAs($admin)
            ->withSession(['_old_input' => ['blocks' => $blocks]])
            ->get(route('backend.pages.edit', $page))
            ->assertOk()
            ->getContent();

        preg_match("/data-page-builder='([^']*)'/", $html, $match);
        $config = json_decode(html_entity_decode($match[1] ?? '{}'), true);

        $this->assertSame('editor', $config['mode'] ?? null);
        $this->assertSame('heading', $config['blocks'][0]['type'] ?? null);
        $this->assertSame('Hello world', $config['blocks'][0]['data']['text'] ?? null);
    }

    public function test_admin_can_store_page(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.pages.store'), [
                'title' => 'Test Page',
                'slug' => 'test-page',
                'content' => '<p>Test content</p>',
                'content_type' => 'html',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', ['title' => 'Test Page', 'slug' => 'test-page']);
    }

    public function test_store_page_requires_title(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.pages.store'), [
                'content' => '<p>Content</p>',
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_admin_can_edit_page(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.pages.edit', $page))
            ->assertOk();
    }

    public function test_admin_can_update_page(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create();

        $this->actingAs($admin)
            ->put(route('backend.pages.update', $page), [
                'title' => 'Updated Title',
                'slug' => $page->slug,
                'content' => '<p>Updated</p>',
                'content_type' => 'html',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Updated Title']);
    }

    public function test_admin_can_delete_page(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create();

        $this->actingAs($admin)
            ->delete(route('backend.pages.destroy', $page))
            ->assertRedirect();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_admin_can_view_tree(): void
    {
        $admin = $this->createAdmin();
        Page::factory()->root()->create();
        Page::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('backend.pages.tree'))
            ->assertOk();
    }

    public function test_new_pages_start_as_draft_with_the_chosen_starter(): void
    {
        $this->actingAs($this->createAdmin());

        $response = $this->post(route('backend.pages.store'), ['title' => 'Landing', 'starter' => 'landing']);

        $page = Page::where('title', 'Landing')->firstOrFail();
        $response->assertRedirect(route('backend.pages.edit', $page));
        $this->assertFalse($page->is_active);
        $this->assertSame('blocks', $page->content_type);
        $this->assertSame(['hero', 'section'], array_column($page->blocks, 'type'));
        $this->assertSame('Landing', $page->blocks[0]['data']['title']);
        // Search text from the blocks
        $this->assertStringContainsString('Landing', $page->content);
    }

    public function test_blank_starter_and_unknown_starter(): void
    {
        $this->actingAs($this->createAdmin());

        $this->post(route('backend.pages.store'), ['title' => 'Empty'])->assertRedirect();
        $this->assertSame([], Page::where('title', 'Empty')->firstOrFail()->blocks);

        $this->post(route('backend.pages.store'), ['title' => 'Broken', 'starter' => 'nope'])->assertSessionHasErrors('starter');
    }

    public function test_update_always_stores_blocks(): void
    {
        $page = Page::factory()->create();
        $blocks = [['id' => 'h1', 'type' => 'heading', 'data' => ['text' => 'New', 'level' => 'h2'], 'settings' => []]];

        $this->actingAs($this->createAdmin())->put(route('backend.pages.update', $page), [
            'title' => $page->title,
            'slug' => $page->slug,
            'blocks' => json_encode($blocks),
        ])->assertRedirect(route('backend.pages.edit', $page));

        $page->refresh();
        $this->assertSame('blocks', $page->content_type);
        $this->assertSame('New', $page->blocks[0]['data']['text']);
        $this->assertSame('New', $page->content);
    }

    public function test_canvas_renders_the_page_in_the_theme_even_as_draft(): void
    {
        $page = Page::factory()->create(['title' => 'Draft page', 'is_active' => false, 'content_type' => 'blocks',
            'blocks' => [['id' => 'h1', 'type' => 'heading', 'data' => ['text' => 'Only in the preview', 'level' => 'h2'], 'settings' => []]]]);

        $response = $this->actingAs($this->createAdmin())->get(route('backend.pages.canvas', $page));

        $response->assertOk()
            ->assertSee('Only in the preview')
            ->assertSee('data-pb-id="h1" data-pb-type="heading"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Publicly the draft stays invisible
        $this->app['auth']->forgetGuards();
        $this->get('/'.$page->slug)->assertNotFound();
    }

    public function test_canvas_needs_edit_permission(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->createUser())->get(route('backend.pages.canvas', $page))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('backend.pages.canvas', $page))->assertRedirect();
    }

    public function test_save_blocks_updates_the_search_text(): void
    {
        $page = Page::factory()->create();
        $blocks = [['id' => 't1', 'type' => 'text', 'data' => ['content' => '<p>Searchable <strong>text</strong></p>'], 'settings' => []]];

        $this->actingAs($this->createAdmin())
            ->postJson(route('backend.pages.save-blocks', $page), ['blocks' => json_encode($blocks)])
            ->assertOk();

        $this->assertSame('Searchable text', $page->fresh()->content);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.pages.index'))
            ->assertForbidden();
    }
}
