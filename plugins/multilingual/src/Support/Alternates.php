<?php

namespace Plugins\Multilingual\Support;

use App\Contracts\ContentTranslator;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;

/**
 * URLs of the current page in all languages — for the language menu and hreflang.
 *
 * If a translatable model is known (page, post …), each language points
 * to its slug in that language: /about-us ↔ /fr/a-propos. Otherwise (search,
 * listings) the path stays the same and only the prefix changes.
 *
 * The same logic provides the language counterparts for /sitemap.xml (forSitemap()).
 */
final class Alternates
{
    /** @return array<string, array{url: string, native: string, current: bool}> */
    public static function all(): array
    {
        $locales = app(Locales::class);
        $visitor = $locales->visitor() ?? $locales->default();
        $urls = self::urls($locales->current());
        $out = [];

        foreach ($locales->all() as $code => $meta) {
            $out[$code] = [
                'url' => $urls[$code],
                'native' => $meta['native'],
                'current' => $code === $visitor,
            ];
        }

        return $out;
    }

    public static function defaultUrl(): string
    {
        $default = app(Locales::class)->default();

        return self::all()[$default]['url'] ?? self::root().'/';
    }

    /**
     * URLs of a sitemap entry in all languages: [language => URL].
     * Without a model (e.g. blog listing) the entry's path stays the same.
     *
     * Every language is reachable: if a translation is missing, the same
     * fallback chain as in the frontend applies (default language, then
     * original) — the original slug opens the content in every language (see
     * HasTranslations::findByTranslatedSlug()).
     *
     * Empty if only one language is installed or the URL does not belong
     * to this website.
     *
     * @param  array{loc: string, model?: ?object}  $entry
     * @return array<string, string>
     */
    public static function forSitemap(array $entry): array
    {
        if (! app(Locales::class)->isMultilingual()) {
            return [];
        }

        $root = self::root();
        $loc = (string) ($entry['loc'] ?? '');

        if ($loc !== $root && ! str_starts_with($loc, $root.'/')) {
            return [];
        }

        $path = '/'.trim((string) parse_url(substr($loc, strlen($root)) ?: '/', PHP_URL_PATH), '/');
        $model = ($entry['model'] ?? null) instanceof Model ? $entry['model'] : null;

        return self::urls($model, $path);
    }

    /**
     * URL per language. $path replaces the path of the current request when
     * there is no model (or no path is registered for its model).
     *
     * @return array<string, string>
     */
    private static function urls(?Model $model, ?string $path = null): array
    {
        $out = [];

        foreach (app(Locales::class)->codes() as $code) {
            $prefix = self::prefix($code);
            $localPath = self::pathFor($model, $code, $path);

            // Home page of a language: /de instead of /de/ — otherwise the web
            // server redirects URLs with a trailing slash via 301
            $out[$code] = self::root().($prefix !== '' && $localPath === '/' ? $prefix : $prefix.$localPath);
        }

        return $out;
    }

    private static function pathFor(?Model $model, string $locale, ?string $fallback = null): string
    {
        if ($model) {
            $path = app(Locales::class)->pathFor($model, self::slugFor($model, $locale));

            if ($path !== null) {
                return $path;
            }
        }

        // Without a model: current path without language prefix
        $path = '/'.trim($fallback ?? request()->getPathInfo(), '/');

        return $path === '/' ? '/' : $path;
    }

    /** Slug in one language — with the same fallback chain as in the frontend. */
    private static function slugFor(Model $model, string $locale): string
    {
        $original = method_exists($model, 'getOriginalValue') ? $model->getOriginalValue('slug') : $model->getAttribute('slug');
        $translator = app(ContentTranslator::class);

        foreach (app(Locales::class)->contentChain($locale) as $code) {
            $slug = $translator->get($model, 'slug', $code);
            if ($slug !== null && $slug !== '') {
                return (string) $slug;
            }
        }

        return (string) $original;
    }

    private static function prefix(string $locale): string
    {
        return $locale === app(Locales::class)->default() ? '' : '/'.$locale;
    }

    private static function root(): string
    {
        $request = request();

        return rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
    }
}
