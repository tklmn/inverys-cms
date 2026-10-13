<?php

namespace Plugins\MailProviders\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Plugins\MailProviders\MailProvidersServiceProvider;
use Plugins\MailProviders\Services\MailProviderService;
use Plugins\MailProviders\Services\OAuthTokenManager;

#[Middleware('permission:mail-providers-settings')]
class SettingsController extends Controller
{
    /**
     * Sending methods. API services (SES, Mailgun, Postmark, Resend) are
     * connected via SMTP, so no extra package is needed.
     */
    public const PROVIDERS = ['smtp', 'microsoft', 'gmail'];

    protected const DEFAULTS = [
        'active_provider' => 'smtp',
        'from_address' => '',
        'from_name' => '',
        // SMTP
        'smtp.host' => '',
        'smtp.port' => '587',
        'smtp.username' => '',
        'smtp.password' => '',
        'smtp.encryption' => 'tls',
        // Microsoft
        'microsoft.tenant_id' => 'common',
        'microsoft.client_id' => '',
        'microsoft.client_secret' => '',
        'microsoft.user_email' => '',
        // Gmail
        'gmail.client_id' => '',
        'gmail.client_secret' => '',
        'gmail.user_email' => '',
    ];

    protected const ENCRYPTED_KEYS = [
        'smtp.password',
        'microsoft.client_secret',
        'gmail.client_secret',
    ];

    public function edit(): View
    {
        $rawSettings = cms()->settings()->getByPrefix('mailproviders.');
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings["mailproviders.{$key}"] = $value;
        }

        // Secrets (password, client secrets) never go into the page — the
        // view only learns WHETHER one is stored. Leave empty = keep.
        $storedSecrets = [];
        foreach (self::ENCRYPTED_KEYS as $encKey) {
            $fullKey = "mailproviders.{$encKey}";
            $storedSecrets[$encKey] = ! empty($settings[$fullKey]);
            unset($settings[$fullKey]);
        }

        // An unknown provider value is shown as SMTP —
        // when sending it falls back to SMTP anyway
        if (! in_array($settings['mailproviders.active_provider'] ?? 'smtp', self::PROVIDERS, true)) {
            $settings['mailproviders.active_provider'] = 'smtp';
        }

        $providerService = app(MailProviderService::class);
        $tokenManager = app(OAuthTokenManager::class);

        return view('mail-providers::settings', [
            'settings' => $settings,
            'defaults' => self::DEFAULTS,
            'storedSecrets' => $storedSecrets,
            'providers' => $providerService->all(),
            'tokenManager' => $tokenManager,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'active_provider' => 'required|in:'.implode(',', self::PROVIDERS),
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            // SMTP
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl,',
            // Microsoft
            'microsoft_tenant_id' => 'nullable|string|max:255',
            'microsoft_client_id' => 'nullable|string|max:255',
            'microsoft_client_secret' => 'nullable|string|max:255',
            // Gmail
            'gmail_client_id' => 'nullable|string|max:255',
            'gmail_client_secret' => 'nullable|string|max:255',
        ]);

        $settings = cms()->settings();

        // Global
        $settings->set('mailproviders.active_provider', $request->input('active_provider'), 'string');
        $settings->set('mailproviders.from_address', $request->input('from_address') ?? '', 'string');
        $settings->set('mailproviders.from_name', $request->input('from_name') ?? '', 'string');

        // SMTP
        $settings->set('mailproviders.smtp.host', $request->input('smtp_host') ?? '', 'string');
        $settings->set('mailproviders.smtp.port', $request->input('smtp_port') ?? '587', 'string');
        $settings->set('mailproviders.smtp.username', $request->input('smtp_username') ?? '', 'string');
        $this->setEncrypted($settings, 'mailproviders.smtp.password', $request->input('smtp_password'));
        $settings->set('mailproviders.smtp.encryption', $request->input('smtp_encryption') ?? 'tls', 'string');

        // Microsoft
        $settings->set('mailproviders.microsoft.tenant_id', $request->input('microsoft_tenant_id') ?? 'common', 'string');
        $settings->set('mailproviders.microsoft.client_id', $request->input('microsoft_client_id') ?? '', 'string');
        $this->setEncrypted($settings, 'mailproviders.microsoft.client_secret', $request->input('microsoft_client_secret'));

        // Gmail
        $settings->set('mailproviders.gmail.client_id', $request->input('gmail_client_id') ?? '', 'string');
        $this->setEncrypted($settings, 'mailproviders.gmail.client_secret', $request->input('gmail_client_secret'));

        return redirect()
            ->route('backend.mail-providers.settings')
            ->with('success', __('mail-providers::mail-providers.settings_saved'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        try {
            // Re-apply config from plugin settings
            app(MailProvidersServiceProvider::class)->applyMailConfig();

            $mailer = config('mail.default');
            app('mail.manager')->purge($mailer);

            Mail::mailer($mailer)->raw(
                __('mail-providers::mail-providers.test_body', ['app' => config('app.name')]),
                function ($message) use ($request) {
                    $message->to($request->input('test_email'))
                        ->subject(__('mail-providers::mail-providers.test_subject', ['app' => config('app.name')]));
                }
            );

            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('success', __('mail-providers::mail-providers.test_sent', ['email' => $request->input('test_email')]));
        } catch (\Exception $e) {
            \Log::warning('Mail provider test failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.test_failed').' '.Str::limit(strtok($e->getMessage(), "\n") ?: '', 200));
        }
    }

    /**
     * The form does not show stored secrets, so an empty field means
     * "leave unchanged" — otherwise every save of the page would delete
     * the password.
     */
    protected function setEncrypted($settings, string $key, ?string $value): void
    {
        if (($value ?? '') !== '') {
            $settings->set($key, encrypt($value), 'encrypted');
        }
    }
}
