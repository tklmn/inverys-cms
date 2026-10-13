<?php

namespace Plugins\Gallery\Search;

use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;
use Plugins\Gallery\Models\GalleryAlbum;

/** Backend quick search: albums by name, address and description */
class AlbumAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'gallery-albums';
    }

    public function label(): string
    {
        return 'gallery::gallery.albums';
    }

    public function icon(): string
    {
        return 'images';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('edit-gallery-album')) {
            return [];
        }

        $found = AdminSearch::whereWords(GalleryAlbum::query(), $query, ['name', 'slug', 'description'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        foreach ($found as $item) {
            yield [
                'title' => (string) $item->name,
                'url' => route('backend.gallery.edit', $item),
                'context' => '/'.$item->slug,
            ];
        }
    }
}
