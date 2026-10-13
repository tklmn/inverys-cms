<?php

namespace Plugins\Gallery\Search;

use App\Contracts\SearchProvider;
use App\Helpers\SearchHelper;
use Illuminate\Support\Collection;
use Plugins\Gallery\Models\GalleryAlbum;

class GallerySearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'gallery::gallery.albums';
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'translation.settings.search_field_title', 'default' => 'on'],
            'description' => ['label' => 'gallery::gallery.description', 'default' => 'on'],
        ];
    }

    public function search(string $query, array $fieldFlags): Collection
    {
        // The results lead to the editor in the backend — website visitors
        // should not see them, only those allowed to edit the entry
        if (! auth()->user()?->hasPermission('edit-gallery-album')) {
            return collect();
        }

        $search = SearchHelper::escapeLike($query);

        return GalleryAlbum::where('is_active', true)
            ->where(function ($q) use ($search, $fieldFlags) {
                if ($fieldFlags['name'] ?? false) {
                    $q->orWhere('name', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['description'] ?? false) {
                    $q->orWhere('description', 'LIKE', "%{$search}%");
                }

                // Multilingual: also search the texts of the visitor's language
                $q->orWhereTranslation(array_keys(array_filter([
                    'name' => $fieldFlags['name'] ?? false,
                    'description' => $fieldFlags['description'] ?? false,
                ])), "%{$search}%");
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($album) => (object) [
                'title' => $album->name,
                'description' => $album->description ?: '',
                'url' => route('backend.gallery.edit', $album),
                'type' => 'gallery-album',
            ]);
    }
}
