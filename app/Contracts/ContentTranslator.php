<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Storage for content translations.
 *
 * The core only ships an empty implementation (NullContentTranslator): without
 * the multilingual plugin there are no translations, every model returns
 * its original values. The plugin binds a real implementation.
 *
 * Models take part via App\Models\Concerns\HasTranslations.
 */
interface ContentTranslator
{
    /** Is there a translation store at all? (plugin active) */
    public function enabled(): bool;

    /** Translated value of a field, or null if there is none. */
    public function get(Model $model, string $field, string $locale): mixed;

    /** All translated fields of a model in one language. @return array<string, mixed> */
    public function all(Model $model, string $locale): array;

    /** Save translations; empty values remove the translation. @param array<string, mixed> $values */
    public function save(Model $model, string $locale, array $values): void;

    /** Model ID for a translated slug, or null. */
    public function findBySlug(string $modelClass, string $slug, string $locale): ?int;

    /** Which languages have translations? @return list<string> */
    public function locales(Model $model): array;

    /**
     * The same for many entries at once (lists in the backend);
     * without IDs for all entries of this kind.
     *
     * @param  list<int|string>|null  $ids
     * @return array<int, list<string>> ID => languages
     */
    public function localesFor(string $modelClass, ?array $ids = null): array;

    /**
     * IDs of the entries whose translation of one of the fields in one of the
     * languages matches — for search. $pattern is a LIKE pattern (already
     * escaped, with %).
     *
     * @param  list<string>  $fields
     * @param  list<string>  $locales
     * @return list<int>
     */
    public function search(string $modelClass, array $fields, string $pattern, array $locales): array;

    /** Remove all translations of a model (on permanent deletion). */
    public function forget(Model $model): void;

    /**
     * Copy the translation of one language as a template for another.
     * Only fields that are still empty in $to; slugs are left out (the
     * address is built from the translated title on save). The model then
     * counts as "copied" in $to until someone saves that version.
     *
     * @return int Number of copied fields
     */
    public function copy(Model $model, string $from, string $to): int;

    /** Which language was the version copied from? null = translated by hand or no version. */
    public function draftOf(Model $model, string $locale): ?string;

    /** All copied versions of a model. @return array<string, string> language => source language */
    public function draftsOf(Model $model): array;

    /**
     * Copied, not yet saved versions for lists.
     *
     * @return array<int, list<string>> ID => languages
     */
    public function draftsFor(string $modelClass): array;

    /** The version counts as translated (someone saved it in the editor). */
    public function clearDraft(Model $model, string $locale): void;
}
