<?php

namespace Tests\Unit\Models;

use App\Models\BlockType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_block_type(): void
    {
        $blockType = BlockType::factory()->create([
            'slug' => 'hero-banner',
            'name' => 'Hero Banner',
            'category' => 'content',
        ]);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'hero-banner',
            'name' => 'Hero Banner',
            'category' => 'content',
        ]);
        $this->assertEquals('hero-banner', $blockType->slug);
    }

    public function test_is_core_cast_to_boolean(): void
    {
        $blockType = BlockType::factory()->create(['is_core' => 1]);

        $this->assertIsBool($blockType->is_core);
        $this->assertTrue($blockType->is_core);

        $blockType2 = BlockType::factory()->create(['is_core' => 0]);

        $this->assertIsBool($blockType2->is_core);
        $this->assertFalse($blockType2->is_core);
    }

    public function test_schema_cast_to_array(): void
    {
        $schemaData = [
            'fields' => [
                ['name' => 'title', 'type' => 'text'],
                ['name' => 'image', 'type' => 'media'],
            ],
        ];
        $blockType = BlockType::factory()->create(['schema' => $schemaData]);

        $blockType->refresh();

        $this->assertIsArray($blockType->schema);
        $this->assertCount(2, $blockType->schema['fields']);
        $this->assertEquals('title', $blockType->schema['fields'][0]['name']);
    }

    public function test_plugin_factory_state(): void
    {
        $blockType = BlockType::factory()->plugin('seo-tools')->create();

        $this->assertFalse($blockType->is_core);
        $this->assertEquals('seo-tools', $blockType->plugin_slug);
    }
}
