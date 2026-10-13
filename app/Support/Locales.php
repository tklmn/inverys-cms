<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of installed languages.
 *
 * The core ships with English only. Further languages come as language packs
 * (plugins) that register here via register(), contributing their folder of
 * translation files. If a text is missing from a pack, Laravel falls back to
 * English — "fallback is always English".
 *
 * Two languages must be kept apart:
 *   - Interface language (App::getLocale()): the language buttons and
 *     messages appear in — configurable per user.
 *   - Content language (content()): the language pages, posts and menus are
 *     delivered in. Only set in the frontend, by the multilingual plugin.
 * An editor with an English interface may still edit German content.
 */
final class Locales
{
    /** @var array<string, array{name: string, native: string}> */
    private array $locales = [
        'en' => ['name' => 'English', 'native' => 'English'],
    ];

    /** The visitor's language in the frontend (set by the multilingual plugin); null in the backend */
    private ?string $visitor = null;

    /** The translatable model currently shown in the frontend (for the language menu, hreflang) */
    private ?object $current = null;

    private ?string $defaultCache = null;

    private ?string $baseCache = null;

    /**
     * Register a language. $langPath contains a subfolder per language (de/*.php)
     * and/or JSON files (de.json), like the core's lang/ folder.
     */
    public function register(string $code, string $name, string $native, ?string $langPath = null): void
    {
        $this->locales[$code] = ['name' => $name, 'native' => $native];

        if ($langPath && is_dir($langPath)) {
            $loader = app('translator')->getLoader();
            $loader->addPath($langPath);
            $loader->addJsonPath($langPath);
        }

        $this->defaultCache = null;
        $this->baseCache = null;
    }

    /** @return array<string, array{name: string, native: string}> */
    public function all(): array
    {
        return $this->locales;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->locales);
    }

    public function has(?string $code): bool
    {
        return $code !== null && isset($this->locales[$code]);
    }

    /** More than one language installed? Only then do editors show a language picker. */
    public function isMultilingual(): bool
    {
        return count($this->locales) > 1;
    }

    public function name(string $code): string
    {
        return $this->locales[$code]['name'] ?? strtoupper($code);
    }

    public function native(string $code): string
    {
        return $this->locales[$code]['native'] ?? strtoupper($code);
    }

    /**
     * Default language of the installation ("language" setting), limited
     * to installed languages. Without a matching pack: English.
     */
    public function default(): string
    {
        if ($this->defaultCache !== null) {
            return $this->defaultCache;
        }

        $value = null;

        try {
            if (Schema::hasTable('settings')) {
                $value = Setting::where('key', 'language')->value('value');
            }
        } catch (\Throwable) {
            // No settings exist yet during installation/migration
        }

        return $this->defaultCache = $this->has($value) ? $value : 'en';
    }

    /**
     * Language of the content in the core tables (pages, posts …). The
     * multilingual plugin records which language it was maintained in;
     * without the plugin it is the default language.
     */
    public function base(): string
    {
        if ($this->baseCache !== null) {
            return $this->baseCache;
        }

        $base = null;

        try {
            if (Schema::hasTable('settings')) {
                $base = Setting::where('key', 'ml.base_locale')->value('value');
            }
        } catch (\Throwable) {
            //
        }

        return $this->baseCache = $this->has($base) ? $base : $this->default();
    }

    /** Re-read after the settings have changed */
    public function flush(): void
    {
        $this->defaultCache = null;
        $this->baseCache = null;
    }

    /**
     * The language content must be translated into — null if no translation
     * is needed (backend, or the visitor reads the base language).
     */
    public function content(): ?string
    {
        return $this->visitor !== null && $this->visitor !== $this->base() ? $this->visitor : null;
    }

    /** Language of the frontend visitor; null in the backend. */
    public function visitor(): ?string
    {
        return $this->visitor;
    }

    public function setVisitor(?string $locale): void
    {
        $this->visitor = $this->has($locale) ? $locale : null;
    }

    /**
     * Which translations are candidates for the current content language,
     * in this order: requested language, then default language. The base
     * language is not in the list — it is the original value.
     *
     * @return list<string>
     */
    public function contentChain(?string $locale = null): array
    {
        $locale ??= $this->content();
        $base = $this->base();

        return array_values(array_unique(array_filter(
            [$locale, $this->default()],
            fn ($code) => $code !== null && $code !== $base,
        )));
    }

    /** @var array<class-string, callable(object, string): string> */
    private array $pathResolvers = [];

    /**
     * What is a model's path for a given (translated) slug? Each model type
     * registers this itself — pages in the core, posts in the blog.
     * The language menu and hreflang build the other languages' URLs from it.
     */
    public function pathUsing(string $modelClass, callable $resolver): void
    {
        $this->pathResolvers[$modelClass] = $resolver;
    }

    public function pathFor(object $model, string $slug): ?string
    {
        foreach ($this->pathResolvers as $class => $resolver) {
            if ($model instanceof $class) {
                return '/'.ltrim((string) $resolver($model, $slug), '/');
            }
        }

        return null;
    }

    public function setCurrent(?object $model): void
    {
        $this->current = $model;
    }

    public function current(): ?object
    {
        return $this->current;
    }
}
