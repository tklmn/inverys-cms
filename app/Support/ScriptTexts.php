<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Language and texts for the backend scripts.
 *
 * The layout outputs them as a JSON block (id="ui-i18n"), read by
 * resources/js/backend/ui/i18n.js. That way no script needs its own texts,
 * and buttons, notices and the editor appear in the user's language.
 */
final class ScriptTexts
{
    /** @return array{locale: string, texts: array<string, string>, tinymce: ?array{code: string, url: string}} */
    public static function backend(): array
    {
        return [
            'locale' => app()->getLocale(),
            'texts' => (array) __('translation.script'),
            'tinymce' => self::tinymce(app()->getLocale()),
        ];
    }

    /**
     * The editor's language file (public/vendor/tinymce/langs, see copy-tinymce.cjs).
     * Files are named after their code: de.js, fr-FR.js … — first the exact
     * language, then the first regional variant. English is built in.
     *
     * @return array{code: string, url: string}|null
     */
    public static function tinymce(string $locale): ?array
    {
        if ($locale === 'en') {
            return null;
        }

        $dir = public_path('vendor/tinymce/langs');
        $locale = str_replace('_', '-', $locale);
        $file = is_file("$dir/$locale.js") ? "$locale.js" : null;

        if ($file === null) {
            $matches = glob($dir.'/'.explode('-', $locale)[0].'-*.js') ?: [];
            $file = $matches ? basename($matches[0]) : null;
        }

        return $file ? ['code' => basename($file, '.js'), 'url' => asset('vendor/tinymce/langs/'.$file)] : null;
    }
}
