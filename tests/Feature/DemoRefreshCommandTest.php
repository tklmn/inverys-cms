<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use Database\Seeders\Demo\DemoPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoRefreshCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_demo_blocks_and_keeps_a_revision(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/demo/hero-abstract.svg', '<svg><text>Demo Image</text></svg>');
        Media::create(['name' => 'Hero', 'file_name' => 'hero-abstract.svg', 'path' => 'media/demo/hero-abstract.svg', 'mime_type' => 'image/svg+xml', 'size' => 1]);

        $page = Page::create(['title' => 'About Us', 'slug' => 'about', 'content_type' => 'blocks', 'is_active' => true,
            'blocks' => [['id' => 'old', 'type' => 'text', 'data' => ['content' => '<p class="text-lg">Old</p>'], 'settings' => []]]]);
        $other = Page::create(['title' => 'Mine', 'slug' => 'mine', 'content_type' => 'html', 'content' => '<p>Mine</p>', 'is_active' => true]);

        $this->artisan('cms:demo-refresh', ['--force' => true])->assertSuccessful();

        $page->refresh();
        $this->assertSame(DemoPages::for('about'), $page->blocks);
        $this->assertSame('hero', $page->blocks[0]['type']);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertStringNotContainsString('<text', Storage::disk('public')->get('media/demo/hero-abstract.svg'));

        // Own pages stay untouched
        $this->assertSame('<p>Mine</p>', $other->fresh()->blocks[0]['data']['content']);
    }

    public function test_it_creates_missing_feature_pages_below_features(): void
    {
        Storage::fake('public');
        $features = Page::create(['title' => 'Features', 'slug' => 'features', 'content_type' => 'blocks', 'is_active' => true, 'blocks' => []]);

        $this->artisan('cms:demo-refresh', ['--force' => true])->assertSuccessful();

        $children = Page::where('parent_id', $features->id)->orderBy('order')->pluck('slug')->all();
        $this->assertSame(array_keys(DemoPages::CHILDREN['features']), $children);
        $this->assertTrue(Storage::disk('public')->exists('media/demo/feature-languages.svg'));

        // A second run creates nothing twice
        $this->artisan('cms:demo-refresh', ['--force' => true])->assertSuccessful();
        $this->assertSame(count($children), Page::where('parent_id', $features->id)->count());
    }
}
