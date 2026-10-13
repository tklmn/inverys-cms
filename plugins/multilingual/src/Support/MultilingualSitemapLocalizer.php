<?php

namespace Plugins\Multilingual\Support;

use App\Contracts\SitemapLocalizer;
use App\Support\Locales;

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
}
