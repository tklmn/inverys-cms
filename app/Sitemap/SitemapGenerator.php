<?php

namespace App\Sitemap;

use App\Contracts\ExtensionRegistrar;
use App\Contracts\SitemapLocalizer;
use App\Contracts\SitemapProvider;
use App\Support\Seo\LanguageCodes;
use App\Support\SeoSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * Builds the sitemap from all registered sitemap providers (core: pages,
 * plugins: e.g. blog posts).
 *
 * One language: /sitemap.xml is a plain list (<urlset>).
 * Several languages (the multilingual plugin registers a SitemapLocalizer):
 * /sitemap.xml is an index (<sitemapindex>) pointing to /sitemap-{locale}.xml.
 * Each language sitemap lists the URLs of that language (translated
 * slugs) with all <xhtml:link rel="alternate" hreflang="…">.
 *
 * The finished XML is cached (duration: Settings → SEO), per base URL,
 * because all URLs are absolute. forget() discards all variants at once.
 */
class SitemapGenerator
{
    private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const NS_XHTML = 'http://www.w3.org/1999/xhtml';

    private const NS_IMAGE = 'http://www.google.com/schemas/sitemap-image/1.1';

    /** Counter in the cache: part of every key, incrementing it discards everything */
    private const VERSION_KEY = 'sitemap.version';

    public function __construct(private readonly ExtensionRegistrar $extensions) {}

    public function enabled(): bool
    {
        return SeoSettings::sitemapEnabled();
    }

    /**
     * Languages with their own sitemap — empty as long as there is no localizer
     * or only one language (then: plain sitemap).
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $locales = $this->localizer()?->locales() ?? [];

        return count($locales) > 1 ? array_values($locales) : [];
    }

    public function isIndex(): bool
    {
        return $this->locales() !== [];
    }

    /** /sitemap.xml: index (multilingual) or plain list */
    public function xml(): string
    {
        return $this->cached('index', fn () => $this->isIndex() ? $this->renderIndex() : $this->renderUrlset($this->urls()));
    }

    /** /sitemap-{locale}.xml — null if this language sitemap does not exist */
    public function xmlFor(string $locale): ?string
    {
        if (! in_array($locale, $this->locales(), true)) {
            return null;
        }

        return $this->cached('locale-'.$locale, fn () => $this->renderUrlset($this->urls($locale)));
    }

    /** Discard all variants — after saving the SEO settings */
    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    /**
     * Providers that can be switched off on the settings page.
     *
     * @return array<string, string> key => translation key
     */
    public function toggleableProviders(): array
    {
        $out = [];

        foreach ($this->extensions->getSitemapProviders() as $provider) {
            if (($label = $provider->label()) !== null) {
                $out[$provider->key()] = $label;
            }
        }

        return $out;
    }

    /**
     * <url> entries: loc, lastmod (W3C date), alternates [hreflang => URL]
     * and images (absolute URLs for <image:image>).
     * Without $locale: all URLs, without language links. With $locale: the
     * URLs of that language including links to the other languages and
     * x-default — only languages that really have a translation. Duplicate
     * URLs appear only once.
     *
     * @return list<array{loc: string, lastmod: ?string, alternates: array<string, string>, images: list<string>}>
     */
    public function urls(?string $locale = null): array
    {
        $localizer = $locale !== null ? $this->localizer() : null;
        $default = $localizer?->defaultLocale();
        $urls = [];

        foreach ($this->entries() as $entry) {
            $lastmod = self::w3cDate($entry['lastmod'] ?? null);
            $images = self::images($entry['images'] ?? []);
            $alternates = $localizer ? $this->alternatesFor($localizer, $entry) : [];

            if ($alternates === []) {
                // Without language versions only in the plain sitemap or the default language
                if ($locale === null || $locale === $default) {
                    $urls[$entry['loc']] ??= ['loc' => $entry['loc'], 'lastmod' => $lastmod, 'alternates' => [], 'images' => $images];
                }

                continue;
            }

            // Not available in this language (no translation): leave out —
            // the fallback address would only repeat the original
            if (! isset($alternates[$locale])) {
                continue;
            }

            // A translation saved later than the entry itself is the last modification
            if ($localizer instanceof SitemapLocalizer && method_exists($localizer, 'lastModified')) {
                $translated = self::w3cDate($localizer->lastModified($entry, $locale));
                if ($translated !== null && ($lastmod === null || strcmp($translated, $lastmod) > 0)) {
                    $lastmod = $translated;
                }
            }

            $loc = $alternates[$locale];

            // Only one language really has the content: no language links
            if (count($alternates) > 1) {
                $alternates['x-default'] = $alternates[$default] ?? reset($alternates);
            } else {
                $alternates = [];
            }

            $urls[$loc] ??= ['loc' => $loc, 'lastmod' => $lastmod, 'alternates' => $alternates, 'images' => $images];
        }

        return array_values($urls);
    }

