<?php

namespace Tests\Feature\Plugins\Gallery;

use App\Models\Media;
use App\Models\Plugin;
use App\Services\BlockRenderer;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\Gallery\Models\GalleryAlbum;
use Tests\TestCase;

/**
 * Gallery grid and lightbox use the scaled copies of the media library
 * instead of the 300 px backend thumbnail.
 */
class GalleryResponsiveImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        Plugin::create(['slug' => 'gallery', 'name' => 'Gallery', 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);

        $manager = app(PluginManager::class);
        $manager->discover();
        $manager->registerOnly('gallery');
        $manager->getLoadedProviders()['gallery']->install();

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function album(): GalleryAlbum
    {
        $album = GalleryAlbum::create(['name' => 'Trip', 'slug' => 'trip', 'settings' => [], 'is_active' => true]);

        foreach (['one', 'two'] as $i => $name) {
            $media = Media::factory()->create([
                'path' => "media/2026/10/{$name}.jpg",
                'thumbnail_path' => "media/2026/10/thumb_{$name}.jpg",
                'mime_type' => 'image/jpeg',
                'alt_text' => ucfirst($name),
                'width' => 1600,
                'height' => 1200,
                'variants' => [
                    ['width' => 480, 'height' => 360, 'path' => "media/2026/10/{$name}-480w.webp", 'mime' => 'image/webp'],
                    ['width' => 768, 'height' => 576, 'path' => "media/2026/10/{$name}-768w.webp", 'mime' => 'image/webp'],
                ],
            ]);
            $album->images()->attach($media->id, ['sort_order' => $i]);
        }

        return $album;
    }

    public function test_grid_and_lightbox_use_the_scaled_copies(): void
    {
        $album = $this->album();

        $html = app(BlockRenderer::class)->render([
            ['id' => 'g', 'type' => 'gallery', 'data' => ['album_id' => $album->id], 'settings' => []],
        ]);

        $this->assertStringNotContainsString('thumb_one.jpg', $html, 'No 300 px thumbnail on the website');
        $this->assertStringContainsString('one-480w.webp 480w', $html);
        $this->assertStringContainsString('width="1600" height="1200"', $html);
        $this->assertStringContainsString('data-srcset="', $html);
        $this->assertStringContainsString('data-sizes="100vw"', $html);

        // The gallery is the page's first image block: its first image gets the priority
        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        preg_match('/<img[^>]*alt="One"[^>]*>/', $html, $first);
        $this->assertStringContainsString('fetchpriority="high"', $first[0]);
    }
}
