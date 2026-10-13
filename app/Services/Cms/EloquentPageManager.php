<?php

namespace App\Services\Cms;

use App\Contracts\PageManager;
use App\Models\Page;

class EloquentPageManager implements PageManager
{
    public function create(array $attributes): mixed
    {
        return Page::firstOrCreate(
            ['slug' => $attributes['slug']],
            $attributes
        );
    }

    public function findBySlug(string $slug): mixed
    {
        return Page::where('slug', $slug)->first();
    }

    public function updateBySlug(string $slug, array $attributes): bool
    {
        $page = Page::where('slug', $slug)->first();
        if (! $page) {
            return false;
        }

        return $page->update($attributes);
    }

    public function deleteBySlug(string $slug): bool
    {
        return Page::withTrashed()->where('slug', $slug)->forceDelete() > 0;
    }

    public function maxOrder(?int $parentId = null): int
    {
        return (int) Page::where('parent_id', $parentId)->max('order');
    }

    public function activeForSelect(): array
    {
        return Page::where('is_active', true)->orderBy('title')->pluck('title', 'slug')->all();
    }
}
