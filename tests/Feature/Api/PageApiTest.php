<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);
        $this->token = $result['plain_text'];
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_list_pages(): void
    {
        Page::factory()->count(5)->create();

        $this->getJson('/api/v1/pages', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_list_pages_with_search(): void
    {
        Page::factory()->create(['title' => 'About Us']);
        Page::factory()->create(['title' => 'Contact']);

        $this->getJson('/api/v1/pages?search=About', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_pages_with_status_filter(): void
    {
        Page::factory()->create(['is_active' => true]);
        Page::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/pages?status=active', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_per_page_is_capped_at_100(): void
    {
        Page::factory()->count(5)->create();

        // Request 9999 per page — should be capped
        $response = $this->getJson('/api/v1/pages?per_page=9999', $this->authHeaders());
        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    public function test_show_page(): void
    {
        $page = Page::factory()->create();

        $this->getJson("/api/v1/pages/{$page->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['title' => $page->title]);
    }

    public function test_create_page(): void
    {
        $this->postJson('/api/v1/pages', [
            'title' => 'API Page',
            'content' => '<p>Created via API</p>',
        ], $this->authHeaders())
            ->assertCreated();

        // HTML without blocks becomes a text block — there are only block pages now
        $page = Page::where('title', 'API Page')->firstOrFail();
        $this->assertSame('blocks', $page->content_type);
        $this->assertSame('text', $page->blocks[0]['type']);
        $this->assertSame('<p>Created via API</p>', $page->blocks[0]['data']['content']);
        $this->assertSame('Created via API', $page->content);
    }

    public function test_update_with_html_replaces_the_blocks(): void
    {
        $page = Page::factory()->create(['content_type' => 'blocks', 'blocks' => [['id' => 'h1', 'type' => 'heading', 'data' => ['text' => 'Old'], 'settings' => []]]]);

        $this->putJson("/api/v1/pages/{$page->id}", ['content' => '<p>New body</p>'], $this->authHeaders())->assertOk();

        $page->refresh();
        $this->assertCount(1, $page->blocks);
        $this->assertSame('<p>New body</p>', $page->blocks[0]['data']['content']);
        $this->assertSame('New body', $page->content);
    }

    public function test_create_page_with_blocks_defaults_to_blocks_mode(): void
    {
        $this->postJson('/api/v1/pages', [
            'title' => 'Block Page',
            'blocks' => [],
        ], $this->authHeaders())
            ->assertCreated();

        $this->assertDatabaseHas('pages', ['title' => 'Block Page', 'content_type' => 'blocks']);
    }

    public function test_create_page_auto_generates_slug(): void
    {
        $this->postJson('/api/v1/pages', [
            'title' => 'My New Page',
        ], $this->authHeaders())
            ->assertCreated();

        $this->assertDatabaseHas('pages', ['slug' => 'my-new-page']);
    }

    public function test_update_page(): void
    {
        $page = Page::factory()->create();

        $this->putJson("/api/v1/pages/{$page->id}", [
            'title' => 'Updated Title',
        ], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Updated Title']);
    }

    public function test_delete_page(): void
    {
        $page = Page::factory()->create();

        $this->deleteJson("/api/v1/pages/{$page->id}", [], $this->authHeaders())
            ->assertOk();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_unauthenticated_request_fails(): void
    {
        $this->getJson('/api/v1/pages')
            ->assertUnauthorized();
    }
}
