<?php

namespace Plugins\Blog\Search;

use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;
use Plugins\Blog\Models\Post;

/** Backend quick search: posts by title, address and excerpt — drafts included */
class PostAdminSearch implements AdminSearchProvider
{
    public function key(): string
    {
        return 'blog-posts';
    }

    public function label(): string
    {
        return 'blog::blog.posts';
    }

    public function icon(): string
    {
        return 'newspaper';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        if (! $user->hasPermission('edit-post')) {
            return [];
        }

        $found = AdminSearch::whereWords(Post::query(), $query, ['title', 'slug', 'excerpt'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        foreach ($found as $item) {
            yield [
                'title' => (string) $item->title,
                'url' => route('backend.blog.edit', $item),
                'context' => $item->is_active && $item->published_at && $item->published_at->isPast() ? '/'.$item->slug : '/'.$item->slug.' · '.__('blog::blog.draft'),
            ];
        }
    }
}
