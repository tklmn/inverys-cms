<?php

namespace Plugins\Blog\Search;

use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;
use Plugins\Blog\Models\BlogCategory;

/** Backend quick search: blog categories by name and address */
class CategoryAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'blog-categories';
    }

    public function label(): string
    {
        return 'blog::blog.categories';
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('edit-blog-category')) {
            return [];
        }

        $found = AdminSearch::whereWords(BlogCategory::query(), $query, ['name', 'slug'])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        foreach ($found as $item) {
            yield [
                'title' => (string) $item->name,
                'url' => route('backend.blog.categories.edit', $item),
                'context' => '/'.$item->slug,
            ];
        }
    }
}
