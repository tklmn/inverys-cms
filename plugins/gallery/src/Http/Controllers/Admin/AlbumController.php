<?php

namespace Plugins\Gallery\Http\Controllers\Admin;

use App\Helpers\SearchHelper;
use App\Support\EditLocale;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Plugins\Gallery\Models\GalleryAlbum;

#[Middleware('permission:show-gallery-album', only: ['index'])]
#[Middleware('permission:create-gallery-album', only: ['create', 'store'])]
#[Middleware('permission:edit-gallery-album', only: ['edit', 'update', 'addImages', 'removeImage', 'reorderImages', 'updateCaption'])]
#[Middleware('permission:delete-gallery-album', only: ['destroy'])]
class AlbumController extends Controller
{
    public function index(Request $request): View
    {
        $albums = GalleryAlbum::withCount('images')
            // Only the first image per album — it serves as the cover in the list
            ->with(['images' => fn ($query) => $query->limit(1)])
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('gallery-admin::index', [
            'albums' => $albums,
        ]);
    }

    public function create(): View
    {
        return view('gallery-admin::create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        $album = GalleryAlbum::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('backend.gallery.edit', $album)
            ->with('success', __('gallery::gallery.album_created'));
    }

    public function edit(GalleryAlbum $album): View
    {
        $album->load('images');

        $globalDefaults = [
            'columns' => cms()->settings()->get('gallery.columns', '3'),
            'gap' => cms()->settings()->get('gallery.gap', '4'),
            'aspect_ratio' => cms()->settings()->get('gallery.aspect_ratio', 'cover'),
            'thumbnail_size' => cms()->settings()->get('gallery.thumbnail_size', 'medium'),
            'lightbox' => cms()->settings()->get('gallery.lightbox', '1'),
            'show_captions' => cms()->settings()->get('gallery.show_captions', '1'),
            'lazy_loading' => cms()->settings()->get('gallery.lazy_loading', '1'),
            'pagination' => cms()->settings()->get('gallery.pagination', '1'),
            'images_per_page' => cms()->settings()->get('gallery.images_per_page', '12'),
        ];

        return view('gallery-admin::edit', [
            'album' => $album,
            'globalDefaults' => $globalDefaults,
        ]);
    }

    public function update(Request $request, GalleryAlbum $album)
    {
        // Translation: name, description, captions (Multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            $data = $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'captions' => 'nullable|array',
                'captions.*' => 'nullable|string|max:500',
            ]);

            $captions = array_filter($data['captions'] ?? [], fn ($c) => filled($c));

            $album->saveTranslations($locale, [
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'captions' => $captions ? json_encode($captions) : null,
            ]);

            return redirect()
                ->route('backend.gallery.edit', ['album' => $album, 'lang' => $locale])
                ->with('success', __('gallery::gallery.album_updated'));
        }

        $validated = $request->validate($this->validationRules($album->id));

        // Build per-album settings (only store non-empty overrides)
        $settingsKeys = ['columns', 'gap', 'aspect_ratio', 'thumbnail_size', 'lightbox', 'show_captions', 'lazy_loading', 'pagination', 'images_per_page'];
        $settings = [];
        foreach ($settingsKeys as $key) {
            $value = $request->input("settings_{$key}");
            if ($value !== null && $value !== '') {
                $settings[$key] = $value;
            }
        }

        $album->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?: Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'settings' => ! empty($settings) ? $settings : null,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('backend.gallery.edit', $album)
            ->with('success', __('gallery::gallery.album_updated'));
    }

    public function destroy(GalleryAlbum $album)
    {
        $album->delete();

        return redirect()
            ->route('backend.gallery.index')
            ->with('success', __('gallery::gallery.album_deleted'));
    }

    // ─── Image Management ────────────────────────────────────────────

    public function addImages(Request $request, GalleryAlbum $album)
    {
        $validated = $request->validate([
            'media_ids' => 'required|array|min:1',
            'media_ids.*' => 'integer|exists:media,id',
        ]);

        $maxSort = (int) $album->images()->max('gallery_album_images.sort_order');

        $attach = [];
        foreach ($validated['media_ids'] as $i => $mediaId) {
            // Skip if already in album
            if ($album->images()->where('media_id', $mediaId)->exists()) {
                continue;
            }
            $attach[$mediaId] = ['sort_order' => $maxSort + $i + 1];
        }

        if (! empty($attach)) {
            $album->images()->attach($attach);
        }

        return response()->json(['message' => __('gallery::gallery.images_added')]);
    }

    public function removeImage(GalleryAlbum $album, int $image)
    {
        $album->images()->detach($image);

        return response()->json(['message' => __('gallery::gallery.image_removed')]);
    }

    public function updateCaption(Request $request, GalleryAlbum $album, int $image)
    {
        $request->validate(['caption' => 'nullable|string|max:500']);
        $album->images()->updateExistingPivot($image, ['caption' => $request->input('caption', '')]);

        return response()->json(['ok' => true]);
    }

    public function reorderImages(Request $request, GalleryAlbum $album)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        foreach ($validated['order'] as $position => $mediaId) {
            $album->images()->updateExistingPivot($mediaId, ['sort_order' => $position]);
        }

        return response()->json(['message' => __('gallery::gallery.images_reordered')]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function validationRules(?int $ignoreId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('gallery_albums')->ignore($ignoreId)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
