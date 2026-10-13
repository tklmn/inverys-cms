<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Subpage list below the content (default theme, setting show_subpages):
 * by default only on plain pages (text, images …) — designed pages with a
 * hero, sections or columns link their subpages themselves.
 */
class SubpagesListTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithChild(array $blocks): Page
    {
        $parent = Page::create(['title' => 'Parent', 'slug' => 'parent', 'is_active' => true, 'content_type' => 'blocks', 'blocks' => $blocks]);
        Page::create(['title' => 'Child Page', 'slug' => 'child-page', 'is_active' => true, 'parent_id' => $parent->id, 'level' => 1, 'content_type' => 'html', 'content' => '<p>Child</p>']);

        return $parent;
    }

    public function test_text_pages_list_their_subpages(): void
    {
        $this->parentWithChild([['id' => 'b1', 'type' => 'text', 'data' => ['content' => '<p>Blocks</p>'], 'settings' => []]]);

        $this->get('/parent')->assertOk()->assertSee('Blocks')->assertSee(__('translation.pages.subpages'));
    }

    public function test_former_html_pages_still_list_their_subpages(): void
    {
        // HTML becomes a text block on save — the list stays
        Page::create(['title' => 'Parent', 'slug' => 'parent', 'is_active' => true, 'content_type' => 'html', 'content' => '<p>Text</p>']);
        Page::create(['title' => 'Child Page', 'slug' => 'child-page', 'is_active' => true, 'parent_id' => Page::where('slug', 'parent')->value('id'), 'level' => 1]);

        $this->get('/parent')->assertOk()->assertSee('Text')->assertSee(__('translation.pages.subpages'));
    }

    public function test_designed_pages_do_not_by_default(): void
    {
        $this->parentWithChild([['id' => 's1', 'type' => 'section', 'data' => [], 'settings' => [], 'children' => [
            ['id' => 'b1', 'type' => 'text', 'data' => ['content' => '<p>Blocks</p>'], 'settings' => []],
        ]]]);

        $this->get('/parent')->assertOk()->assertSee('Blocks')->assertDontSee(__('translation.pages.subpages'));
    }
}
