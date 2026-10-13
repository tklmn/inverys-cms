<?php

namespace Plugins\Gallery;

use App\Services\BlockRenderer;
use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Plugins\Gallery\Http\Controllers\Admin\AlbumController;
use Plugins\Gallery\Http\Controllers\Admin\SettingsController;
use Plugins\Gallery\Models\GalleryAlbum;
use Plugins\Gallery\Search\AlbumAdminSearch;
use Plugins\Gallery\Search\GallerySearchProvider;
use Plugins\Gallery\Services\GalleryMediaUsageChecker;

class GalleryServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\Gallery');
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        // View namespaces — theme override for frontend
        $this->registerPluginViews('gallery', $pluginPath.'/views/frontend');
        $this->registerPluginViews('gallery-admin', $pluginPath.'/views/admin');

        // Translations
        $this->app->make('translator')->addNamespace('gallery', $pluginPath.'/lang');

        // A gallery as the page's first image block loads its first image with high priority
        BlockRenderer::registerImageBlock('gallery', fn (array $data) => ! empty($data['album_id']));

        // Backend routes
        $this->routes()->backend('gallery', function () {
            Route::get('/', [AlbumController::class, 'index'])->name('backend.gallery.index');
            Route::get('/create', [AlbumController::class, 'create'])->name('backend.gallery.create');
            Route::post('/', [AlbumController::class, 'store'])->name('backend.gallery.store');

            // Static routes must be registered before the /{album} wildcard, otherwise
            // "settings" is captured as a {album} route-model binding (404 on save).
            Route::get('/settings', [SettingsController::class, 'edit'])->name('backend.gallery.settings');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.gallery.settings.update');

            Route::get('/{album}/edit', [AlbumController::class, 'edit'])->name('backend.gallery.edit');
            Route::put('/{album}', [AlbumController::class, 'update'])->name('backend.gallery.update');
            Route::delete('/{album}', [AlbumController::class, 'destroy'])->name('backend.gallery.destroy');

            // Image management
            Route::post('/{album}/images', [AlbumController::class, 'addImages'])->name('backend.gallery.images.add');
            Route::delete('/{album}/images/{image}', [AlbumController::class, 'removeImage'])->name('backend.gallery.images.remove');
            Route::post('/{album}/images/reorder', [AlbumController::class, 'reorderImages'])->name('backend.gallery.images.reorder');
            Route::put('/{album}/images/{image}/caption', [AlbumController::class, 'updateCaption'])->name('backend.gallery.images.caption');
        });

        // Sidebar
        $this->extensions()->registerSidebarItem([
            'route' => 'backend.gallery.index',
            'icon' => 'images',
            'label' => 'gallery::gallery.albums',
            'active' => 'backend.gallery.*',
            'section' => 'content',
            'order' => 25,
        ]);

        $this->extensions()->registerSettingsPrefix('gallery.');

        // Dashboard widgets
        $this->extensions()->registerDashboardWidget([
            'type' => 'stat',
            'label' => 'gallery::gallery.albums',
            'icon' => 'bi-collection',
            'icon_bg' => 'bg-pink-500 dark:bg-pink-600',
            'link_class' => 'text-pink-600 hover:text-pink-700 dark:text-pink-400 dark:hover:text-pink-300',
            'route' => 'backend.gallery.index',
            'permission' => 'show-gallery-album',
            'count' => fn () => Schema::hasTable('gallery_albums') ? DB::table('gallery_albums')->whereNull('deleted_at')->count() : 0,
        ]);

        // Search provider
        $this->extensions()->registerSearchProvider(new GallerySearchProvider);
        $this->extensions()->registerAdminSearchProvider(new AlbumAdminSearch);

        // Media usage checker
        $this->extensions()->registerMediaUsageChecker(new GalleryMediaUsageChecker);

        // Trash tab
        $this->extensions()->registerTrashTab([
            'type' => 'gallery-album',
            'label' => 'gallery::gallery.albums',
            'icon' => 'bi-images',
            'model' => GalleryAlbum::class,
            'title_column' => 'name',
            // Restore/permanently delete in the trash
            'permission' => 'delete-gallery-album',
        ]);

        // Head hook — page builder block type registration
        $this->extensions()->registerHeadHook('gallery-admin::block-type-script');

        // Body hook — GLightbox assets
        $this->extensions()->registerBodyHook('gallery::lightbox-assets');

        // View composer to share albums list with the block type script
        View::composer('gallery-admin::block-type-script', function ($view) {
            $albums = [];
            // The view renders nothing outside the backend (see there)
            if (request()->is('backend', 'backend/*') && Schema::hasTable('gallery_albums')) {
                $albums = GalleryAlbum::where('is_active', true)
                    ->pluck('name', 'id')
                    ->toArray();
            }
            $view->with('albums', $albums);
        });
    }

    public function syncPermissions(): void
    {
        $permNames = [
            'show-gallery-album',
            'create-gallery-album',
            'edit-gallery-album',
            'delete-gallery-album',
            'gallery-settings',
        ];

        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
        // Editor = content management: albums in full, settings admin only
        $this->permissions()->assignToRole('editor', array_values(array_diff($permNames, ['gallery-settings'])));
    }

    public function install(): void
    {
        // Create gallery_albums table
        if (! Schema::hasTable('gallery_albums')) {
            Schema::create('gallery_albums', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255)->unique();
                $table->text('description')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Create gallery_album_images pivot table
        if (! Schema::hasTable('gallery_album_images')) {
            Schema::create('gallery_album_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
                $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('caption', 500)->nullable();
                $table->timestamps();

                $table->unique(['album_id', 'media_id']);
                $table->index(['album_id', 'sort_order']);
            });
        }

        // Register gallery block type for page builder
        $this->registerBlockType([
            'slug' => 'gallery',
            'name' => 'Gallery',
            'category' => 'content',
            'icon' => 'bi-images',
        ]);

        // Seed default settings
        $defaults = [
            'gallery.columns' => ['value' => '3', 'type' => 'integer', 'description' => 'Default number of columns'],
            'gallery.lightbox' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable lightbox by default'],
            'gallery.images_per_page' => ['value' => '12', 'type' => 'integer', 'description' => 'Images per page (0 = all)'],
            'gallery.pagination' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable pagination by default'],
            'gallery.thumbnail_size' => ['value' => 'medium', 'type' => 'string', 'description' => 'Default thumbnail size'],
            'gallery.gap' => ['value' => '4', 'type' => 'integer', 'description' => 'Grid gap (Tailwind units)'],
            'gallery.aspect_ratio' => ['value' => 'cover', 'type' => 'string', 'description' => 'Default aspect ratio mode'],
            'gallery.show_captions' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show captions by default'],
            'gallery.lazy_loading' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable lazy loading by default'],
        ];

        $this->seedSettings($defaults);

        // Create permissions and assign to roles
        $this->syncPermissions();
    }

    public function uninstall(): void
    {
        Schema::dropIfExists('gallery_album_images');
        Schema::dropIfExists('gallery_albums');

        $this->unregisterBlockType('gallery');

        $keys = [
            'gallery.columns', 'gallery.lightbox', 'gallery.images_per_page',
            'gallery.pagination', 'gallery.thumbnail_size', 'gallery.gap',
            'gallery.aspect_ratio', 'gallery.show_captions', 'gallery.lazy_loading',
        ];

        foreach ($keys as $key) {
            $this->settings()->delete($key);
        }
    }
}
