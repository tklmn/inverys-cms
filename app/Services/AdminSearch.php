<?php

namespace App\Services;

use App\Contracts\ExtensionRegistrar;
use App\Helpers\SearchHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

/**
 * Backend quick search: queries all registered AdminSearchProviders
 * (core and plugins) and returns the hits grouped. A provider that
 * fails is just missing from the list — the search itself carries on.
 */
class AdminSearch
{
    public const MIN_LENGTH = 2;

    public function __construct(private ExtensionRegistrar $extensions) {}

    /**
     * @return list<array{key: string, label: string, icon: string, items: list<array{title: string, url: string, context: ?string}>}>
     */
    public function search(string $query, User $user, int $limit = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [];
        foreach ($this->extensions->getAdminSearchProviders() as $provider) {
            try {
                $items = [];
                foreach ($provider->search($query, $user, $limit) as $item) {
                    if (empty($item['title']) || empty($item['url'])) {
                        continue;
                    }
                    $items[] = [
                        'title' => (string) $item['title'],
                        'url' => (string) $item['url'],
                        'context' => isset($item['context']) && $item['context'] !== '' ? (string) $item['context'] : null,
                    ];
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($items !== []) {
                $groups[] = [
                    'key' => $provider->key(),
                    'label' => __($provider->label()),
                    'icon' => $provider->icon(),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    // ── Helpers for providers ────────────────────────────────────────

    /**
     * May $user open the page behind $routeName? Reads the permissions from the
     * route and its controller (permission:…) — the same ones that apply on request.
     */
    public static function mayOpen(string $routeName, User $user): bool
    {
        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('/^(?:permission|App\\\\Http\\\\Middleware\\\\CheckPermission):(.+)$/', $middleware, $m)
                && ! $user->hasPermission($m[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every word of the query in one of the columns (LIKE, wildcards escaped).
     *
     * @param  list<string>  $columns
     */
    public static function whereWords(Builder $builder, string $query, array $columns): Builder
    {
        foreach (preg_split('/\s+/u', trim($query)) ?: [] as $word) {
            $like = '%'.SearchHelper::escapeLike($word).'%';
            $builder->where(function (Builder $q) use ($columns, $like) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', $like);
                }
            });
        }

        return $builder;
    }

    /** Text for comparison: lowercase, without accents and umlaut dots */
    public static function fold(string $text): string
    {
        $text = mb_strtolower($text);
        $normalized = class_exists(\Normalizer::class) ? \Normalizer::normalize($text, \Normalizer::FORM_D) : $text;

        return trim((string) preg_replace('/\p{Mn}+/u', '', (string) $normalized));
    }

    /** Do all words of the query occur in $haystack? */
    public static function matches(string $query, string $haystack): bool
    {
        $haystack = self::fold($haystack);
        foreach (preg_split('/\s+/u', self::fold($query)) ?: [] as $word) {
            if ($word !== '' && ! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }
}
