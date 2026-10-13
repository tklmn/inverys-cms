<?php

namespace App\Contracts;

/**
 * Provides addresses for the XML sitemap (/sitemap.xml).
 *
 * The core registers the pages, plugins their own content (e.g. the blog
 * its posts) — via ExtensionRegistrar::registerSitemapProvider().
 */
interface SitemapProvider
{
    /**
     * Unique key (lowercase letters, digits, _ and -). The SEO settings page
     * stores under seo.sitemap_include_{key} whether the provider appears
     * in the sitemap.
     */
    public function key(): string;

    /**
     * Translation key for the toggle on the SEO settings page.
     * null: no toggle, the provider is always included.
     */
    public function label(): ?string;

    /**
     * Entries of the sitemap. Each entry is an array:
     *
     *   - loc:     absolute address (route()/url()), required
     *   - lastmod: last modification (DateTimeInterface or date string), optional
     *   - model:   the displayed, translatable model, optional — the multilingual
     *              plugin builds the addresses of the other languages from it
     *
     * Only return publicly reachable, indexable content (no drafts,
     * no scheduled posts, nothing with "noindex").
     *
     * @return iterable<array{loc: string, lastmod?: \DateTimeInterface|string|null, model?: object|null}>
     */
    public function entries(): iterable;
}
