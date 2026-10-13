<?php

namespace Plugins\MailProviders\Services\Providers;

use Illuminate\Support\Facades\Http;
use Plugins\MailProviders\Contracts\OAuthProviderInterface;
use Plugins\MailProviders\Services\OAuthTokenManager;

class MicrosoftGraphProvider implements OAuthProviderInterface
{
    protected OAuthTokenManager $tokenManager;

    public function __construct(OAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function slug(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Microsoft Graph';
    }

    public function configKeys(): array
    {
        return ['microsoft.tenant_id', 'microsoft.client_id', 'microsoft.client_secret'];
    }

    public function validationRules(): array
    {
        return [
            'microsoft_tenant_id' => 'nullable|string|max:255',
            'microsoft_client_id' => 'nullable|string|max:255',
            'microsoft_client_secret' => 'nullable|string|max:255',
        ];
    }

    public function requiresOAuth(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        $clientId = cms()->settings()->get('mailproviders.microsoft.client_id', '');
        $clientSecret = cms()->settings()->get('mailproviders.microsoft.client_secret', '');

        return $clientId !== '' && $clientSecret !== '';
    }

    public function getAuthorizationUrl(string $state): string
    {
        $tenantId = cms()->settings()->get('mailproviders.microsoft.tenant_id', 'common');
        $clientId = cms()->settings()->get('mailproviders.microsoft.client_id', '');
        $redirectUri = route('mail-providers.oauth.callback', ['provider' => 'microsoft']);

        return "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?".http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->getScopes()),
            'state' => $state,
            'response_mode' => 'query',
        ]);
    }

    public function getScopes(): array
    {
        return ['offline_access', 'Mail.Send', 'User.Read'];
    }

    public function handleCallback(string $code): void
    {
        $tenantId = cms()->settings()->get('mailproviders.microsoft.tenant_id', 'common');
        $clientId = cms()->settings()->get('mailproviders.microsoft.client_id', '');
        $clientSecret = cms()->settings()->get('mailproviders.microsoft.client_secret', '');

        if ($clientSecret !== '') {
            try {
                $clientSecret = decrypt($clientSecret);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to decrypt client secret.');
            }
        }

        $redirectUri = route('mail-providers.oauth.callback', ['provider' => 'microsoft']);

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'scope' => implode(' ', $this->getScopes()),
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to exchange authorization code: '.$response->body());
        }

        $tokenData = $response->json();

        // Fetch user email
        $userResponse = Http::withToken($tokenData['access_token'])
            ->get('https://graph.microsoft.com/v1.0/me');

        if ($userResponse->successful()) {
            $tokenData['user_email'] = $userResponse->json('mail') ?: $userResponse->json('userPrincipalName');
        }

        $this->tokenManager->storeTokens('microsoft', $tokenData);
    }

    public function hasValidTokens(): bool
    {
        return $this->tokenManager->hasTokens('microsoft') && ! $this->tokenManager->isExpired('microsoft');
    }

    public function refreshTokenIfNeeded(): void
    {
        if ($this->tokenManager->hasTokens('microsoft') && $this->tokenManager->isExpired('microsoft')) {
            $this->tokenManager->refreshToken('microsoft');
        }
    }

    public function disconnect(): void
    {
        $this->tokenManager->clearTokens('microsoft');
    }
}
