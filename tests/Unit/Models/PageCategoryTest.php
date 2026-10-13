<?php

namespace Tests\Unit\Models;

use App\Models\Page;
use App\Models\PageCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_page_category(): void
    {
        $category = PageCategory::factory()->create([
            'name' => 'Blog',
            'slug' => 'blog',
        ]);

        $this->assertDatabaseHas('page_categories', [
            'name' => 'Blog',
            'slug' => 'blog',
        ]);
        $this->assertEquals('Blog', $category->name);
        $this->assertEquals('blog', $category->slug);
    }

    public function test_can_attach_pages_to_category(): void
    {
        $category = PageCategory::factory()->create();
        $page1 = Page::factory()->create();
        $page2 = Page::factory()->create();

        $category->pages()->attach([$page1->id, $page2->id]);

        $this->assertCount(2, $category->pages);
        $this->assertTrue($category->pages->contains($page1));
        $this->assertTrue($category->pages->contains($page2));
    }

    public function test_pages_relationship_returns_correct_pages(): void
    {
        $category = PageCategory::factory()->create();
        $attachedPage = Page::factory()->create();
        $unattachedPage = Page::factory()->create();

        $category->pages()->attach($attachedPage);

        $this->assertCount(1, $category->pages);
        $this->assertTrue($category->pages->contains($attachedPage));
        $this->assertFalse($category->pages->contains($unattachedPage));
    }
}
