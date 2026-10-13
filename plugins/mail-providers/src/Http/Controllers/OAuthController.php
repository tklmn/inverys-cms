<?php

namespace Plugins\MailProviders\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Plugins\MailProviders\Contracts\OAuthProviderInterface;
use Plugins\MailProviders\Services\MailProviderService;

// Connecting/disconnecting the mail account: otherwise any backend user could
// route sending (incl. password reset mails) through their own account
#[Middleware('permission:mail-providers-settings')]
class OAuthController extends Controller
{
    public function authorize(Request $request, string $provider): RedirectResponse
    {
        $providerInstance = $this->resolveOAuthProvider($provider);

        if (! $providerInstance) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_invalid_provider'));
        }

        $state = Str::random(40);
        $request->session()->put("mail-providers.oauth_state.{$provider}", $state);

        return redirect()->away($providerInstance->getAuthorizationUrl($state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $providerInstance = $this->resolveOAuthProvider($provider);

        if (! $providerInstance) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_invalid_provider'));
        }

        // Verify state
        $expectedState = $request->session()->pull("mail-providers.oauth_state.{$provider}");
        if (! $expectedState || $request->input('state') !== $expectedState) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_state_mismatch'));
        }

        // Check for errors from provider
        if ($request->has('error')) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_denied').' '.$request->input('error_description', ''));
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_no_code'));
        }

        try {
            $providerInstance->handleCallback($code);

            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('success', __('mail-providers::mail-providers.oauth_connected', ['provider' => $providerInstance->label()]));
        } catch (\Exception $e) {
            \Log::warning("OAuth callback failed for {$provider}", ['error' => $e->getMessage()]);

            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_failed').' '.$e->getMessage());
        }
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $providerInstance = $this->resolveOAuthProvider($provider);

        if (! $providerInstance) {
            return redirect()
                ->route('backend.mail-providers.settings')
                ->with('error', __('mail-providers::mail-providers.oauth_invalid_provider'));
        }

        $providerInstance->disconnect();

        return redirect()
            ->route('backend.mail-providers.settings')
            ->with('success', __('mail-providers::mail-providers.oauth_disconnected', ['provider' => $providerInstance->label()]));
    }

    protected function resolveOAuthProvider(string $slug): ?OAuthProviderInterface
    {
        $service = app(MailProviderService::class);
        $provider = $service->get($slug);

        if ($provider instanceof OAuthProviderInterface) {
            return $provider;
        }

        return null;
    }
}
