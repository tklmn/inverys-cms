<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Content types with revisions (models using App\Traits\HasRevisions), so that
 * list, comparison, preview and restore work the same for all of them —
 * pages in the core, blog posts and whatever plugins register:
 *
 *   $this->extensions()->registerRevisionable('post', [
 *       'model' => Post::class,
 *       'permission' => 'edit-post',                    // who may view and restore revisions
 *       'edit_route' => 'backend.blog.edit',            // where to return after restoring
 *       'edit_params' => fn (Post $post) => ['post' => $post],
 *       'labels' => ['title' => 'blog::blog.post_title'], // field names in the comparison (translation keys)
 *       'preview' => fn (Post $snapshot) => '<html>…',  // optional: the page in its old state
 *       'authorize' => fn (Post $post, array $data) => …, // optional: extra checks before restoring (abort to refuse)
 *   ]);
 */
final class Revisionables
{
    /** @var array<string, array<string, mixed>> */
    private array $types = [];

    /** @param  array<string, mixed>  $options */
    public function register(string $type, array $options): void
    {
        $this->types[$type] = $options + [
            'permission' => null,
            'edit_route' => null,
            'edit_params' => null,
            'labels' => [],
            'preview' => null,
            'authorize' => null,
        ];
    }

    /** @return array<string, mixed>|null */
    public function get(string $type): ?array
    {
        return $this->types[$type] ?? null;
    }

    public function typeOf(Model $model): ?string
    {
        foreach ($this->types as $type => $options) {
            if ($model instanceof $options['model']) {
                return $type;
            }
        }

        return null;
    }

    /** Edit URL (where to return after restoring) */
    public function editUrl(string $type, Model $model, array $query = []): ?string
    {
        $options = $this->types[$type] ?? null;
        if (! $options || ! $options['edit_route']) {
            return null;
        }

        $params = is_callable($options['edit_params']) ? ($options['edit_params'])($model) : [$model];

        return route($options['edit_route'], array_merge($params, array_filter($query, fn ($value) => $value !== null)));
    }
}
