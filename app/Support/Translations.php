<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Save a translation from an editor — the same for all models.
 *
 * Validates the translatable fields, builds the slug using the language's
 * transliteration rules (e.g. German umlauts become ae/oe/ue) and ensures the
 * URL is still free in that language. Empty fields remove the translation:
 * the original then applies again.
 */
final class Translations
{
    /**
     * @param  array<string, mixed>  $rules  rules for the translatable fields only
     * @param  string|null  $slugSource  field an empty slug is built from; null = no slug
     *
     * @throws ValidationException if the URL is taken (Laravel then redirects back)
     */
    public static function saveFromRequest(Request $request, Model $model, string $locale, array $rules, ?string $slugSource = 'title'): void
    {
        $data = $request->validate($rules);

        if ($slugSource !== null && array_key_exists('slug', $rules)) {
            $source = filled($data['slug'] ?? null) ? $data['slug'] : ($data[$slugSource] ?? null);
            $data['slug'] = filled($source) ? Str::slug((string) $source, '-', $locale) : null;

            if ($data['slug'] && self::slugTaken($model, $data['slug'], $locale)) {
                throw ValidationException::withMessages([
                    'slug' => __('translation.locales.slug_taken', ['language' => app(Locales::class)->native($locale)]),
                ]);
            }
        }

        $model->saveTranslations($locale, $data);
    }

    /**
     * Is the URL already taken in this language — as the translation of
     * another entry or as another entry's original URL?
     */
    public static function slugTaken(Model $model, string $slug, string $locale): bool
    {
        $owner = app(ContentTranslator::class)->findBySlug($model::class, $slug, $locale);

        if ($owner !== null && $owner !== (int) $model->getKey()) {
            return true;
        }

        return $model->newQuery()->where('slug', $slug)->whereKeyNot($model->getKey())->exists();
    }
}
