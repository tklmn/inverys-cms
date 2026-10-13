<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use App\Models\Setting;

/**
 * Translated values of settings (multilingual plugin).
 *
 * Stored on the setting record in the "value" field — the same mechanism
 * as for pages. Which settings are translatable is listed in the
 * TranslatableSettings registry; theme texts use this class via
 * ThemeTranslations.
 */
final class SettingTranslations
{
    /** Value in the current content language (chain: language → default language) or null */
    public static function lookup(string $key): ?string
    {
        if (! self::translating()) {
            return null;
        }

        $setting = self::row($key);
        if (! $setting) {
            return null;
        }

        $translator = app(ContentTranslator::class);

        foreach (app(Locales::class)->contentChain() as $locale) {
            $translated = $translator->get($setting, 'value', $locale);
            if ($translated !== null && $translated !== '') {
                return (string) $translated;
            }
        }

        return null;
    }

    /** Raw translation value for an editor */
    public static function translated(string $key, string $locale): ?string
    {
        $setting = self::row($key);
        $value = $setting ? app(ContentTranslator::class)->get($setting, 'value', $locale) : null;

        return $value === null ? null : (string) $value;
    }

    /**
     * Save a translation. If the setting does not exist yet (a default value
     * applies), it is created with $originalValue — a translation needs a
     * record to attach to.
     */
    public static function save(string $key, string $locale, ?string $value, string $originalValue, string $type = 'string'): void
    {
        $setting = self::row($key) ?? Setting::create(['key' => $key, 'value' => $originalValue, 'type' => $type]);
        self::rows()[$key] = $setting;

        app(ContentTranslator::class)->save($setting, $locale, ['value' => $value === '' ? null : $value]);
    }

    /** Is the frontend currently translating (visitor in a language other than the base)? */
    public static function translating(): bool
    {
        return app(Locales::class)->content() !== null && app(ContentTranslator::class)->enabled();
    }

    private static function row(string $key): ?Setting
    {
        $rows = self::rows();

        if (! $rows->offsetExists($key)) {
            $rows[$key] = Setting::where('key', $key)->first();
        }

        return $rows[$key];
    }

    /**
     * Cache per application, not static — otherwise it would survive an
     * app restart (tests, Octane) with stale records.
     */
    private static function rows(): \ArrayObject
    {
        $app = app();

        if (! $app->bound(self::class.'.rows')) {
            $app->instance(self::class.'.rows', new \ArrayObject);
        }

        return $app->make(self::class.'.rows');
    }
}
