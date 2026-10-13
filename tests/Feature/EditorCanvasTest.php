<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\BlockRenderer;
use App\Support\EditorCanvas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editor preview markers: only there, never on the website.
 */
class EditorCanvasTest extends TestCase
{
    use RefreshDatabase;

    private function blocks(): array
    {
        return [
            ['id' => 'h1', 'type' => 'heading', 'data' => ['text' => 'Hello', 'level' => 'h2'], 'settings' => []],
            ['id' => 'c1', 'type' => 'columns', 'data' => [], 'settings' => ['columns' => '1'], 'children' => [
                ['id' => 'col1', 'type' => 'column', 'data' => [], 'settings' => [], 'children' => [
                    ['id' => 't1', 'type' => 'text', 'data' => ['content' => '<p>Text</p>'], 'settings' => []],
                ]],
            ]],
        ];
    }

    public function test_blocks_and_fields_are_marked_only_in_canvas_mode(): void
    {
        $plain = app(BlockRenderer::class)->render($this->blocks());
        $this->assertStringNotContainsString('data-pb-', $plain);

        EditorCanvas::enable();
        try {
            $html = app(BlockRenderer::class)->render($this->blocks());
        } finally {
            EditorCanvas::enable(false);
        }

        $this->assertStringContainsString('data-pb-id="h1" data-pb-type="heading"', $html);
        $this->assertStringContainsString('data-pb-field="text"', $html);
        $this->assertStringContainsString('data-pb-id="t1"', $html);
        $this->assertStringContainsString('data-pb-field="content" data-pb-rich', $html);
        // Columns have no entry of their own in the structure
        $this->assertStringNotContainsString('data-pb-id="col1"', $html);
    }

    public function test_button_texts_are_editable_in_the_canvas_with_their_list_path(): void
    {
        $blocks = [
            ['id' => 'hero', 'type' => 'hero', 'data' => ['title' => 'T', 'primary_text' => 'Go', 'secondary_text' => 'More'], 'settings' => []],
            // The empty first button is not shown — the second one's path stays items.1
            ['id' => 'btns', 'type' => 'buttons', 'data' => ['items' => [['text' => ''], ['text' => 'Two', 'url' => '#']]], 'settings' => []],
            ['id' => 'price', 'type' => 'pricing', 'data' => ['plans' => [['name' => 'Basic', 'price' => '9', 'button_text' => 'Book']]], 'settings' => []],
        ];

        EditorCanvas::enable();
        try {
            $html = app(BlockRenderer::class)->render($blocks);
        } finally {
            EditorCanvas::enable(false);
        }

        $this->assertStringContainsString('data-pb-field="primary_text">Go</span>', $html);
        $this->assertStringContainsString('data-pb-field="secondary_text">More</span>', $html);
        $this->assertStringContainsString('data-pb-field="items.1.text">Two</span>', $html);
        $this->assertStringNotContainsString('items.0.text', $html);
        $this->assertStringContainsString('data-pb-field="plans.0.name"', $html);
        $this->assertStringContainsString('data-pb-field="plans.0.button_text">Book</span>', $html);
    }

    public function test_annotate_skips_leading_comments_and_escapes(): void
    {
        EditorCanvas::enable();
        try {
            $html = EditorCanvas::annotate("  <!-- x -->\n<section class=\"a\">", 'a"b', 'hero');
        } finally {
            EditorCanvas::enable(false);
        }

        $this->assertSame("  <!-- x -->\n<section data-pb-id=\"a&quot;b\" data-pb-type=\"hero\" class=\"a\">", $html);
    }

    public function test_canvas_marks_blocks_but_the_website_does_not(): void
    {
        $this->markAsInstalled();
        $this->seedPermissions();
        $page = Page::factory()->create(['slug' => 'sample-page', 'is_active' => true, 'content_type' => 'blocks', 'blocks' => $this->blocks()]);

        $this->actingAs($this->createAdmin())
            ->get(route('backend.pages.canvas', $page))
            ->assertOk()
            ->assertSee('data-pb-id="h1"', false);

        $this->app['auth']->forgetGuards();
        $site = $this->get('/sample-page')->assertOk();
        $this->assertStringNotContainsString('data-pb-', $site->getContent());
        $this->assertStringNotContainsString('editor-canvas', $site->getContent());
    }
}
