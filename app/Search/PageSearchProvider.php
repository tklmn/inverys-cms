<?php

namespace App\Search;

use App\Contracts\SearchProvider;
use App\Helpers\SearchHelper;
use App\Models\Page;
use Illuminate\Support\Collection;

class PageSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'translation.settings.search_include_pages';
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => 'translation.settings.search_field_title', 'default' => 'on'],
            'content' => ['label' => 'translation.settings.search_field_content', 'default' => 'on'],
            'meta' => ['label' => 'translation.settings.search_field_meta', 'default' => 'on'],
        ];
    }

    public function search(string $query, array $fieldFlags): Collection
    {
        $search = SearchHelper::escapeLike($query);

        return Page::where('is_active', true)
            ->where('include_in_search', true)
            ->where(function ($q) use ($search, $fieldFlags) {
                if ($fieldFlags['title'] ?? false) {
                    $q->orWhere('title', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['content'] ?? false) {
                    $q->orWhere('content', 'LIKE', "%{$search}%");
                }
                if ($fieldFlags['meta'] ?? false) {
                    $q->orWhere('meta_description', 'LIKE', "%{$search}%");
                }

                // Multilingual: also search the texts in the visitor's language
                $q->orWhereTranslation(array_keys(array_filter([
                    'title' => $fieldFlags['title'] ?? false,
                    'content' => $fieldFlags['content'] ?? false,
                    'meta_description' => $fieldFlags['meta'] ?? false,
                ])), "%{$search}%");
            })
            ->orderBy('title')
            // Upper limit: a short query should not load every page into memory
            ->limit(200)
            ->get()
            ->map(fn ($page) => (object) [
                'title' => $page->title,
                'description' => $page->meta_description ?: \Str::limit(strip_tags($page->content), 160),
                'url' => $page->is_root ? route('home') : route('page.show', $page->slug),
                'type' => 'pages',
            ]);
    }
}
