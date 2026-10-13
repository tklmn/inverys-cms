<?php

namespace App\Search\Admin;

use App\Contracts\AdminSearchProvider;
use App\Models\Page;
use App\Models\User;
use App\Services\AdminSearch;

/** Pages by title, menu title and address — inactive ones too */
class PageAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'translation.admin_search.pages';
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('edit-page')) {
            return [];
        }

        $pages = AdminSearch::whereWords(Page::query(), $query, ['title', 'nav_title', 'slug'])
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'is_active']);

        foreach ($pages as $page) {
            $path = $page->slug === '/' ? '/' : '/'.ltrim((string) $page->slug, '/');

            yield [
                'title' => (string) $page->title,
                'url' => route('backend.pages.edit', $page),
                'context' => $page->is_active ? $path : $path.' · '.__('translation.admin_search.inactive'),
            ];
        }
    }
}
