<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\BlockDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revisions: one per editing session (the page builder saves on every
 * change), a backup before restoring and a readable comparison of the
 * blocks.
 */
class RevisionSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
    }

    private function blocks(string $heading, string $text, array $extra = []): array
    {
        return array_merge([
            ['id' => 'b1', 'type' => 'heading', 'data' => ['text' => $heading, 'level' => 'h2'], 'settings' => []],
            ['id' => 'b2', 'type' => 'text', 'data' => ['content' => '<p>'.$text.'</p>'], 'settings' => []],
        ], $extra);
    }

    public function test_autosaves_within_a_session_make_one_revision(): void
    {
        $this->actingAs($this->createAdmin());
        $page = Page::factory()->create(['title' => 'Start', 'content_type' => 'blocks', 'blocks' => $this->blocks('A', 'one')]);

        $base = now();
        foreach (['two', 'three', 'four', 'five'] as $i => $word) {
            $this->travelTo($base->copy()->addSeconds(($i + 1) * 8));
            $page->update(['blocks' => $this->blocks('A', $word)]);
        }

        // Only the state before the session is saved
        $this->assertCount(1, $page->revisions()->get());
        $this->assertSame('<p>one</p>', $page->revisions()->first()->data['blocks'][1]['data']['content']);

        // After the session (more than 10 minutes later) a new one is created
        $this->travelTo($base->copy()->addMinutes(30));
        $page->update(['blocks' => $this->blocks('A', 'six')]);
        $this->travelBack();

        $this->assertCount(2, $page->revisions()->get());
    }

    public function test_another_user_starts_a_new_revision(): void
    {
        $this->actingAs($this->createAdmin());
        $page = Page::factory()->create(['title' => 'v0']);
        $page->update(['title' => 'v1']);

        $this->travel(5)->seconds();
        $this->actingAs($this->createEditor());
        $page->update(['title' => 'v2']);
        $this->travelBack();

        $this->assertSame(['v1', 'v0'], $page->revisions()->get()->pluck('data.title')->all());
    }

    public function test_restoring_saves_the_current_state_first_even_within_a_session(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        $page = Page::factory()->create(['title' => 'v0']);
        $page->update(['title' => 'v1']);
        $page->update(['title' => 'v2']);   // same session: no revision of its own

        $old = $page->revisions()->first();   // state "v0"
        $this->post(route('backend.pages.revisions.restore', [$page, $old]))->assertRedirect();

        $page->refresh();
        $this->assertSame('v0', $page->title);
        // "v2" is not lost: saved before restoring, with a note
        $latest = $page->revisions()->first();
        $this->assertSame('v2', $latest->data['title']);
        $this->assertSame(__('translation.pages.revisions.before_restore'), $latest->note);
    }

    public function test_block_diff_names_added_removed_changed_and_reordered_blocks(): void
    {
        $before = $this->blocks('Welcome', 'Old text', [['id' => 'b3', 'type' => 'button', 'data' => ['label' => 'Contact', 'url' => '/contact'], 'settings' => []]]);
        $after = [
            ['id' => 'b2', 'type' => 'text', 'data' => ['content' => '<p>New text</p>'], 'settings' => []],
            ['id' => 'b1', 'type' => 'heading', 'data' => ['text' => 'Welcome', 'level' => 'h2'], 'settings' => []],
            ['id' => 'b4', 'type' => 'section', 'data' => ['title' => 'New'], 'settings' => [], 'children' => [
                ['id' => 'b5', 'type' => 'text', 'data' => ['content' => '<p>Inside</p>'], 'settings' => []],
            ]],
        ];

        $diff = BlockDiff::compare($before, $after);

        $this->assertSame(['New', 'Inside'], array_column($diff['added'], 'text'));
        $this->assertSame('Contact', $diff['removed'][0]['text']);
        $this->assertSame(['Old text', 'New text'], [$diff['changed'][0]['before'], $diff['changed'][0]['after']]);
        $this->assertTrue($diff['reordered']);
        $this->assertNull(BlockDiff::compare($before, json_encode($before)));
    }

    public function test_compare_shows_blocks_instead_of_too_large(): void
    {
        $this->actingAs($this->createAdmin());
        $page = Page::factory()->create(['title' => 'Start', 'content_type' => 'blocks', 'blocks' => $this->blocks('A', str_repeat('long ', 200))]);
        $page->update(['blocks' => $this->blocks('A', 'short')]);
        $revision = $page->revisions()->first();

        $diff = $this->getJson(route('backend.pages.revisions.show', [$page, $revision]))->assertOk()->json('diff');

        $this->assertSame('blocks', $diff['blocks']['kind']);
        $this->assertSame('short', $diff['blocks']['changed'][0]['after']);
        $this->assertArrayNotHasKey('content', $diff);
    }
}
