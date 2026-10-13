<?php

namespace Plugins\MailProviders\Services;

use Plugins\MailProviders\Contracts\MailProviderInterface;

class MailProviderService
{
    /** @var array<string, MailProviderInterface> */
    protected array $providers = [];

    public function register(MailProviderInterface $provider): void
    {
        $this->providers[$provider->slug()] = $provider;
    }

    public function get(string $slug): ?MailProviderInterface
    {
        return $this->providers[$slug] ?? null;
    }

    /** @return array<string, MailProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    public function activeSlug(): string
    {
        return cms()->settings()->get('mailproviders.active_provider', 'smtp');
    }

    public function active(): ?MailProviderInterface
    {
        return $this->get($this->activeSlug());
    }
}
