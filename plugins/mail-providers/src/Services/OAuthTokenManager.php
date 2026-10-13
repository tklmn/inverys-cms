<?php

namespace Plugins\MailProviders\Services;

use App\Contracts\SettingRepository;
use Illuminate\Support\Facades\Http;

class OAuthTokenManager
{
    protected function settings(): SettingRepository
    {
        return app(SettingRepository::class);
    }

    public function getAccessToken(string $provider): string
    {
        if ($this->isExpired($provider)) {
            $this->refreshToken($provider);
        }

        $token = $this->settings()->get("mailproviders.{$provider}.access_token", '');

        if ($token !== '') {
            try {
                $token = decrypt($token);
            } catch (\Exception $e) {
                $token = '';
            }
        }

        return $token;
    }

    public function isExpired(string $provider): bool
    {
        $expires = $this->settings()->get("mailproviders.{$provider}.token_expires", '');

        if (empty($expires)) {
            return true;
        }

        return (int) $expires < (time() - 60);
    }

    public function hasTokens(string $provider): bool
    {
        $accessToken = $this->settings()->get("mailproviders.{$provider}.access_token", '');
        $refreshToken = $this->settings()->get("mailproviders.{$provider}.refresh_token", '');

        return $accessToken !== '' && $refreshToken !== '';
    }

    public function storeTokens(string $provider, array $data): void
    {
        $this->settings()->set(
            "mailproviders.{$provider}.access_token",
            encrypt($data['access_token']),
            'encrypted',
            "{$provider} OAuth access token"
        );

        if (! empty($data['refresh_token'])) {
            $this->settings()->set(
                "mailproviders.{$provider}.refresh_token",
                encrypt($data['refresh_token']),
                'encrypted',
                "{$provider} OAuth refresh token"
            );
        }

        $expiresAt = time() + ($data['expires_in'] ?? 3600);
        $this->settings()->set(
            "mailproviders.{$provider}.token_expires",
            (string) $expiresAt,
            'string',
            "{$provider} OAuth token expiry timestamp"
        );

        if (! empty($data['user_email'])) {
            $this->settings()->set(
                "mailproviders.{$provider}.user_email",
                $data['user_email'],
                'string',
                "{$provider} authenticated user email"
            );
        }
    }

    public function refreshToken(string $provider): void
    {
        $refreshToken = $this->settings()->get("mailproviders.{$provider}.refresh_token", '');
        if ($refreshToken === '') {
            return;
        }

        try {
            $refreshToken = decrypt($refreshToken);
        } catch (\Exception $e) {
            return;
        }

        $clientId = $this->settings()->get("mailproviders.{$provider}.client_id", '');
        $clientSecret = $this->settings()->get("mailproviders.{$provider}.client_secret", '');

        if ($clientSecret !== '') {
            try {
                $clientSecret = decrypt($clientSecret);
            } catch (\Exception $e) {
                return;
            }
        }

        if ($provider === 'microsoft') {
            $this->refreshMicrosoftToken($clientId, $clientSecret, $refreshToken);
        } elseif ($provider === 'gmail') {
            $this->refreshGmailToken($clientId, $clientSecret, $refreshToken);
        }
    }

    protected function refreshMicrosoftToken(string $clientId, string $clientSecret, string $refreshToken): void
    {
        $tenantId = cms()->settings()->get('mailproviders.microsoft.tenant_id', 'common');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => 'offline_access Mail.Send User.Read',
            ]
        );

        if ($response->successful()) {
            $this->storeTokens('microsoft', $response->json());
        }
    }

    protected function refreshGmailToken(string $clientId, string $clientSecret, string $refreshToken): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $this->storeTokens('gmail', $response->json());
        }
    }

    public function clearTokens(string $provider): void
    {
        $keys = ['access_token', 'refresh_token', 'token_expires', 'user_email'];

        foreach ($keys as $key) {
            $this->settings()->set("mailproviders.{$provider}.{$key}", '', 'string');
        }
    }
}
