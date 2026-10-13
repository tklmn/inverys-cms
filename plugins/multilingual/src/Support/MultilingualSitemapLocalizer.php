<?php

namespace Plugins\Multilingual\Support;

use App\Contracts\ContentTranslator;
use App\Contracts\SitemapLocalizer;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Plugins\Multilingual\DatabaseContentTranslator;

/**
 * Sitemap per language: each installed language gets /sitemap-{language}.xml;
 * the URLs come from the same logic as the language menu and hreflang in the <head>.
 */
final class MultilingualSitemapLocalizer implements SitemapLocalizer
{
    public function __construct(private readonly Locales $locales) {}

    public function locales(): array
    {
        return $this->locales->codes();
    }

    public function defaultLocale(): string
    {
        return $this->locales->default();
    }

    public function alternates(array $entry): array
    {
        return Alternates::forSitemap($entry);
    }

    /**
     * When the entry's translation in this language was last saved — a
     * translation edited after the original moves its lastmod forward.
     */
    public function lastModified(array $entry, string $locale): ?string
    {
        $model = $entry['model'] ?? null;
        $translator = app(ContentTranslator::class);

        if (! $model instanceof Model || $locale === $this->locales->base() || ! $translator instanceof DatabaseContentTranslator) {
            return null;
        }

        return $translator->lastTranslated($model, $locale);
    }
}
