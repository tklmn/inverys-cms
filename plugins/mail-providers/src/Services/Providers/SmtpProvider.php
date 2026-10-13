<?php

namespace Plugins\MailProviders\Services\Providers;

use Plugins\MailProviders\Contracts\MailProviderInterface;

class SmtpProvider implements MailProviderInterface
{
    public function slug(): string
    {
        return 'smtp';
    }

    public function label(): string
    {
        return 'SMTP';
    }

    public function configKeys(): array
    {
        return ['smtp.host', 'smtp.port', 'smtp.username', 'smtp.password', 'smtp.encryption'];
    }

    public function validationRules(): array
    {
        return [
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl,',
        ];
    }

    public function requiresOAuth(): bool
    {
        return false;
    }

    public function isConfigured(): bool
    {
        $host = cms()->settings()->get('mailproviders.smtp.host', '');

        return $host !== '';
    }
}
