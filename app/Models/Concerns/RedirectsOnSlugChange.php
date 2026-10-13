<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Redirects;

/**
 * When the slug changes, the old address leads to the new one with 301
 * (Settings → SEO → Redirects). The address of a slug comes from
 * Locales::pathUsing() — pages in the core, posts and categories in the blog.
 *
 * Translated slugs (HasTranslations::saveTranslations) get a redirect that
 * applies in their language only.
 */
trait RedirectsOnSlugChange
{
    public static function bootRedirectsOnSlugChange(): void
    {
        // In "updated" the original still holds the old values
        static::updated(function ($model) {
            if ($model->wasChanged('slug')) {
                Redirects::recordSlugChange($model, (string) $model->getRawOriginal('slug'), (string) ($model->getAttributes()['slug'] ?? ''));
            }
        });
    }

    /** Called by HasTranslations::saveTranslations() around saving a translated slug */
    public function translatedSlugChanged(string $locale, string $oldSlug, string $newSlug): void
    {
        // The original slug keeps working in every language (and is then
        // redirected to the translated one) — no stored redirect needed
        if ($oldSlug === (string) $this->getOriginalValue('slug')) {
            return;
        }

        Redirects::recordSlugChange($this, $oldSlug, $newSlug, $locale);
    }
}
