<?php

namespace App\Contracts;

interface ExtensionRegistrar
{
    public function registerSidebarItem(array $item): void;

    public function registerSearchProvider(SearchProvider $provider): void;

    /**
     * Contribute results to the backend quick search (Ctrl/⌘ + K) —
     * e.g. the posts of a blog. See AdminSearchProvider.
     */
    public function registerAdminSearchProvider(AdminSearchProvider $provider): void;

    /** @return list<AdminSearchProvider> */
    public function getAdminSearchProviders(): array;

    /**
     * Offer revisions for a content type (model with App\Traits\HasRevisions):
     * list, compare, preview and restore as with pages.
     * For options see App\Support\Revisionables.
     *
     * @param  array<string, mixed>  $options
     */
    public function registerRevisionable(string $type, array $options): void;

    public function registerTrashTab(array $tab): void;

    public function registerBodyHook(string $viewName): void;

    public function registerHeadHook(string $viewName): void;

    /**
     * View that a theme outputs in its main navigation — e.g. the
     * language menu of the multilingual plugin. Themes render all
     * navigation hooks in one place next to search/profile.
     */
    public function registerNavigationHook(string $viewName): void;

    /** @return list<string> */
    public function getNavigationHooks(): array;

    /**
     * Make a setting whose text visitors see translatable per language
     * (multilingual plugin) — e.g. the text of a banner.
     * Options: label, group, type (text|textarea|list), fields, item_key, item_label;
     * see App\Support\TranslatableSettings.
     */
    public function registerTranslatableSetting(string $key, array $options = []): void;

    public function registerMediaUsageChecker(MediaUsageChecker $checker): void;

    public function getSidebarItems(): array;

    public function getSearchProviders(): array;

    public function getTrashTabs(): array;

    public function getBodyHooks(): array;

    public function getHeadHooks(): array;

    public function getMediaUsageCheckers(): array;

    public function registerSettingsPrefix(string $prefix): void;

    public function getSettingsPrefixes(): array;

    public function registerDashboardWidget(array $widget): void;

    public function getDashboardWidgets(): array;

    /**
     * Contribute addresses to /sitemap.xml. A callable returns the same
     * entries as SitemapProvider::entries(); $key and $label then act
     * like SitemapProvider::key()/label() (without label: always included, no toggle).
     *
     * @param  SitemapProvider|callable(): iterable<array>  $provider
     */
    public function registerSitemapProvider(SitemapProvider|callable $provider, ?string $key = null, ?string $label = null): void;

    /** @return list<SitemapProvider> */
    public function getSitemapProviders(): array;

    /**
     * Language versions for the sitemap (multilingual plugin). There is only
     * one — one registered later replaces the previous one.
     */
    public function registerSitemapLocalizer(SitemapLocalizer $localizer): void;

    public function getSitemapLocalizer(): ?SitemapLocalizer;
}
