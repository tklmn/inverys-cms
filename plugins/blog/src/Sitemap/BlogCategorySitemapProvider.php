<?php

namespace Plugins\Blog\Sitemap;

use App\Contracts\SitemapProvider;
use Illuminate\Support\Facades\Schema;
use Plugins\Blog\Models\BlogCategory;

/**
 * Blog categories in the sitemap — only those with published
 * posts; last modified = newest post in them.
 */
class BlogCategorySitemapProvider implements SitemapProvider
{
    public function key(): string
    {
        return 'blog_categories';
    }

    public function label(): ?string
    {
        return 'blog::blog.sitemap_categories';
    }

    public function entries(): iterable
    {
        if (! Schema::hasTable('blog_categories')) {
            return;
        }

        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withMax(['posts' => fn ($q) => $q->published()], 'updated_at')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            yield [
                'loc' => route('blog.category', $category->slug),
                'lastmod' => $category->posts_max_updated_at,
                'model' => $category,
            ];
        }
    }
}
