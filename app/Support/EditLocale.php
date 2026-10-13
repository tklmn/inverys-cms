<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Which language is an editor currently editing?
 *
 * null  = the original (base language) — the normal editor
 * 'en'  = a translation; the editor shows its values, shared fields
 *         (status, parent, images …) are locked
 *
 * Comes from ?lang=en (opening) or the hidden field _lang (saving).
 * Always null without the multilingual plugin.
 */
final class EditLocale
{
    public static function current(?Request $request = null): ?string
    {
        $request ??= request();
        $code = $request->input('_lang', $request->query('lang'));
        $locales = app(Locales::class);

        if (! is_string($code) || ! $locales->has($code) || $code === $locales->base()) {
            return null;
        }

        return app(ContentTranslator::class)->enabled() ? $code : null;
    }

    /** Form values for an editor (original or current translation). */
    public static function form(?Model $model): TranslationForm
    {
        return new TranslationForm($model, $model ? self::current() : null);
    }

    /** Is there anything to translate at all (plugin active, more than one language)? */
    public static function available(): bool
    {
        return app(ContentTranslator::class)->enabled() && app(Locales::class)->isMultilingual();
    }
}
