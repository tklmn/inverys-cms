<?php

namespace Tests\Unit\Services;

use App\Services\BlockRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BlockRendererTest extends TestCase
{
    use RefreshDatabase;

    private BlockRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new BlockRenderer;
    }

    public function test_empty_blocks_return_empty_string(): void
    {
        $this->assertEquals('', $this->renderer->render(null));
        $this->assertEquals('', $this->renderer->render([]));
    }

    public function test_unknown_block_type_returns_empty(): void
    {
        $blocks = [
            ['type' => 'nonexistent_type_xyz', 'data' => [], 'settings' => []],
        ];
        $this->assertEquals('', $this->renderer->render($blocks));
    }

    public function test_renders_text_block(): void
    {
        // Only test if the view exists in the application
        if (! View::exists('frontend.blocks.text')) {
            $this->markTestSkipped('frontend.blocks.text view not available');
        }

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'text',
                'data' => ['content' => '<p>Hello World</p>'],
                'settings' => [],
            ],
        ];

        $html = $this->renderer->render($blocks);
        $this->assertStringContainsString('Hello World', $html);
    }
}
