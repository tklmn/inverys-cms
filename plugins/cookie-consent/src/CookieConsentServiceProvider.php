<?php

namespace Plugins\CookieConsent;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Route;

class CookieConsentServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        // Exempt cc_consent cookie from encryption so PHP can read the JS-set cookie
        $this->app->resolving(EncryptCookies::class, function (EncryptCookies $middleware) {
            $middleware->disableFor('cc_consent');
        });
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        $this->registerPluginViews('cookie-consent', $pluginPath.'/views');
        $this->app['translator']->addNamespace('cookie-consent', $pluginPath.'/lang');

        // Frontend hooks
        $this->extensions()->registerBodyHook('cookie-consent::banner');
        $this->extensions()->registerBodyHook('cookie-consent::body-scripts');
        $this->extensions()->registerHeadHook('cookie-consent::head-scripts');

        $this->extensions()->registerSettingsPrefix('cc.');
        $this->extensions()->registerSettingsPrefix('cookie_consent.');

        // Texts visitors see — translatable per language (Multilingual plugin)
        $this->extensions()->registerTranslatableSetting('cc.banner_text', [
            'label' => 'cookie-consent::cc.banner_text',
            'group' => 'cookie-consent::cc.sidebar_label',
            'type' => 'textarea',
        ]);
        $this->extensions()->registerTranslatableSetting('cc.services', [
            'label' => 'cookie-consent::cc.services_heading',
            'group' => 'cookie-consent::cc.sidebar_label',
            'type' => 'list',
            'fields' => ['description' => 'cookie-consent::cc.service_description'],
            'item_key' => 'id',
            'item_label' => 'name',
        ]);

        // Sidebar
        $this->extensions()->registerSidebarItem([
            'label' => 'cookie-consent::cc.sidebar_label',
            'route' => 'backend.cookie-consent.settings',
            'icon' => 'cookie',
            'active' => 'backend.cookie-consent.*',
            'section' => 'extensions',
            'order' => 30,
            'permission' => 'manage-plugins',
        ]);

        // Backend routes
        $this->routes()->backend('cookie-consent', function () {
            Route::get('settings', [SettingsController::class, 'edit'])
                ->name('backend.cookie-consent.settings');
            Route::post('settings', [SettingsController::class, 'update'])
                ->name('backend.cookie-consent.settings.save');
            Route::get('services', [ServiceController::class, 'index'])
                ->name('backend.cookie-consent.services');
            Route::get('services/create', [ServiceController::class, 'create'])
                ->name('backend.cookie-consent.services.create');
            Route::post('services', [ServiceController::class, 'store'])
                ->name('backend.cookie-consent.services.store');
            Route::get('services/{id}/edit', [ServiceController::class, 'edit'])
                ->name('backend.cookie-consent.services.edit');
            Route::put('services/{id}', [ServiceController::class, 'update'])
                ->name('backend.cookie-consent.services.update');
            Route::delete('services/{id}', [ServiceController::class, 'destroy'])
                ->name('backend.cookie-consent.services.destroy');
        });
    }

    public function install(): void
    {
        $s = $this->settings();
        $s->set('cc.enabled', '1', 'plugin_setting');
        $s->set('cc.banner_text', '', 'plugin_setting');
        $s->set('cc.privacy_page_slug', '', 'plugin_setting');
        $s->set('cc.imprint_page_slug', '', 'plugin_setting');
        $s->set('cc.position', 'bottom', 'plugin_setting');
        $s->set('cc.bg_color', '#1e293b', 'plugin_setting');
        $s->set('cc.text_color', '#f1f5f9', 'plugin_setting');
        $s->set('cc.btn_bg_color', '#4f46e5', 'plugin_setting');
        $s->set('cc.btn_text_color', '#ffffff', 'plugin_setting');
        $s->set('cc.cookie_lifetime', '365', 'plugin_setting');
        $s->set('cc.show_reopen_btn', '1', 'plugin_setting');
        $s->set('cc.reopen_position', 'left', 'plugin_setting');
        $s->set('cc.services', '[]', 'plugin_setting');
    }

    public function uninstall(): void
    {
        $keys = [
            'cc.enabled', 'cc.banner_text', 'cc.privacy_page_slug', 'cc.imprint_page_slug',
            'cc.position', 'cc.bg_color', 'cc.text_color', 'cc.btn_bg_color', 'cc.btn_text_color',
            'cc.cookie_lifetime', 'cc.show_reopen_btn', 'cc.reopen_position', 'cc.services',
        ];
        foreach ($keys as $key) {
            $this->settings()->delete($key);
        }
    }
}
