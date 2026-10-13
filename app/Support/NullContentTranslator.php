<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use Illuminate\Database\Eloquent\Model;

/**
 * Default without the multilingual plugin: there are no translations.
 * Every model returns its original values, editors show no language picker.
 */
final class NullContentTranslator implements ContentTranslator
{
    public function enabled(): bool
    {
        return false;
    }

    public function get(Model $model, string $field, string $locale): mixed
    {
        return null;
    }

    public function all(Model $model, string $locale): array
    {
        return [];
    }

    public function save(Model $model, string $locale, array $values): void
    {
        //
    }

    public function findBySlug(string $modelClass, string $slug, string $locale): ?int
    {
        return null;
    }

    public function locales(Model $model): array
    {
        return [];
    }

    public function localesFor(string $modelClass, ?array $ids = null): array
    {
        return [];
    }

    public function search(string $modelClass, array $fields, string $pattern, array $locales): array
    {
        return [];
    }

    public function forget(Model $model): void
    {
        //
    }

    public function copy(Model $model, string $from, string $to): int
    {
        return 0;
    }

    public function draftOf(Model $model, string $locale): ?string
    {
        return null;
    }

    public function draftsOf(Model $model): array
    {
        return [];
    }

    public function draftsFor(string $modelClass): array
    {
        return [];
    }

    public function clearDraft(Model $model, string $locale): void
    {
        //
    }
}
