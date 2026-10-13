<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Values for an editor form — original or translation.
 *
 *   @php($i18n = \App\Support\EditLocale::form($post))
 *   <x-ui.input name="title" :value="$i18n->value('title')" :placeholder="$i18n->placeholder('title')" />
 *   <x-ui.select name="author_id" :disabled="$i18n->shared">
 *
 * For the original, value() returns the stored value (with old()); for a
 * translation, its value — empty if there is none yet; placeholder() then
 * shows the original. $shared locks fields that apply to all languages.
 */
final class TranslationForm
{
    public readonly bool $shared;

    /** @var array<string, mixed> */
    private array $values;

    public function __construct(
        public readonly ?Model $model,
        public readonly ?string $locale,
    ) {
        $this->shared = $locale !== null;
        $this->values = $locale && $model && method_exists($model, 'translationsFor')
            ? $model->translationsFor($locale)
            : [];
    }

    public function value(string $field, mixed $fallback = null): mixed
    {
        if ($this->locale !== null) {
            return old($field, $this->values[$field] ?? '');
        }

        return old($field, $this->original($field) ?? $fallback);
    }

    public function placeholder(string $field, ?string $fallback = null): ?string
    {
        return $this->locale !== null ? (string) ($this->original($field) ?? '') : $fallback;
    }

    public function original(string $field): mixed
    {
        if (! $this->model) {
            return null;
        }

        return method_exists($this->model, 'getOriginalValue')
            ? $this->model->getOriginalValue($field)
            : $this->model->getAttribute($field);
    }

    /** Raw translation value (e.g. blocks as JSON), without old() */
    public function translated(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }
}
