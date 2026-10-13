<?php

namespace Plugins\Multilingual\Support;

use App\Contracts\ContentTranslator;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Plugins\Multilingual\DatabaseContentTranslator;

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
     * URLs of a sitemap entry (or the current page) in the languages that
     * really have it: [language => URL]. Without a model (e.g. blog listing)
     * the entry's path stays the same and every language counts.
     *
     * With a model only its original language and the languages it has a
     * translation in are listed. The other languages can still be opened
     * (fallback chain: default language, then original — see
     * HasTranslations::findByTranslatedSlug()), but they only repeat the
     * original: no hreflang, no sitemap entry, canonical to the original.
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
        $urls = self::urls($model, $path);

        return $model !== null ? array_intersect_key($urls, array_flip(self::availableLocales($model))) : $urls;
    }

    /**
     * Languages in which a model has content of its own: the language of
     * the original plus every language with a translation.
     *
     * @return list<string>
     */
    public static function availableLocales(Model $model): array
    {
        $locales = app(Locales::class);
        $translator = app(ContentTranslator::class);

        $translated = $translator instanceof DatabaseContentTranslator
            ? $translator->availableLocales($model)
            : $translator->locales($model);

        return array_values(array_intersect($locales->codes(), array_merge([$locales->base()], $translated)));
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
