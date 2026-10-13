<?php

namespace App\Search\Admin;

use App\Contracts\AdminSearchProvider;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminSearch;
use App\Services\PluginManager;
use App\Support\GeneralSettings;

/**
 * Settings: the settings pages with their keywords ("smtp" finds the
 * mail page) and the individual settings under General.
 *
 * Plugins with their own settings page ("settings_route" in plugin.json)
 * appear automatically; their keywords live in "search_keywords"
 * (text or translation key, terms separated by commas).
 */
class SettingsAdminSearch implements AdminSearchProvider
{
    /** Core pages: route => [label, keywords] */
    private const PAGES = [
        'backend.settings.index' => ['translation.navigation.general', 'translation.admin_search.keywords.general'],
        'backend.settings.search' => ['translation.navigation.search', 'translation.admin_search.keywords.search'],
        'backend.settings.security' => ['translation.navigation.security', 'translation.admin_search.keywords.security'],
        'backend.settings.seo' => ['translation.navigation.seo', 'translation.admin_search.keywords.seo'],
        'backend.api-tokens.index' => ['translation.navigation.api_tokens', 'translation.admin_search.keywords.api_tokens'],
        'backend.notifications.settings' => ['translation.navigation.notifications', 'translation.admin_search.keywords.notifications'],
        'backend.themes.index' => ['translation.navigation.themes', 'translation.admin_search.keywords.themes'],
        'backend.plugins.index' => ['translation.navigation.plugins', 'translation.admin_search.keywords.plugins'],
        'backend.updates.index' => ['translation.navigation.updates', 'translation.admin_search.keywords.updates'],
        'profile.edit' => ['translation.users.profile', 'translation.admin_search.keywords.profile'],
    ];

    public function __construct(private ?PluginManager $plugins = null) {}

    public function key(): string
    {
        return 'settings';
    }

    public function label(): string
    {
        return 'translation.admin_search.settings';
    }

    public function icon(): string
    {
        return 'settings';
    }

    public function search(string $query, User $user, int $limit): iterable
    {
        $found = [];

        foreach ($this->pages() as $route => [$label, $keywords, $haystack]) {
            if (count($found) >= $limit) {
                break;
            }
            if (AdminSearch::matches($query, $haystack) && AdminSearch::mayOpen($route, $user)) {
                $found[] = ['title' => $label, 'url' => route($route), 'context' => $keywords !== '' ? $keywords : null];
            }
        }

        // Individual settings under General (site name, timezone …)
        if (count($found) < $limit && AdminSearch::mayOpen('backend.settings.index', $user)) {
            $settings = Setting::whereIn('key', GeneralSettings::KEYS)->get(['id', 'key']);
            foreach ($settings as $setting) {
                $definition = GeneralSettings::definition($setting->key);
                if (! AdminSearch::matches($query, $definition['label'].' '.$setting->key)) {
                    continue;
                }

                $managed = $definition['managed'];
                $url = $managed ? (AdminSearch::mayOpen($managed['route'], $user) ? route($managed['route']) : null)
                    : (AdminSearch::mayOpen('backend.settings.edit', $user) ? route('backend.settings.edit', $setting) : null);
                if ($url !== null) {
                    $found[] = ['title' => $definition['label'], 'url' => $url, 'context' => __('translation.navigation.general')];
                }
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Pages with settings: core and active plugins — each with label,
     * keywords (display) and the text that is searched.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private function pages(): array
    {
        $entries = self::PAGES;

        $plugins = $this->plugins ?? app(PluginManager::class);
        foreach (array_keys($plugins->getLoadedProviders()) as $slug) {
            $manifest = $plugins->get($slug) ?? [];
            $route = $manifest['settings_route'] ?? null;
            if (is_string($route) && $route !== '' && ! isset($entries[$route])) {
                $entries[$route] = [(string) ($manifest['name'] ?? $slug), (string) ($manifest['search_keywords'] ?? '')];
            }
        }

        $pages = [];
        foreach ($entries as $route => [$label, $keywords]) {
            // Search in the UI language and in English (technical terms like SMTP)
            $local = [__($label), $keywords !== '' ? __($keywords) : ''];
            $english = [__($label, [], 'en'), $keywords !== '' ? __($keywords, [], 'en') : ''];
            $pages[$route] = [$local[0], $local[1], implode(' ', [...$local, ...$english])];
        }

        return $pages;
    }
}
