<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use Illuminate\Database\Eloquent\Model;

/**
 * Which translations does an entry have? For the badges in lists.
 *
 * Loaded once per request for each model type — so a list of 50 pages
 * runs one query, not 50.
 */
final class TranslationStatus
{
    /**
     * Language => translated? for all languages except the base, or null
     * if there is nothing to translate (plugin off, only one language).
     *
     * @return array<string, bool>|null
     */
    public static function for(Model $model): ?array
    {
        if (! EditLocale::available() || ! $model->exists || ! method_exists($model, 'translatedLocales')) {
            return null;
        }

        $locales = app(Locales::class);
        $done = self::map($model::class)[(int) $model->getKey()] ?? [];

        $status = [];
        foreach ($locales->codes() as $code) {
            if ($code !== $locales->base()) {
                $status[$code] = in_array($code, $done, true);
            }
        }

        return $status ?: null;
    }

    /**
     * Languages whose version was copied from another one and has not been
     * saved yet.
     *
     * @return list<string>
     */
    public static function drafts(Model $model): array
    {
        if (! EditLocale::available() || ! $model->exists) {
            return [];
        }

        $key = self::class.'.drafts.'.$model::class;

        if (! app()->bound($key)) {
            app()->instance($key, app(ContentTranslator::class)->draftsFor($model::class));
        }

        return app($key)[(int) $model->getKey()] ?? [];
    }

    /** @return array<int, list<string>> */
    private static function map(string $class): array
    {
        $key = self::class.'.'.$class;

        if (! app()->bound($key)) {
            app()->instance($key, app(ContentTranslator::class)->localesFor($class));
        }

        return app($key);
    }
}
