<?php

namespace Plugins\MailProviders\Contracts;

interface MailProviderInterface
{
    public function slug(): string;

    public function label(): string;

    public function configKeys(): array;

    public function validationRules(): array;

    public function requiresOAuth(): bool;

    public function isConfigured(): bool;
}
