<?php

namespace Plugins\LanguageDe;

use App\Services\Plugins\PluginServiceProvider;
use App\Support\Locales;
use Illuminate\Console\Application;
use Plugins\LanguageDe\Console\DemoTranslationsCommand;

/**
 * German language pack.
 *
 * Registers "de" in the language registry and ships the German interface texts
 * (lang/de/*.php, lang/de.json). A missing text appears in English.
 *
 * Template for further languages: copy the folder, replace code, names and
 * texts — see README.md.
 */
class LanguageDeServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\LanguageDe');

        // Register early: before any text gets translated
        $this->app->make(Locales::class)->register(
            code: 'de',
            name: 'German',
            native: 'Deutsch',
            langPath: $this->getPluginPath().'/lang',
        );
    }

    public function boot(): void
    {
        // php artisan language-de:demo — add the German demo content later
        if ($this->app->runningInConsole()) {
            Application::starting(function ($artisan) {
                $artisan->resolve(DemoTranslationsCommand::class);
            });
        }
    }

    /**
     * Called by the installer after the demo content: German version of the
     * pages, posts, categories, media and theme texts (demo/de.json).
     */
    public function seedDemoTranslations(): void
    {
        (new DemoTranslations)->apply();
    }
}
