<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision list with a summary, comparison with the next version and a
 * preview of an old state.
 */
class RevisionUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
    }

    private function block(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'text', 'data' => ['content' => '<p>'.$text.'</p>'], 'settings' => []];
    }

    /** Three sessions: v0 → v1 (title) → v2 (block added) */
    private function history(): Page
    {
        $this->actingAs($this->createAdmin());
        $page = Page::factory()->create(['title' => 'v0', 'content_type' => 'blocks', 'blocks' => [$this->block('a', 'Old')]]);
        $page = $page->fresh();

        $this->travel(15)->minutes();
        $page->update(['title' => 'v1']);
        $this->travel(15)->minutes();
        $page->update(['blocks' => [$this->block('a', 'Old'), $this->block('b', 'New')]]);

        return $page;
    }

    public function test_each_revision_says_what_changed_afterwards(): void
    {
        $page = $this->history();

        $list = $this->getJson(route('backend.pages.revisions.index', $page))->assertOk()->json('revisions');

        // Newest first: the block came after it, the title before
        $this->assertStringContainsString(trans_choice('translation.pages.revisions.summary_added', 1, ['count' => 1]), $list[0]['summary']);
        $this->assertSame(__('translation.pages.field_title'), $list[1]['summary']);
        $this->assertTrue($list[0]['previewable']);
        $this->assertNull($list[0]['locale']);
    }

    public function test_compare_against_the_next_version(): void
    {
        $page = $this->history();
        $oldest = $page->revisions()->get()->last();

        $next = $this->getJson(route('backend.pages.revisions.show', [$page, $oldest]).'?against=next')->assertOk()->json();
        $this->assertSame('next', $next['against']);
        $this->assertSame(['title'], array_keys($next['diff']));

        $current = $this->getJson(route('backend.pages.revisions.show', [$page, $oldest]))->assertOk()->json();
        $this->assertSame('current', $current['against']);
        $this->assertEqualsCanonicalizing(['title', 'blocks'], array_keys($current['diff']));
    }

    public function test_preview_shows_the_old_state_without_changing_anything(): void
    {
        $page = $this->history();
        $oldest = $page->revisions()->get()->last();

        $html = $this->get(route('backend.pages.revisions.preview', [$page, $oldest]))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->getContent();

        $this->assertStringContainsString('Old', $html);
        $this->assertStringNotContainsString('<p>New</p>', $html);
        $this->assertStringContainsString(__('translation.pages.revisions.preview_back'), $html);
        $this->assertSame('v1', $page->fresh()->title);
        $this->assertCount(2, $page->fresh()->blocks);
    }

    public function test_editor_has_the_confirm_dialog(): void
    {
        $page = $this->history();

        $this->get(route('backend.pages.edit', $page))
            ->assertOk()
            ->assertSee('global-confirm-modal', false)
            ->assertSee(__('translation.pages.revisions.restore_this'));
    }
}
