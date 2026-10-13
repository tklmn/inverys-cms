<?php

namespace Plugins\Blog\Search;

use App\Contracts\SearchProvider;
use App\Helpers\SearchHelper;
use Illuminate\Support\Collection;
use Plugins\Blog\Models\Post;

class BlogSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'blog';
    }

    public function label(): string
    {
        return 'blog::blog.posts';
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => 'translation.settings.search_field_title', 'default' => 'on'],
            'content' => ['label' => 'translation.settings.search_field_content', 'default' => 'on'],
            'excerpt' => ['label' => 'blog::blog.excerpt', 'default' => 'on'],
            'meta' => ['label' => 'translation.settings.search_field_meta', 'default' => 'on'],
        ];
    }

    public function search(string $query, array $fieldFlags): Collection
    {
        $search = SearchHelper::escapeLike($query);

        return Post::published()
            ->where(function ($q) use ($search, $fieldFlags) {
                if ($fieldFlags['title'] ?? false) {
                    $q->orWhere('title', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['content'] ?? false) {
                    $q->orWhere('content', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['excerpt'] ?? false) {
                    $q->orWhere('excerpt', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['meta'] ?? false) {
                    $q->orWhere('meta_description', 'LIKE', "%{$search}%");
                }

                // Multilingual: also search the texts of the visitor's language
                $q->orWhereTranslation(array_keys(array_filter([
                    'title' => $fieldFlags['title'] ?? false,
                    'content' => $fieldFlags['content'] ?? false,
                    'excerpt' => $fieldFlags['excerpt'] ?? false,
                    'meta_description' => $fieldFlags['meta'] ?? false,
                ])), "%{$search}%");
            })
            ->orderBy('title')
            ->limit(200)
            ->get()
            ->map(fn ($post) => (object) [
                'title' => $post->title,
                'description' => $post->excerpt ?: $post->meta_description ?: \Str::limit(strip_tags($post->content), 160),
                'url' => route('blog.show', $post->slug),
                'type' => 'blog',
            ]);
    }
}
