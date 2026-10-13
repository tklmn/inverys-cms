<?php

namespace Tests\Feature\Plugins\Gallery;

use App\Models\Media;
use App\Models\Plugin;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\Gallery\Models\GalleryAlbum;
use Tests\TestCase;

class GalleryAlbumModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        Plugin::create([
            'slug' => 'gallery',
            'name' => 'Gallery',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        // registerOnly() registers the gallery PSR-4 autoloader (its classes are
        // not in composer's autoload) and boots the provider; install() creates
        // the plugin tables.
        $manager = app(PluginManager::class);
        $manager->discover();
        $manager->registerOnly('gallery');
        $manager->getLoadedProviders()['gallery']->install();

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function makeAlbum(array $attributes = []): GalleryAlbum
    {
        return GalleryAlbum::create(array_merge([
            'name' => 'Test Album',
            'slug' => 'test-album',
            'settings' => [],
            'is_active' => true,
        ], $attributes));
    }

    /**
     * The images() relationship is defined via config('cms.models.media') rather
     * than a direct import, so it must still resolve to the configured core model.
     */
    public function test_images_relationship_resolves_to_configured_media_model(): void
    {
        $album = $this->makeAlbum();
        $media = Media::factory()->create();

        $album->images()->attach($media->id, ['sort_order' => 1]);

        $this->assertInstanceOf(config('cms.models.media'), $album->images()->first());
        $this->assertTrue($album->images->contains($media->id));
    }

    /**
     * creator()/updater() are defined via config('cms.models.user').
     */
    public function test_creator_and_updater_resolve_to_configured_user_model(): void
    {
        $user = User::factory()->create();
        $album = $this->makeAlbum([
            'slug' => 'authored-album',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertInstanceOf(config('cms.models.user'), $album->creator);
        $this->assertInstanceOf(config('cms.models.user'), $album->updater);
        $this->assertSame($user->id, $album->creator->id);
    }

    /**
     * install() registers the page-builder block via the base provider's
     * registerBlockType() SDK helper (no direct BlockType import in the plugin),
     * applying the is_core=false / plugin_slug defaults.
     */
    public function test_install_registers_block_type_via_sdk_helper(): void
    {
        $this->assertDatabaseHas('block_types', [
            'slug' => 'gallery',
            'plugin_slug' => 'gallery',
            'is_core' => false,
        ]);
    }
}
