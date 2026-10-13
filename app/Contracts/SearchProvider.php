<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface SearchProvider
{
    /**
     * Unique key for this provider (used in settings: search.include_{key}).
     */
    public function key(): string;

    /**
     * Human-readable label (translation key).
     */
    public function label(): string;

    /**
     * Configurable search fields for this provider.
     * Returns: ['field_key' => ['label' => 'translation.key', 'default' => 'on']]
     * Settings stored as: search.{provider_key}.fields.{field_key}
     */
    public function fields(): array;

    /**
     * Search and return a collection of result objects.
     * Each result: (object) ['title', 'description', 'url', 'type']
     * $fieldFlags: ['field_key' => true/false] based on admin settings.
     */
    public function search(string $query, array $fieldFlags): Collection;
}
