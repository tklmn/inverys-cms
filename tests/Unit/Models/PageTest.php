<?php

namespace Tests\Unit\Models;

use App\Models\Page;
use App\Models\PageCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_auto_generated_from_title(): void
    {
        $page = Page::create(['title' => 'Hello World']);
        $this->assertEquals('hello-world', $page->slug);
    }

    public function test_root_page_gets_slash_slug(): void
    {
        $page = Page::create(['title' => 'Home', 'is_root' => true]);
        $this->assertEquals('/', $page->slug);
    }

    public function test_level_set_from_parent(): void
    {
        $parent = Page::factory()->create(['level' => 0]);
        $child = Page::factory()->childOf($parent)->create();

        $this->assertEquals(1, $child->level);
    }

    public function test_get_path_for_root(): void
    {
        $page = Page::factory()->root()->create();
        $this->assertEquals('/', $page->getPath());
    }

    public function test_get_path_for_nested_page(): void
    {
        $root = Page::factory()->root()->create();
        $parent = Page::factory()->create(['slug' => 'about', 'parent_id' => $root->id]);
        $child = Page::factory()->create(['slug' => 'team', 'parent_id' => $parent->id]);

        $this->assertEquals('/about/team', $child->getPath());
    }

    public function test_children_relationship(): void
    {
        $parent = Page::factory()->create();
        Page::factory()->count(3)->childOf($parent)->create();

        $this->assertCount(3, $parent->children);
    }

    public function test_parent_relationship(): void
    {
        $parent = Page::factory()->create();
        $child = Page::factory()->childOf($parent)->create();

        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_categories_relationship(): void
    {
        $page = Page::factory()->create();
        $cat = PageCategory::factory()->create();
        $page->categories()->attach($cat);

        $this->assertCount(1, $page->categories);
        $this->assertEquals($cat->id, $page->categories->first()->id);
    }

    public function test_blocks_cast_to_array(): void
    {
        $blocks = [
            ['id' => 'b1', 'type' => 'text', 'data' => ['content' => 'Hello']],
        ];
        $page = Page::factory()->create(['blocks' => $blocks, 'content_type' => 'blocks']);

        $this->assertIsArray($page->blocks);
        $this->assertEquals('text', $page->blocks[0]['type']);
    }

    public function test_soft_delete(): void
    {
        $page = Page::factory()->create();
        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->assertNull(Page::find($page->id));
        $this->assertNotNull(Page::withTrashed()->find($page->id));
    }

    public function test_only_one_root_page(): void
    {
        $home1 = Page::factory()->root()->create();
        $home2 = Page::factory()->create(['title' => 'New Home', 'is_root' => true]);

        $home1->refresh();
        $this->assertFalse($home1->is_root);
        $this->assertTrue($home2->is_root);
    }

    public function test_move_to_changes_parent(): void
    {
        $parent1 = Page::factory()->create();
        $parent2 = Page::factory()->create();
        $child = Page::factory()->childOf($parent1)->create();

        $child->moveTo($parent2->id);

        $this->assertEquals($parent2->id, $child->parent_id);
        $this->assertEquals(1, $child->level);
    }
}
