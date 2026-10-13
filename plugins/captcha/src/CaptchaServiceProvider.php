<?php

namespace Plugins\Captcha;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Plugins\Captcha\Contracts\CaptchaVerifier;
use Plugins\Captcha\Http\Controllers\MathCaptchaController;
use Plugins\Captcha\Http\Controllers\SettingsController;
use Plugins\Captcha\Services\CaptchaService;

class CaptchaServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\Captcha');

        // Bind the CaptchaVerifier contract
        $this->app->singleton(
            CaptchaVerifier::class,
            CaptchaService::class,
        );
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        // View namespaces
        $this->registerPluginViews('captcha', $pluginPath.'/views');
        $this->registerPluginViews('captcha-admin', $pluginPath.'/views');

        // Translations
        $this->app->make('translator')->addNamespace('captcha', $pluginPath.'/lang');

        // Frontend routes — math captcha challenge endpoint
        $this->routes()->web('captcha', function () {
            Route::get('/challenge', [MathCaptchaController::class, 'challenge'])
                ->middleware('throttle:30,1')
                ->name('captcha.challenge');
        });

        // Backend routes
        $this->routes()->backend('captcha', function () {
            Route::get('/settings', [SettingsController::class, 'edit'])->name('backend.captcha.settings');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.captcha.settings.update');
        });

        $this->extensions()->registerSettingsPrefix('captcha.');

        // Head hook — inject captcha field type into form-builder palette
        $this->extensions()->registerHeadHook('captcha::admin.field-type-script');

        // Prepend captcha views to form-builder namespace so fields.captcha view is found
        $this->extendPluginViews('form-builder', $pluginPath.'/views');

        // Listen for form-builder pre-submission validation — verify captcha
        Event::listen('form-builder.validating', function ($form, $request) {
            $service = app(CaptchaService::class);
            if (! $service->isRequiredForForm($form)) {
                return null;
            }
            $result = $service->verify($request, $form);

            return $result->passed() ? null : $result->message();
        });
    }

    public function syncPermissions(): void
    {
        $permNames = ['captcha-settings'];

        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
    }

    public function install(): void
    {
        // Seed default settings
        $defaults = [
            'captcha.enabled' => ['value' => '1', 'type' => 'boolean', 'description' => 'Global captcha enable/disable'],
            'captcha.provider' => ['value' => 'math', 'type' => 'string', 'description' => 'Active captcha provider slug'],
            'captcha.math_difficulty' => ['value' => 'easy', 'type' => 'string', 'description' => 'Math captcha difficulty (easy/medium/hard)'],
            'captcha.recaptcha_v3_site_key' => ['value' => '', 'type' => 'string', 'description' => 'reCAPTCHA v3 site key'],
            'captcha.recaptcha_v3_secret_key' => ['value' => '', 'type' => 'encrypted', 'description' => 'reCAPTCHA v3 secret key'],
            'captcha.recaptcha_v3_threshold' => ['value' => '0.5', 'type' => 'string', 'description' => 'reCAPTCHA v3 score threshold'],
            'captcha.turnstile_site_key' => ['value' => '', 'type' => 'string', 'description' => 'Cloudflare Turnstile site key'],
            'captcha.turnstile_secret_key' => ['value' => '', 'type' => 'encrypted', 'description' => 'Cloudflare Turnstile secret key'],
            'captcha.hcaptcha_site_key' => ['value' => '', 'type' => 'string', 'description' => 'hCaptcha site key'],
            'captcha.hcaptcha_secret_key' => ['value' => '', 'type' => 'encrypted', 'description' => 'hCaptcha secret key'],
            'captcha.captchafox_site_key' => ['value' => '', 'type' => 'string', 'description' => 'CaptchaFox site key'],
            'captcha.captchafox_secret_key' => ['value' => '', 'type' => 'encrypted', 'description' => 'CaptchaFox secret key'],
            'captcha.friendly_captcha_site_key' => ['value' => '', 'type' => 'string', 'description' => 'Friendly Captcha site key'],
            'captcha.friendly_captcha_secret_key' => ['value' => '', 'type' => 'encrypted', 'description' => 'Friendly Captcha secret key'],
        ];

        $this->seedSettings($defaults);

        $this->syncPermissions();
    }

    public function uninstall(): void
    {
        $keys = [
            'captcha.enabled', 'captcha.provider', 'captcha.math_difficulty',
            'captcha.recaptcha_v3_site_key', 'captcha.recaptcha_v3_secret_key', 'captcha.recaptcha_v3_threshold',
            'captcha.turnstile_site_key', 'captcha.turnstile_secret_key',
            'captcha.hcaptcha_site_key', 'captcha.hcaptcha_secret_key',
            'captcha.captchafox_site_key', 'captcha.captchafox_secret_key',
            'captcha.friendly_captcha_site_key', 'captcha.friendly_captcha_secret_key',
        ];

        foreach ($keys as $key) {
            $this->settings()->delete($key);
        }
    }
}
