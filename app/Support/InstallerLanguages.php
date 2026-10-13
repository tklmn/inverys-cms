<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\LanguageHelper;

/**
 * Installer languages.
 *
 * The installer runs before there is a database or any active plugins, so
 * language packs have not registered yet. It therefore looks for them itself:
 * English in the core (lang/en/install.php), every other language in a
 * plugin of type "language" (plugins/<package>/lang/<code>/install.php).
 *
 * The same lookup exists without Laravel in resources/views/install/bootstrap.php.
 */
final class InstallerLanguages
{
    /** @return array<string, array{path: string, native: string}> code => folder with language files, name */
    public static function all(): array
    {
        $found = [];

        foreach (glob(base_path('lang/*/install.php')) ?: [] as $file) {
            $code = basename(dirname($file));
            $found[$code] = ['path' => base_path('lang'), 'native' => $code === 'en' ? 'English' : LanguageHelper::getLanguageName($code)];
        }

        foreach (glob(base_path('plugins/*/plugin.json')) ?: [] as $manifest) {
            $meta = json_decode((string) file_get_contents($manifest), true);
            $code = $meta['locale'] ?? null;

            if (($meta['type'] ?? null) !== 'language' || ! is_string($code) || ! preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $code)) {
                continue;
            }

            $dir = dirname($manifest).'/lang';
            if (is_file("{$dir}/{$code}/install.php")) {
                $found[$code] = ['path' => $dir, 'native' => $meta['native'] ?? LanguageHelper::getLanguageName($code)];
            }
        }

        ksort($found);

        return $found;
    }

    public static function has(?string $locale): bool
    {
        return is_string($locale) && isset(self::all()[$locale]);
    }

    /**
     * Set the language for this request. To do so, the files of a language pack
     * are made known to the translator — the plugin itself does not run yet
     * during installation.
     */
    public static function use(?string $locale): bool
    {
        $languages = self::all();

        if (! is_string($locale) || ! isset($languages[$locale])) {
            return false;
        }

        $loader = app('translator')->getLoader();
        $loader->addPath($languages[$locale]['path']);
        $loader->addJsonPath($languages[$locale]['path']);
        app()->setLocale($locale);

        return true;
    }
}
