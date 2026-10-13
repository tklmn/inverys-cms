<?php

namespace Plugins\Gallery\Services;

use App\Contracts\MediaUsageChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GalleryMediaUsageChecker implements MediaUsageChecker
{
    // $media is a core media record (App\Models\Media); typed mixed to keep the
    // plugin free of a compile-time dependency on the core model class.
    public function getUsages(mixed $media): array
    {
        if (! Schema::hasTable('gallery_album_images')) {
            return [];
        }

        $usages = [];

        $albums = DB::table('gallery_album_images')
            ->join('gallery_albums', 'gallery_albums.id', '=', 'gallery_album_images.album_id')
            ->where('gallery_album_images.media_id', $media->id)
            ->whereNull('gallery_albums.deleted_at')
            ->select('gallery_albums.id', 'gallery_albums.name')
            ->get();

        foreach ($albums as $album) {
            $usages[] = [
                'type' => 'gallery-album',
                'label' => __('gallery::gallery.album').': '.$album->name,
                'url' => route('backend.gallery.edit', $album->id),
            ];
        }

        return $usages;
    }
}
