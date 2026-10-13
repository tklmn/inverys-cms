<?php

namespace App\Contracts;

interface PageManager
{
    public function create(array $attributes): mixed;

    public function findBySlug(string $slug): mixed;

    public function updateBySlug(string $slug, array $attributes): bool;

    public function deleteBySlug(string $slug): bool;

    public function maxOrder(?int $parentId = null): int;

    /**
     * Active pages as a [slug => title] map, ordered by title — for select inputs.
     *
     * @return array<string, string>
     */
    public function activeForSelect(): array;
}
