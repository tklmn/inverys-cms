<?php

namespace App\Services\Cms;

use App\Contracts\MediaManager;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class EloquentMediaManager implements MediaManager
{
    public function store(string $path, string $content, string $disk = 'public'): string
    {
        $storage = Storage::disk($disk);
        $dir = dirname($path);
        if ($dir !== '.') {
            $storage->makeDirectory($dir);
        }
        $storage->put($path, $content);

        return $path;
    }

    public function createRecord(array $attributes): mixed
    {
        return Media::firstOrCreate(
            ['path' => $attributes['path']],
            $attributes
        );
    }

    public function storeAndRecord(string $path, string $content, array $attributes, string $disk = 'public'): mixed
    {
        $this->store($path, $content, $disk);

        $storage = Storage::disk($disk);
        $attributes['path'] = $path;
        $attributes['size'] = $attributes['size'] ?? $storage->size($path);

        return $this->createRecord($attributes);
    }

    public function findByPath(string $path): mixed
    {
        return Media::where('path', $path)->first();
    }

    public function delete(mixed $media): bool
    {
        if (! $media instanceof Media) {
            return false;
        }

        // Soft delete only — physical files are removed on forceDelete (see Media::booted)
        // so the item remains restorable from the trash.
        return $media->delete();
    }
}
