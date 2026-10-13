<?php

namespace App\Contracts;

/**
 * Language versions for the sitemap — registered by the multilingual plugin
 * (ExtensionRegistrar::registerSitemapLocalizer()).
 *
 * With at least two languages, /sitemap.xml becomes a sitemap index pointing
 * to one sitemap per language (/sitemap-{locale}.xml). Without a localizer or
 * with only one language it stays a plain sitemap.
 */
interface SitemapLocalizer
{
    /**
     * Languages that get their own sitemap (language codes, e.g. en, de).
     *
     * @return list<string>
     */
    public function locales(): array;

    /** Language without address prefix — target of hreflang="x-default" */
    public function defaultLocale(): string;

    /**
     * Address of a sitemap entry (loc, lastmod, model) in every language
     * in which it is actually reachable: [locale => absolute address].
     * Empty if the entry has no language versions (e.g. external address) —
     * it then only appears in the sitemap of the default language.
     *
     * @param  array{loc: string, lastmod?: mixed, model?: ?object}  $entry
     * @return array<string, string>
     */
    public function alternates(array $entry): array;
}
