<?php

namespace App\Search\Admin;

use App\Contracts\AdminSearchProvider;
use App\Models\Media;
use App\Models\User;
use App\Services\AdminSearch;

/** Media library files by name, file name, alt text and description */
class MediaAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return 'translation.admin_search.media';
    }

    public function icon(): string
    {
        return 'image';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('show-media')) {
            return [];
        }

        $files = AdminSearch::whereWords(Media::query(), $query, ['name', 'file_name', 'alt_text', 'description'])
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'file_name']);

        foreach ($files as $file) {
            yield [
                'title' => (string) ($file->name ?: $file->file_name),
                'url' => route('backend.media.show', $file),
                'context' => $file->name && $file->name !== $file->file_name ? (string) $file->file_name : null,
            ];
        }
    }
}
