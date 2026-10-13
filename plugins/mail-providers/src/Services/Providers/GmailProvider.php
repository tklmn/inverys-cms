<?php

namespace Plugins\MailProviders\Services\Providers;

use Illuminate\Support\Facades\Http;
use Plugins\MailProviders\Contracts\OAuthProviderInterface;
use Plugins\MailProviders\Services\OAuthTokenManager;

class GmailProvider implements OAuthProviderInterface
{
    protected OAuthTokenManager $tokenManager;

    public function __construct(OAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function slug(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return 'Gmail API';
    }

    public function configKeys(): array
    {
        return ['gmail.client_id', 'gmail.client_secret'];
    }

    public function validationRules(): array
    {
        return [
            'gmail_client_id' => 'nullable|string|max:255',
            'gmail_client_secret' => 'nullable|string|max:255',
        ];
    }

    public function requiresOAuth(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        $clientId = cms()->settings()->get('mailproviders.gmail.client_id', '');
        $clientSecret = cms()->settings()->get('mailproviders.gmail.client_secret', '');

        return $clientId !== '' && $clientSecret !== '';
    }

    public function getAuthorizationUrl(string $state): string
    {
        $clientId = cms()->settings()->get('mailproviders.gmail.client_id', '');
        $redirectUri = route('mail-providers.oauth.callback', ['provider' => 'gmail']);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->getScopes()),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
    }

    public function getScopes(): array
    {
        return [
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/userinfo.email',
        ];
    }

    public function handleCallback(string $code): void
    {
        $clientId = cms()->settings()->get('mailproviders.gmail.client_id', '');
        $clientSecret = cms()->settings()->get('mailproviders.gmail.client_secret', '');

        if ($clientSecret !== '') {
            try {
                $clientSecret = decrypt($clientSecret);
            } catch (\Exception $e) {
                throw new \RuntimeException('Failed to decrypt client secret.');
            }
        }

        $redirectUri = route('mail-providers.oauth.callback', ['provider' => 'gmail']);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to exchange authorization code: '.$response->body());
        }

        $tokenData = $response->json();

        // Fetch user email
        $userResponse = Http::withToken($tokenData['access_token'])
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if ($userResponse->successful()) {
            $tokenData['user_email'] = $userResponse->json('email');
        }

        $this->tokenManager->storeTokens('gmail', $tokenData);
    }

    public function hasValidTokens(): bool
    {
        return $this->tokenManager->hasTokens('gmail') && ! $this->tokenManager->isExpired('gmail');
    }

    public function refreshTokenIfNeeded(): void
    {
        if ($this->tokenManager->hasTokens('gmail') && $this->tokenManager->isExpired('gmail')) {
            $this->tokenManager->refreshToken('gmail');
        }
    }

    public function disconnect(): void
    {
        $this->tokenManager->clearTokens('gmail');
    }
}
