<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Contracts\ExtensionRegistrar;
use App\Support\Locales;
use App\Support\SeoSettings;

/**
 * What the current frontend page tells search engines and social networks.
 *
 * Controllers fill it (pages, blog, search …); one core head hook
 * (frontend.partials.seo-head → SeoHead) turns it into description,
 * canonical, Open Graph, Twitter and JSON-LD tags. Themes therefore no
 * longer print these tags themselves.
 *
 * Without a controller filling it (plugin pages, custom routes) the head
 * still gets a self-referencing canonical and basic Open Graph tags; title
 * and description then come from the page's own <title> and description.
 *
 * Lives for one request (scoped binding, reset on every matched route).
 */
final class SeoContext
{
    public const TYPE_WEBSITE = 'website';

    public const TYPE_ARTICLE = 'article';

    /** Title of the content without the site name (og:title, JSON-LD) */
    public ?string $title = null;

    /** Plain-text description — already shortened (see Descriptions) */
    public ?string $description = null;

    /** Social title/description if they differ from title/description */
    public ?string $socialTitle = null;

    public ?string $socialDescription = null;

    /** Explicit canonical address (page field) — otherwise self-referencing */
    public ?string $canonical = null;

    /** Robots directives of this page ("noindex, follow" …) */
    public ?string $robots = null;

    public ?string $author = null;

    /** og:type: website or article */
    public string $type = self::TYPE_WEBSITE;

    /** JSON-LD type of the main entity: WebPage, CollectionPage, BlogPosting or null */
    public ?string $schemaType = null;

    /** Image for Open Graph and Twitter: path in public/ or absolute URL */
    public ?string $image = null;

    public ?string $imageAlt = null;

    /** article:* for posts: published_time, modified_time, author, section (Carbon or string) */
    public array $article = [];

    /** @var list<array{name: string, url: string}> breadcrumb trail without the home page */
    public array $breadcrumbs = [];

    /** Current page number of a paginated list (1 = first page) */
    public int $page = 1;

    /** The website's home page (WebSite and Organization in JSON-LD, verification tags) */
    public bool $home = false;

    /** Has a controller described the page? */
    public bool $filled = false;

    /** Status of the response the head is injected into (InjectPluginHooks) */
    private ?int $status = null;

    /** The page's <head> as rendered by the theme, before the head hooks */
    private string $documentHead = '';

    /** @var array<string, string>|null locale => URL, computed once */
    private ?array $alternates = null;

    /**
     * Describe the page. Unknown keys are ignored; only non-empty values are taken.
     *
     * @param  array<string, mixed>  $values
     */
    public function fill(array $values): self
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key) && ! in_array($key, ['status', 'documentHead', 'alternates'], true) && $value !== null) {
                $this->{$key} = $value;
            }
        }

        $this->filled = true;

        return $this;
    }

    /** Set by InjectPluginHooks before the head hooks are rendered */
    public function respond(int $status, string $documentHead): void
    {
        $this->status = $status;
        $this->documentHead = $documentHead;
    }

    public function documentHead(): string
    {
        return $this->documentHead;
    }

    /**
     * Only successful pages get SEO tags — an error page must not claim a
     * canonical address or language versions.
     */
    public function indexableResponse(): bool
    {
        return $this->status === null || $this->status === 200;
    }

    public function isNoindex(): bool
    {
        return $this->robots !== null && preg_match('/\b(noindex|none)\b/i', $this->robots) === 1;
    }

    /** Does the canonical address point to another page (field "Canonical URL")? */
    public function hasForeignCanonical(): bool
    {
        if ($this->canonical === null || $this->canonical === '') {
            return false;
        }

        return self::normalizeUrl($this->canonical) !== self::normalizeUrl($this->selfUrl());
    }

    /**
     * Canonical address: the explicit one, otherwise the page itself — in
     * the language whose content is actually shown (a fallback language
     * version points to the original) — without query, except ?page=N for N > 1.
     */
    public function canonicalUrl(): string
    {
        if ($this->canonical !== null && $this->canonical !== '') {
            return Urls::absolute($this->canonical);
        }

        return $this->selfUrl();
    }

    /** This page's own address (language version that is really shown, no query) */
    public function selfUrl(): string
    {
        $locales = app(Locales::class);
        $alternates = $this->alternates();

        if ($alternates !== []) {
            $visitor = $locales->visitor() ?? $locales->default();
            $url = $alternates[$visitor]
                ?? $alternates[$locales->default()]
                ?? $alternates[$locales->base()]
                ?? reset($alternates);
        } else {
            $url = url(request()->getPathInfo());
        }

        $url = self::normalizeUrl($url);

        return $this->page > 1 ? $url.'?page='.$this->page : $url;
    }

    /**
     * Addresses of this page in the languages that really have it:
     * [locale => URL]. A page without a translation in a language is not
     * listed there (its fallback URL would be duplicate content). Lists
     * without a model (blog overview, search) exist in every language.
     * Empty with only one language.
     *
     * @return array<string, string>
     */
    public function alternates(): array
    {
        if ($this->alternates !== null) {
            return $this->alternates;
        }

        $locales = app(Locales::class);
        $localizer = app(ExtensionRegistrar::class)->getSitemapLocalizer();

        if ($localizer === null || ! $locales->isMultilingual()) {
            return $this->alternates = [];
        }

        $request = request();
        $root = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        $path = '/'.ltrim($request->getPathInfo(), '/');

        try {
            $urls = $localizer->alternates(['loc' => $root.$path, 'model' => $locales->current()]);
        } catch (\Throwable $e) {
            report($e);
            $urls = [];
        }

        return $this->alternates = array_filter($urls, fn ($url) => is_string($url) && $url !== '');
    }

    /**
     * hreflang and og:locale:alternate only where they are true: a
     * successful, indexable page with its own canonical address, available
     * in at least two languages, requested in one of them.
     */
    public function emitsAlternates(): bool
    {
        $alternates = $this->alternates();
        $locales = app(Locales::class);

        return $this->indexableResponse()
            && ! $this->isNoindex()
            && ! $this->hasForeignCanonical()
            && count($alternates) > 1
            && isset($alternates[$locales->visitor() ?? $locales->default()]);
    }

    /** Target of hreflang="x-default": the default language, otherwise the original */
    public function defaultAlternate(): ?string
    {
        $locales = app(Locales::class);
        $alternates = $this->alternates();

        return $alternates[$locales->default()] ?? $alternates[$locales->base()] ?? (reset($alternates) ?: null);
    }

    /** Language of the content shown on this page */
    public function contentLocale(): string
    {
        $locales = app(Locales::class);
        $alternates = $this->alternates();
        $visitor = $locales->visitor() ?? $locales->default();

        if ($alternates === [] || isset($alternates[$visitor])) {
            return $visitor;
        }

        return isset($alternates[$locales->default()]) ? $locales->default() : $locales->base();
    }

    /** Is the page a list beyond its first page? */
    public function isPaginated(): bool
    {
        return $this->page > 1;
    }

    /** Robots directives to print (null while search engines are blocked: the robots-meta hook says noindex then) */
    public function robotsDirectives(): ?string
    {
        $robots = trim((string) $this->robots);

        return $robots === '' || ! SeoSettings::allowIndexing() ? null : $robots;
    }

    /** Home URL with a trailing slash ("https://example.org/") */
    public static function normalizeUrl(string $url): string
    {
        return (string) preg_replace('#^(https?://[^/?\#]+)(?=[?\#]|$)#i', '$1/', $url);
    }
}
