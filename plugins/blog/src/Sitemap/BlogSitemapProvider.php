<?php

namespace Plugins\Blog\Sitemap;

use App\Contracts\SitemapProvider;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\Post;

/**
 * Blog in the sitemap: overview and published posts (active and
 * publication date reached — no drafts, no scheduled ones).
 * The categories are reported by BlogCategorySitemapProvider (own switch).
 */
class BlogSitemapProvider implements SitemapProvider
{
    public function key(): string
    {
        return 'blog';
    }

    public function label(): ?string
    {
        return 'blog::blog.sitemap_posts';
    }

    public function entries(): iterable
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $posts = Post::published()->orderByDesc('published_at')->get();

        yield [
            'loc' => route('blog.index'),
            'lastmod' => $posts->max('updated_at'),
        ];

        foreach ($posts as $post) {
            yield [
                'loc' => route('blog.show', $post->slug),
                'lastmod' => $post->updated_at,
                'model' => $post,
            ];
        }
    }
}
