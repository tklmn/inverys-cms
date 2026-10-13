<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Contracts\ContentTranslator;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translatable fields for a model.
 *
 * The model lists its text fields in `protected array $translatable = [...]`.
 * In the frontend, `$model->title` then returns the title in the current
 * content language — falling back to the default language and finally to the
 * original value. Menus, breadcrumbs and lists thus translate themselves,
 * without views knowing anything about it.
 *
 * In the backend no content language is set: editors see and save the
 * original values; translations go explicitly through
 * translationsFor() / saveTranslations().
 *
 * Without the multilingual plugin the translator is empty — then this
 * trait does not change the model's behaviour at all.
 */
trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        // Permanently deleted: remove translations too. Soft-deleted models
        // keep them, so restoring loses nothing.
        static::deleted(function ($model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
            if (! $soft) {
                app(ContentTranslator::class)->forget($model);
            }
        });
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (! is_string($key) || ! in_array($key, $this->translatable ?? [], true)) {
            return $value;
        }

        $locales = app(Locales::class);
        if ($locales->content() === null) {
            return $value;
        }

        $translator = app(ContentTranslator::class);
        if (! $translator->enabled() || ! $this->exists) {
            return $value;
        }

        foreach ($locales->contentChain() as $locale) {
            $translated = $translator->get($this, $key, $locale);

            if ($translated !== null && $translated !== '' && $translated !== []) {
                return $this->castTranslated($key, $translated);
            }
        }

        return $value;
    }

    /** Bring a translation into the same shape as the original value (e.g. JSON → array). */
    protected function castTranslated(string $key, mixed $value): mixed
    {
        if (is_string($value) && $this->hasCast($key, ['array', 'json', 'object', 'collection', 'encrypted:array'])) {
            $decoded = json_decode($value, true);

            return $decoded ?? $value;
        }

        return $value;
    }

    /**
     * Find a model by its slug — in the frontend also by the translated slug
     * of the current content language. The model found is remembered as the
     * "current" one, so the language menu and hreflang can find its
     * counterparts in the other languages.
     */
    public static function findByTranslatedSlug(Builder $query, string $slug, string $column = 'slug'): ?static
    {
        $locales = app(Locales::class);
        $translator = app(ContentTranslator::class);
        $model = null;

        if ($locales->content() !== null && $translator->enabled()) {
            foreach ($locales->contentChain() as $locale) {
                $id = $translator->findBySlug(static::class, $slug, $locale);
                if ($id !== null) {
                    $model = (clone $query)->whereKey($id)->first();
                    if ($model) {
                        break;
                    }
                }
            }
        }

        // The original slug works in every language — /en/{original-slug} then shows
        // the English content if available, otherwise the original
        $model ??= (clone $query)->where($column, $slug)->first();

        if ($model) {
            $locales->setCurrent($model);
        }

        return $model;
    }

    /**
     * Routes like /category/{category:slug} also find the model by the
     * translated slug of the current language.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === 'slug' && is_string($value)) {
            return static::findByTranslatedSlug(static::query(), $value);
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /** Translated values for an editor. @return array<string, mixed> */
    public function translationsFor(string $locale): array
    {
        $values = app(ContentTranslator::class)->all($this, $locale);

        return array_intersect_key($values, array_flip($this->translatableFields()));
    }

    /**
     * Save translations; only fields from $translatable.
     *
     * $reviewed: someone deliberately saved this version — a version copied
     * from another language then counts as translated. Intermediate saving
     * of blocks in the editor passes false.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveTranslations(string $locale, array $values, bool $reviewed = true): void
    {
        $values = array_intersect_key($values, array_flip($this->translatableFields()));

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = json_encode($value);
            }
        }

        // Back up the language's previous state as a revision (models with HasRevisions)
        if (method_exists($this, 'snapshotTranslation')) {
            $this->snapshotTranslation($locale, $values);
        }

        $translator = app(ContentTranslator::class);
        $translator->save($this, $locale, $values);

        if ($reviewed) {
            $translator->clearDraft($this, $locale);
        }
        $this->translationDraftCache = null;
    }

    /** @var array<string, string>|null locale => source locale, loaded once per model */
    protected ?array $translationDraftCache = null;

    /** Which language was this version copied from (not yet saved)? */
    public function translationDraftOf(string $locale): ?string
    {
        return $this->translationDrafts()[$locale] ?? null;
    }

    /** @return array<string, string> locale => source locale */
    public function translationDrafts(): array
    {
        return $this->translationDraftCache ??= app(ContentTranslator::class)->draftsOf($this);
    }

    /** @return list<string> */
    public function translatedLocales(): array
    {
        return app(ContentTranslator::class)->locales($this);
    }

    /**
     * Also search the translations of the current content language (and the
     * default language as fallback) — inside an OR group:
     *
     *   ->where(fn ($q) => $q->orWhere('title', 'LIKE', $p)->orWhereTranslation(['title'], $p))
     *
     * Outside a translation (backend, base language) nothing changes.
     *
     * @param  list<string>  $fields
     */
    public function scopeOrWhereTranslation(Builder $query, array $fields, string $pattern): Builder
    {
        $locales = app(Locales::class)->contentChain();

        if ($locales === [] || $fields === []) {
            return $query;
        }

        $ids = app(ContentTranslator::class)->search(static::class, $fields, $pattern, $locales);

        return $ids ? $query->orWhereIn($this->getQualifiedKeyName(), $ids) : $query;
    }

    /**
     * Original value without translation — for places that always need the
     * base value (e.g. placeholders in the translation editor).
     */
    public function getOriginalValue(string $key): mixed
    {
        return parent::getAttribute($key);
    }
}
