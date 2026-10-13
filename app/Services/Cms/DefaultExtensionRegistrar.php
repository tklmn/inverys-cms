<?php

namespace App\Services\Cms;

use App\Contracts\AdminSearchProvider;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\MediaUsageChecker;
use App\Contracts\SearchProvider;
use App\Contracts\SitemapLocalizer;
use App\Contracts\SitemapProvider;
use App\Support\Revisionables;
use App\Support\TranslatableSettings;
use Closure;

class DefaultExtensionRegistrar implements ExtensionRegistrar
{
    protected array $sidebarItems = [];

    protected array $searchProviders = [];

    /** @var array<string, AdminSearchProvider> */
    protected array $adminSearchProviders = [];

    protected array $trashTabs = [];

    protected array $bodyHooks = [];

    protected array $headHooks = [];

    protected array $navigationHooks = [];

    protected array $mediaUsageCheckers = [];

    protected array $settingsPrefixes = [];

    protected array $dashboardWidgets = [];

    protected array $sitemapProviders = [];

    protected ?SitemapLocalizer $sitemapLocalizer = null;

    public function registerSidebarItem(array $item): void
    {
        $this->sidebarItems[] = $item;
    }

    public function registerSearchProvider(SearchProvider $provider): void
    {
        $this->searchProviders[] = $provider;
    }

    public function registerAdminSearchProvider(AdminSearchProvider $provider): void
    {
        // One key, one group — a second registration replaces the first
        $this->adminSearchProviders[$provider->key()] = $provider;
    }

    public function getAdminSearchProviders(): array
    {
        return array_values($this->adminSearchProviders);
    }

    public function registerRevisionable(string $type, array $options): void
    {
        app(Revisionables::class)->register($type, $options);
    }

    public function registerTrashTab(array $tab): void
    {
        $this->trashTabs[] = $tab;
    }

    public function registerBodyHook(string $viewName): void
    {
        $this->bodyHooks[] = $viewName;
    }

    public function getSidebarItems(): array
    {
        return $this->sidebarItems;
    }

    public function getSearchProviders(): array
    {
        return $this->searchProviders;
    }

    public function getTrashTabs(): array
    {
        return $this->trashTabs;
    }

    public function getBodyHooks(): array
    {
        return $this->bodyHooks;
    }

    public function registerHeadHook(string $viewName): void
    {
        $this->headHooks[] = $viewName;
    }

    public function getHeadHooks(): array
    {
        return $this->headHooks;
    }

    public function registerNavigationHook(string $viewName): void
    {
        $this->navigationHooks[] = $viewName;
    }

    public function getNavigationHooks(): array
    {
        return $this->navigationHooks;
    }

    public function registerTranslatableSetting(string $key, array $options = []): void
    {
        app(TranslatableSettings::class)->register($key, $options);
    }

    public function registerMediaUsageChecker(MediaUsageChecker $checker): void
    {
        $this->mediaUsageCheckers[] = $checker;
    }

    public function getMediaUsageCheckers(): array
    {
        return $this->mediaUsageCheckers;
    }

    public function registerSettingsPrefix(string $prefix): void
    {
        $this->settingsPrefixes[] = $prefix;
    }

    public function getSettingsPrefixes(): array
    {
        return $this->settingsPrefixes;
    }

    public function registerDashboardWidget(array $widget): void
    {
        $this->dashboardWidgets[] = $widget;
    }

    public function getDashboardWidgets(): array
    {
        return $this->dashboardWidgets;
    }

    public function registerSitemapProvider(SitemapProvider|callable $provider, ?string $key = null, ?string $label = null): void
    {
        // Wrap callables in a provider — the generator only knows the contract
        if (! $provider instanceof SitemapProvider) {
            $provider = new class(Closure::fromCallable($provider), $key ?? 'callable-'.count($this->sitemapProviders), $label) implements SitemapProvider
            {
                public function __construct(
                    private readonly Closure $entries,
                    private readonly string $key,
                    private readonly ?string $label,
                ) {}

                public function key(): string
                {
                    return $this->key;
                }

                public function label(): ?string
                {
                    return $this->label;
                }

                public function entries(): iterable
                {
                    return ($this->entries)() ?? [];
                }
            };
        }

        $this->sitemapProviders[] = $provider;
    }

    public function getSitemapProviders(): array
    {
        return $this->sitemapProviders;
    }

    public function registerSitemapLocalizer(SitemapLocalizer $localizer): void
    {
        $this->sitemapLocalizer = $localizer;
    }

    public function getSitemapLocalizer(): ?SitemapLocalizer
    {
        return $this->sitemapLocalizer;
    }
}
