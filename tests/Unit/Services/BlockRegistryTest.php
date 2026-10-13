<?php

namespace Tests\Unit\Services;

use App\Models\BlockType;
use App\Services\BlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    use RefreshDatabase;

    private BlockRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new BlockRegistry;
    }

    public function test_boot_seeds_core_block_types_into_database(): void
    {
        $this->assertSame(0, BlockType::count());

        $this->registry->boot();

        $this->assertGreaterThanOrEqual(18, BlockType::count());
    }

    public function test_boot_registers_all_core_block_types_in_memory(): void
    {
        $this->registry->boot();

        $expectedSlugs = [
            'container', 'columns', 'section', 'spacer', 'divider',
            'heading', 'text', 'image', 'video', 'quote', 'code', 'list',
            'accordion', 'tabs', 'button', 'link-group', 'alert', 'card',
        ];

        $all = $this->registry->all();

        foreach ($expectedSlugs as $slug) {
            $this->assertArrayHasKey($slug, $all, "Core block type '{$slug}' is missing from registry.");
        }
    }

    public function test_get_returns_block_type_by_slug(): void
    {
        $this->registry->boot();

        $heading = $this->registry->get('heading');

        $this->assertNotNull($heading);
        $this->assertSame('Heading', $heading['name']);
        $this->assertSame('content', $heading['category']);
    }

    public function test_get_returns_null_for_unknown_slug(): void
    {
        $this->registry->boot();

        $this->assertNull($this->registry->get('nonexistent-block'));
    }

    public function test_all_blocks_have_required_keys(): void
    {
        $this->registry->boot();

        foreach ($this->registry->all() as $slug => $block) {
            $this->assertArrayHasKey('name', $block, "Block '{$slug}' is missing 'name'.");
            $this->assertArrayHasKey('category', $block, "Block '{$slug}' is missing 'category'.");
            $this->assertArrayHasKey('icon', $block, "Block '{$slug}' is missing 'icon'.");
        }
    }

    public function test_core_blocks_are_stored_with_is_core_flag(): void
    {
        $this->registry->boot();

        $coreCount = BlockType::where('is_core', true)->count();
        $this->assertGreaterThanOrEqual(18, $coreCount);
    }

    public function test_can_register_additional_block_types(): void
    {
        $this->registry->boot();

        $this->registry->register('custom-block', [
            'name' => 'Custom Block',
            'category' => 'content',
            'icon' => 'bi-star',
            'schema' => ['data' => ['content' => '']],
        ]);

        $this->assertTrue($this->registry->has('custom-block'));
        $this->assertSame('Custom Block', $this->registry->get('custom-block')['name']);
    }

    public function test_has_returns_false_for_unregistered_slug(): void
    {
        $this->registry->boot();

        $this->assertFalse($this->registry->has('does-not-exist'));
    }

    public function test_by_category_groups_blocks_correctly(): void
    {
        $this->registry->boot();

        $grouped = $this->registry->byCategory();

        $this->assertArrayHasKey('layout', $grouped);
        $this->assertArrayHasKey('content', $grouped);
        $this->assertArrayHasKey('interactive', $grouped);

        $this->assertArrayHasKey('spacer', $grouped['layout']);
        $this->assertArrayHasKey('heading', $grouped['content']);
        $this->assertArrayHasKey('button', $grouped['interactive']);
    }

    public function test_boot_is_idempotent(): void
    {
        $this->registry->boot();
        $countFirst = BlockType::count();

        // Clear cache and boot again
        BlockRegistry::clearCache();
        $freshRegistry = new BlockRegistry;
        $freshRegistry->boot();
        $countSecond = BlockType::count();

        $this->assertSame($countFirst, $countSecond);
    }
}
