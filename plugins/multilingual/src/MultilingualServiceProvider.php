<?php

namespace Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Services\Plugins\PluginServiceProvider;
use App\Support\Locales;
use Illuminate\Console\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Plugins\Multilingual\Console\CopyLocaleCommand;
use Plugins\Multilingual\Http\Controllers\SettingsController;
use Plugins\Multilingual\Http\Controllers\TextsController;
use Plugins\Multilingual\Http\Middleware\DetectContentLocale;
use Plugins\Multilingual\Support\MultilingualSitemapLocalizer;
use Plugins\Multilingual\Support\Paths;

/**
 * Multilingual support.
 *
 * The plugin does not ship languages itself — those come as language packs
 * (e.g. language-de) via the core's language registry. This plugin makes
 * CONTENT multilingual:
 *
 *   - Translation store (ml_translations) instead of the empty core implementation
 *   - Language from the path: /en/about-us (default language without prefix)
 *   - Links automatically get the prefix of the current language
 *   - Language menu in the theme navigation, hreflang in the <head>
 *   - Language selection in the editors (?lang=en)
 */
class MultilingualServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\Multilingual');

        // Real store instead of NullContentTranslator
        $this->app->singleton(ContentTranslator::class, DatabaseContentTranslator::class);
    }

    public function boot(): void
    {
        $path = $this->getPluginPath();

        $this->registerPluginViews('multilingual', $path.'/views');
        $this->app->make('translator')->addNamespace('multilingual', $path.'/lang');

        // Before routing: detect the language from the path and strip the prefix
        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'prependMiddleware') && ! $kernel->hasMiddleware(DetectContentLocale::class)) {
            $kernel->prependMiddleware(DetectContentLocale::class);
        }

        $this->localizeGeneratedUrls();

        $this->routes()->backend('multilingual', function () {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('backend.multilingual.settings');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.multilingual.settings.update');
            Route::post('/copy', [SettingsController::class, 'copy'])->name('backend.multilingual.copy');
            Route::get('/texts', [TextsController::class, 'edit'])->name('backend.multilingual.texts');
            Route::put('/texts', [TextsController::class, 'update'])->name('backend.multilingual.texts.update');
        });

        // php artisan multilingual:copy de fr — prefill a language
        if ($this->app->runningInConsole()) {
            Application::starting(function ($artisan) {
                $artisan->resolve(CopyLocaleCommand::class);
            });
        }

        $this->extensions()->registerSettingsPrefix('ml.');
        $this->extensions()->registerNavigationHook('multilingual::frontend.switcher');
        $this->extensions()->registerHeadHook('multilingual::frontend.hreflang');

        // Sitemap per language with hreflang links (same logic as in the <head>)
        $this->extensions()->registerSitemapLocalizer(new MultilingualSitemapLocalizer($this->app->make(Locales::class)));

        $this->extensions()->registerSidebarItem([
            'route' => 'backend.multilingual.settings',
            'icon' => 'globe',
            'label' => 'multilingual::ml.title',
            'active' => 'backend.multilingual.*',
            'section' => 'system',
            'order' => 12,
            'permission' => 'multilingual-settings',
        ]);
    }

    /**
     * In the frontend, route('page.show', …) and url('/…') get the prefix of the
     * visitor's language. Backend, API and files stay unchanged.
     */
    protected function localizeGeneratedUrls(): void
    {
        $locales = $this->app->make(Locales::class);

        $this->app->make('url')->formatPathUsing(function (string $path) use ($locales) {
            $visitor = $locales->visitor();

            if ($visitor === null || $visitor === $locales->default()) {
                return $path;
            }

            $first = explode('/', trim($path, '/'), 2)[0];

            if ($first === $visitor || $locales->has($first) || ! Paths::isContent($first)) {
                return $path;
            }

            return '/'.$visitor.($path === '/' ? '' : $path);
        });
    }

    public function syncPermissions(): void
    {
        $this->permissions()->createPermission('multilingual-settings');
        $this->permissions()->assignToRole('admin', ['multilingual-settings']);
    }

    public function install(): void
    {
        if (! Schema::hasTable(DatabaseContentTranslator::TABLE)) {
            Schema::create(DatabaseContentTranslator::TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('translatable_type', 191);
                $table->unsignedBigInteger('translatable_id');
                $table->string('locale', 12);
                $table->string('field', 64);
                $table->longText('value')->nullable();
                $table->timestamps();

                $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'ml_translations_unique');
                $table->index(['translatable_type', 'locale', 'field'], 'ml_translations_lookup');
            });
        }

        // Record the base language: the existing content is in this language.
        // It does not change later, even if the default language
        // changes — otherwise originals would be mislabelled.
        if (! $this->settings()->has('ml.base_locale')) {
            $this->settings()->set('ml.base_locale', $this->app->make(Locales::class)->default(), 'string', 'Language of the original content');
        }

        $this->syncPermissions();
    }

    public function uninstall(): void
    {
        Schema::dropIfExists(DatabaseContentTranslator::TABLE);
        $this->settings()->delete('ml.base_locale');
    }
}
