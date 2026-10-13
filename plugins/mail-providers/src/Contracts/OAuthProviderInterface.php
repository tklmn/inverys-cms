<?php

namespace Plugins\MailProviders\Contracts;

interface OAuthProviderInterface extends MailProviderInterface
{
    public function getAuthorizationUrl(string $state): string;

    public function getScopes(): array;

    public function handleCallback(string $code): void;

    public function hasValidTokens(): bool;

    public function refreshTokenIfNeeded(): void;

    public function disconnect(): void;
}
