<?php

namespace Tests\Feature\Plugins\Gallery;

use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\Gallery\Models\GalleryAlbum;
use Tests\TestCase;

/**
 * Editor = content management: fully manage albums (including deleting),
 * the gallery settings remain reserved for the admin.
 */
class AlbumPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        Plugin::create([
            'slug' => 'gallery',
            'name' => 'Gallery',
            'version' => '1.0.0',
            'is_enabled' => true,
            'is_system' => true,
        ]);

        // install() creates the tables and calls syncPermissions()
        $manager = app(PluginManager::class);
        $manager->discover();
        $manager->registerOnly('gallery');
        $manager->getLoadedProviders()['gallery']->install();

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    public function test_editor_can_delete_album(): void
    {
        $album = GalleryAlbum::create(['name' => 'Editor Album', 'slug' => 'editor-album', 'settings' => [], 'is_active' => true]);

        $this->actingAs($this->createEditor())
            ->delete(route('backend.gallery.destroy', $album))
            ->assertRedirect(route('backend.gallery.index'));

        $this->assertSoftDeleted('gallery_albums', ['id' => $album->id]);
    }

    public function test_editor_cannot_open_gallery_settings(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.gallery.settings'))->assertForbidden();
        $this->actingAs($editor)->put(route('backend.gallery.settings.update'), [])->assertForbidden();
    }
}