    /**
     * Images of an entry for <image:image>: absolute http(s) addresses, no duplicates.
     *
     * @return list<string>
     */
    private static function images(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        $out = [];
        foreach ($images as $image) {
            if (is_string($image) && preg_match('#^https?://#i', $image)) {
                $out[$image] = true;
            }
        }

        return array_keys($out);
    }

    private function localizer(): ?SitemapLocalizer
    {
        return $this->extensions->getSitemapLocalizer();
    }

    private function cached(string $variant, callable $build): string
    {
        $minutes = SeoSettings::sitemapCacheMinutes();

        if ($minutes === 0) {
            return $build();
        }

        $key = 'sitemap:'.(int) Cache::get(self::VERSION_KEY, 0).':'.sha1(url('/')).':'.$variant;

        return Cache::remember($key, $minutes * 60, $build);
    }

    private function renderIndex(): string
    {
        $xml = $this->startDocument('sitemapindex', false);

        foreach ($this->locales() as $locale) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', route('sitemap.locale', $locale));
            $xml->endElement();
        }

        return $this->endDocument($xml);
    }

    /** @param list<array{loc: string, lastmod: ?string, alternates: array<string, string>, images: list<string>}> $urls */
    private function renderUrlset(array $urls): string
    {
        $multilingual = collect($urls)->contains(fn ($url) => $url['alternates'] !== []);
        $withImages = collect($urls)->contains(fn ($url) => ($url['images'] ?? []) !== []);
        $xml = $this->startDocument('urlset', $multilingual, $withImages);

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url['loc']);

            if ($url['lastmod'] !== null) {
                $xml->writeElement('lastmod', $url['lastmod']);
            }

            foreach ($url['alternates'] as $hreflang => $href) {
                $xml->startElement('xhtml:link');
                $xml->writeAttribute('rel', 'alternate');
                // BCP 47 as in the <head>: pt-BR, not pt_BR
                $xml->writeAttribute('hreflang', LanguageCodes::hreflang((string) $hreflang));
                $xml->writeAttribute('href', $href);
                $xml->endElement();
            }

            foreach ($url['images'] ?? [] as $image) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', $image);
                $xml->endElement();
            }

            $xml->endElement();
        }

        return $this->endDocument($xml);
    }

    private function startDocument(string $root, bool $xhtml, bool $images = false): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement($root);
        $xml->writeAttribute('xmlns', self::NS_SITEMAP);
        if ($xhtml) {
            $xml->writeAttribute('xmlns:xhtml', self::NS_XHTML);
        }
        if ($images) {
            $xml->writeAttribute('xmlns:image', self::NS_IMAGE);
        }

        return $xml;
    }

    private function endDocument(XMLWriter $xml): string
    {
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Valid entries of the enabled providers. A faulty provider
     * (e.g. a plugin whose table is missing) must not break the
     * sitemap.
     *
     * @return iterable<array{loc: string, lastmod?: mixed, model?: ?object}>
     */
    private function entries(): iterable
    {
        foreach ($this->extensions->getSitemapProviders() as $provider) {
            if (! $this->includes($provider)) {
                continue;
            }

            try {
                // Read completely so errors occur here and not in the middle of the XML
                $entries = $provider->entries();
                $entries = is_array($entries) ? $entries : iterator_to_array($entries, false);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ($entries as $entry) {
                if (is_array($entry) && is_string($entry['loc'] ?? null) && $entry['loc'] !== '') {
                    yield $entry;
                }
            }
        }
    }

    /** Providers without a label (pages) are always included, the others per setting */
    private function includes(SitemapProvider $provider): bool
    {
        return $provider->label() === null || SeoSettings::sitemapIncludes($provider->key());
    }

    /** @return array<string, string> */
    private function alternatesFor(SitemapLocalizer $localizer, array $entry): array
    {
        try {
            return array_filter($localizer->alternates($entry), fn ($href) => is_string($href) && $href !== '');
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** W3C date (ISO 8601 with time zone) — null for a missing or unreadable value */
    private static function w3cDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        return $date->toAtomString();
    }
}
