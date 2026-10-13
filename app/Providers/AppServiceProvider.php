<?php

namespace App\Providers;

use App\Contracts\CmsUserProvider;
use App\Contracts\ContentTranslator;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\MediaManager;
use App\Contracts\PageManager;
use App\Contracts\PermissionManager;
use App\Contracts\RouteRegistrar;
use App\Contracts\SettingRepository;
use App\Models\Page;
use App\Models\Setting;
use App\Search\Admin\MediaAdminSearch;
use App\Search\Admin\PageAdminSearch;
use App\Search\Admin\SettingsAdminSearch;
use App\Search\Admin\UserAdminSearch;
use App\Search\PageSearchProvider;
use App\Services\BlockRegistry;
use App\Services\BlockRenderer;
use App\Services\Cms\DefaultExtensionRegistrar;
use App\Services\Cms\DefaultRouteRegistrar;
use App\Services\Cms\EloquentMediaManager;
use App\Services\Cms\EloquentPageManager;
use App\Services\Cms\EloquentPermissionManager;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\Cms\EloquentUserProvider;
use App\Services\Cms\PageMediaUsageChecker;
use App\Services\CmsManager;
use App\Services\PluginManager;
use App\Services\ThemeManager;
use App\Services\TwoFactorService;
use App\Sitemap\PageSitemapProvider;
use App\Support\CspSources;
use App\Support\Locales;
use App\Support\NullContentTranslator;
use App\Support\PageRevisionPreview;
use App\Support\Revisionables;
use App\Support\ThemeViews;
use App\Support\TranslatableSettings;
use App\View\Components\DynamicLayout;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Revisionables::class);
        // Core services
        $this->app->singleton(TwoFactorService::class);
        // Additional CSP sources only apply to the one request
        $this->app->scoped(CspSources::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemeViews::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(BlockRenderer::class);

        // CMS contracts — the public API for plugins and themes
        $this->app->singleton(SettingRepository::class, EloquentSettingRepository::class);
        $this->app->singleton(PermissionManager::class, EloquentPermissionManager::class);
        $this->app->singleton(PageManager::class, EloquentPageManager::class);
        $this->app->singleton(MediaManager::class, EloquentMediaManager::class);
        $this->app->singleton(CmsUserProvider::class, EloquentUserProvider::class);
        $this->app->singleton(RouteRegistrar::class, DefaultRouteRegistrar::class);
        $this->app->singleton(ExtensionRegistrar::class, DefaultExtensionRegistrar::class);
        $this->app->singleton(CmsManager::class);

        // Languages: registry of installed languages (core: English only) and
        // translation store for content — empty until the
        // multilingual plugin binds a real implementation
        $this->app->singleton(Locales::class);
        $this->app->singleton(ContentTranslator::class, NullContentTranslator::class);

        // Path of a page for a slug — for the language counterparts
        $this->app->afterResolving(Locales::class, function ($locales) {
            $locales->pathUsing(Page::class, fn ($page, $slug) => $page->is_root ? '/' : '/'.$slug);
        });

        // Settings whose text visitors see — translatable per language.
        // Plus the bundled packages that don't use the registry themselves yet.
        $this->app->singleton(TranslatableSettings::class, function () {
            $settings = new TranslatableSettings;
            $settings->register('site_name', ['label' => 'translation.settings.site_name', 'group' => 'translation.settings.general']);

            foreach (config('cms.translatable_settings', []) as $key => $options) {
                $settings->register($key, $options);
            }

            return $settings;
        });
    }

    public function boot(): void
    {
        // Enforce password policy from security settings (with safe minimums)
        Password::defaults(function () {
            $minLength = max(8, (int) setting('security.password_min_length', '10'));
            $rule = Password::min($minLength);
            if (setting('security.password_require_uppercase', 'on') === 'on') {
                $rule = $rule->mixedCase();
            }
            if (setting('security.password_require_numbers', 'on') === 'on') {
                $rule = $rule->numbers();
            }
            if (setting('security.password_require_symbols', 'on') === 'on') {
                $rule = $rule->symbols();
            }

            return $rule;
        });

        // Behind a reverse proxy (TRUSTED_PROXIES): trust X-Forwarded-* only from
        // these addresses — otherwise Laravel does not detect HTTPS
        $proxies = trim((string) config('cms.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }

        if (config('cms.log_missing_translations')) {
            $this->logMissingTranslations();
        }

        // Register a custom Blade directive for checking permissions
        Blade::if('permission', function ($permission) {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        // Register the dynamic-layout component
        Blade::component('dynamic-layout', DynamicLayout::class);

        // CSP nonce for inline <script> tags: @cspNonce → nonce="<per-request value>"
        Blade::directive('cspNonce', fn () => "<?php echo 'nonce=\"'.csp_nonce().'\"'; ?>");

        // Text spot that can be edited directly in the editor preview: @pbField('title')
        Blade::directive('pbField', fn ($expression) => "<?php echo \\App\\Support\\EditorCanvas::field({$expression}); ?>");

        // Boot theme system
        try {
            app(ThemeManager::class)->boot();
        } catch (\Exception $e) {
            $this->logBootFailure('ThemeManager', $e);
        }

        // Register core extensions
        $extensions = app(ExtensionRegistrar::class);
        $extensions->registerSearchProvider(new PageSearchProvider);
        // Quick search in the backend (Ctrl/⌘ + K)
        $extensions->registerAdminSearchProvider(new SettingsAdminSearch);
        $extensions->registerAdminSearchProvider(new PageAdminSearch);
        $extensions->registerAdminSearchProvider(new MediaAdminSearch);
        $extensions->registerAdminSearchProvider(new UserAdminSearch);
        // Page revisions
        $extensions->registerRevisionable('page', [
            'model' => Page::class,
            'permission' => 'edit-page',
            'edit_route' => 'backend.pages.edit',
            'edit_params' => fn (Page $page) => ['page' => $page],
            'preview' => [PageRevisionPreview::class, 'render'],
        ]);
        $extensions->registerMediaUsageChecker(new PageMediaUsageChecker);
        $extensions->registerSitemapProvider(new PageSitemapProvider);
        // <meta name="robots" content="noindex, nofollow"> while search engines are blocked
        $extensions->registerHeadHook('frontend.partials.robots-meta');

        // Container bindings as read-through proxies to the extension registrar
        $this->app->bind('plugin.search_providers', fn () => $extensions->getSearchProviders());
        $this->app->bind('plugin.trash_tabs', fn () => $extensions->getTrashTabs());
        $this->app->bind('plugin.body_hooks', fn () => $extensions->getBodyHooks());
        $this->app->bind('plugin.head_hooks', fn () => $extensions->getHeadHooks());
        $this->app->bind('plugin.navigation_hooks', fn () => $extensions->getNavigationHooks());

        // Boot plugin system
        try {
            app(PluginManager::class)->boot();
        } catch (\Exception $e) {
            $this->logBootFailure('PluginManager', $e);
        }

        // Share navigation pages with the frontend nav (single cached query per request)
        View::composer(['frontend.layouts.navigation', 'theme::layouts.navigation'], function ($view) {
            static $menuPages = null;
            if ($menuPages === null) {
                $menuPages = Page::whereNull('parent_id')
                    ->where('is_active', true)
                    ->where('hide_in_menu', false)
                    ->orderBy('order')
                    ->with(['children' => function ($q) {
                        $q->where('is_active', true)
                            ->where('hide_in_menu', false)
                            ->orderBy('order');
                    }])
                    ->get();
            }
            $view->with('menuPages', $menuPages);
        });

        // Boot block registry
        try {
            app(BlockRegistry::class)->boot();
        } catch (\Exception $e) {
            $this->logBootFailure('BlockRegistry', $e);
        }

        // Load core settings (timezone, session)
        try {
            $timezone = Setting::where('key', 'timezone')->value('value');
            if ($timezone && in_array($timezone, timezone_identifiers_list())) {
                config(['app.timezone' => $timezone]);
                date_default_timezone_set($timezone);
            }

            // Apply session lifetime from security settings
            $sessionLifetime = setting('security.session_lifetime');
            if ($sessionLifetime !== null) {
                config(['session.lifetime' => max(5, (int) $sessionLifetime)]);
            }
        } catch (\Exception $e) {
            $this->logBootFailure('Settings', $e);
        }
    }

    /**
     * Report a subsystem boot failure. Before installation the tables are
     * necessarily missing — that would mean one warning per subsystem on every
     * installer request, so stay silent then.
     */
    private function logBootFailure(string $system, \Exception $e): void
    {
        if (app()->runningInConsole() || ! file_exists(storage_path('installed'))) {
            return;
        }

        Log::warning($system.' boot failed: '.$e->getMessage());
    }

    /**
     * Log texts without a translation (config cms.log_missing_translations).
     * English doesn't count: there the key is often the text itself.
     */
    protected function logMissingTranslations(): void
    {
        $seen = [];

        app('translator')->handleMissingKeysUsing(function (string $key, array $replace, ?string $locale) use (&$seen) {
            $locale ??= app()->getLocale();

            if ($locale !== 'en' && ! isset($seen[$locale.$key])) {
                $seen[$locale.$key] = true;
                $where = app()->runningInConsole() ? 'console' : request()->path();
                @file_put_contents(storage_path('logs/missing-translations.log'), "$locale\t$key\t$where\n", FILE_APPEND);
            }

            return $key;
        });
    }
}
