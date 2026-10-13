<?php

namespace Plugins\MailProviders;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Plugins\MailProviders\Services\MailProviderService;
use Plugins\MailProviders\Services\OAuthTokenManager;
use Plugins\MailProviders\Services\Providers;
use Plugins\MailProviders\Transport\GmailApiTransport;
use Plugins\MailProviders\Transport\MicrosoftGraphTransport;

class MailProvidersServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\MailProviders');

        $this->app->singleton(OAuthTokenManager::class);

        $this->app->singleton(MailProviderService::class, function ($app) {
            $service = new MailProviderService;
            $tokenManager = $app->make(OAuthTokenManager::class);

            $service->register(new Providers\SmtpProvider);
            $service->register(new Providers\MicrosoftGraphProvider($tokenManager));
            $service->register(new Providers\GmailProvider($tokenManager));

            return $service;
        });

        // Make this provider accessible for test-mail re-apply
        $this->app->instance(self::class, $this);
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        $this->registerPluginViews('mail-providers', $pluginPath.'/views');

        $this->app->make('translator')->addNamespace('mail-providers', $pluginPath.'/lang');

        // Backend routes (auth + permission)
        $this->routes()->backend('mail-providers', function () {
            Route::get('/settings', [Http\Controllers\SettingsController::class, 'edit'])
                ->name('backend.mail-providers.settings');
            Route::put('/settings', [Http\Controllers\SettingsController::class, 'update'])
                ->name('backend.mail-providers.settings.update');
            Route::post('/test', [Http\Controllers\SettingsController::class, 'sendTest'])
                ->middleware('throttle:5,1')
                ->name('backend.mail-providers.test');
            Route::post('/oauth/{provider}/authorize', [Http\Controllers\OAuthController::class, 'authorize'])
                ->name('backend.mail-providers.oauth.authorize')
                ->where('provider', 'microsoft|gmail');
            Route::post('/oauth/{provider}/disconnect', [Http\Controllers\OAuthController::class, 'disconnect'])
                ->name('backend.mail-providers.oauth.disconnect')
                ->where('provider', 'microsoft|gmail');
        });

        // Web route for OAuth callback (public, needs session)
        $this->routes()->web('mail-providers', function () {
            Route::get('/oauth/{provider}/callback', [Http\Controllers\OAuthController::class, 'callback'])
                ->name('mail-providers.oauth.callback')
                ->where('provider', 'microsoft|gmail')
                ->middleware('auth');
        });

        $this->extensions()->registerSettingsPrefix('mailproviders.');

        // Sidebar
        $this->extensions()->registerSidebarItem([
            'route' => 'backend.mail-providers.settings',
            'icon' => 'mail',
            'label' => 'mail-providers::mail-providers.mail_providers',
            'active' => 'backend.mail-providers.*',
            'section' => 'system',
            'order' => 15,
            'permission' => 'mail-providers-settings',
        ]);

        // Apply mail configuration
        $this->applyMailConfig();
    }

    public function applyMailConfig(): void
    {
        try {
            $s = $this->settings();
            $activeProvider = $s->get('mailproviders.active_provider', '');

            if (empty($activeProvider)) {
                return;
            }

            // From address/name override
            $fromAddress = $s->get('mailproviders.from_address', '');
            $fromName = $s->get('mailproviders.from_name', '');

            if (! empty($fromAddress)) {
                config(['mail.from.address' => $fromAddress]);
            }
            if (! empty($fromName)) {
                config(['mail.from.name' => $fromName]);
            }

            switch ($activeProvider) {
                case 'smtp':
                    $this->applySmtpConfig($s);
                    break;
                case 'microsoft':
                    $this->applyMicrosoftConfig();
                    break;
                case 'gmail':
                    $this->applyGmailConfig();
                    break;
                default:
                    // Unknown values send via SMTP
                    $this->applySmtpConfig($s);
            }
        } catch (\Exception $e) {
            if (! app()->runningInConsole()) {
                \Log::warning('Mail providers boot failed: '.$e->getMessage());
            }
        }
    }

    protected function applySmtpConfig($s): void
    {
        $host = $s->get('mailproviders.smtp.host', '');

        if (empty($host)) {
            return;
        }

        $password = $s->get('mailproviders.smtp.password', '');
        if ($password !== '') {
            try {
                $password = decrypt($password);
            } catch (\Exception $e) {
                $password = '';
            }
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $s->get('mailproviders.smtp.port', 587),
            'mail.mailers.smtp.username' => $s->get('mailproviders.smtp.username', ''),
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $s->get('mailproviders.smtp.encryption', 'tls'),
            'mail.mailers.smtp.timeout' => 10,
        ]);
    }

    protected function applyMicrosoftConfig(): void
    {
        $tokenManager = $this->app->make(OAuthTokenManager::class);

        Mail::extend('microsoft-graph', function () use ($tokenManager) {
            return new MicrosoftGraphTransport($tokenManager);
        });

        config([
            'mail.default' => 'microsoft-graph',
            'mail.mailers.microsoft-graph' => ['transport' => 'microsoft-graph'],
        ]);
    }

    protected function applyGmailConfig(): void
    {
        $tokenManager = $this->app->make(OAuthTokenManager::class);

        Mail::extend('gmail-api', function () use ($tokenManager) {
            return new GmailApiTransport($tokenManager);
        });

        config([
            'mail.default' => 'gmail-api',
            'mail.mailers.gmail-api' => ['transport' => 'gmail-api'],
        ]);
    }

    public function syncPermissions(): void
    {
        $permNames = ['mail-providers-settings'];

        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
    }

    public function install(): void
    {
        $defaults = [
            'mailproviders.active_provider' => ['value' => 'smtp', 'type' => 'string', 'description' => 'Active mail provider'],
            'mailproviders.from_address' => ['value' => '', 'type' => 'string', 'description' => 'Global from address'],
            'mailproviders.from_name' => ['value' => '', 'type' => 'string', 'description' => 'Global from name'],
            // SMTP
            'mailproviders.smtp.host' => ['value' => '', 'type' => 'string', 'description' => 'SMTP host'],
            'mailproviders.smtp.port' => ['value' => '587', 'type' => 'string', 'description' => 'SMTP port'],
            'mailproviders.smtp.username' => ['value' => '', 'type' => 'string', 'description' => 'SMTP username'],
            'mailproviders.smtp.password' => ['value' => '', 'type' => 'encrypted', 'description' => 'SMTP password'],
            'mailproviders.smtp.encryption' => ['value' => 'tls', 'type' => 'string', 'description' => 'SMTP encryption'],
            // Microsoft Graph
            'mailproviders.microsoft.tenant_id' => ['value' => 'common', 'type' => 'string', 'description' => 'Microsoft tenant ID'],
            'mailproviders.microsoft.client_id' => ['value' => '', 'type' => 'string', 'description' => 'Microsoft client ID'],
            'mailproviders.microsoft.client_secret' => ['value' => '', 'type' => 'encrypted', 'description' => 'Microsoft client secret'],
            'mailproviders.microsoft.access_token' => ['value' => '', 'type' => 'encrypted', 'description' => 'Microsoft access token'],
            'mailproviders.microsoft.refresh_token' => ['value' => '', 'type' => 'encrypted', 'description' => 'Microsoft refresh token'],
            'mailproviders.microsoft.token_expires' => ['value' => '', 'type' => 'string', 'description' => 'Microsoft token expiry'],
            'mailproviders.microsoft.user_email' => ['value' => '', 'type' => 'string', 'description' => 'Microsoft authenticated email'],
            // Gmail
            'mailproviders.gmail.client_id' => ['value' => '', 'type' => 'string', 'description' => 'Gmail client ID'],
            'mailproviders.gmail.client_secret' => ['value' => '', 'type' => 'encrypted', 'description' => 'Gmail client secret'],
            'mailproviders.gmail.access_token' => ['value' => '', 'type' => 'encrypted', 'description' => 'Gmail access token'],
            'mailproviders.gmail.refresh_token' => ['value' => '', 'type' => 'encrypted', 'description' => 'Gmail refresh token'],
            'mailproviders.gmail.token_expires' => ['value' => '', 'type' => 'string', 'description' => 'Gmail token expiry'],
            'mailproviders.gmail.user_email' => ['value' => '', 'type' => 'string', 'description' => 'Gmail authenticated email'],
        ];

        $this->seedSettings($defaults);
        $this->syncPermissions();
    }

    public function uninstall(): void
    {
        $keys = [
            'mailproviders.active_provider', 'mailproviders.from_address', 'mailproviders.from_name',
            'mailproviders.smtp.host', 'mailproviders.smtp.port', 'mailproviders.smtp.username',
            'mailproviders.smtp.password', 'mailproviders.smtp.encryption',
            'mailproviders.microsoft.tenant_id', 'mailproviders.microsoft.client_id',
            'mailproviders.microsoft.client_secret', 'mailproviders.microsoft.access_token',
            'mailproviders.microsoft.refresh_token', 'mailproviders.microsoft.token_expires',
            'mailproviders.microsoft.user_email',
            'mailproviders.gmail.client_id', 'mailproviders.gmail.client_secret',
            'mailproviders.gmail.access_token', 'mailproviders.gmail.refresh_token',
            'mailproviders.gmail.token_expires', 'mailproviders.gmail.user_email',
        ];

        foreach ($keys as $key) {
            $this->settings()->delete($key);
        }
    }
}
