<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ExtensionRegistrar;
use App\Services\PluginManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Structure of the backend navigation.
 *
 * Core and plugins feed the same structure so that order, permissions and
 * active state are defined in one place only. Breadcrumbs or a command
 * palette can later be derived from this structure as well.
 *
 * The active state has two levels:
 *   'current' — exactly this page
 *   'within'  — the section you are in (parent item of a child)
 * A parent item never becomes 'current' while one of its children is.
 */
final class Navigation
{
    /**
     * @return array<int, array{label: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        $groups = collect(self::definition())
            ->map(fn (array $group) => array_merge($group['items'], self::pluginItems($group['section'] ?? null)));

        // A plugin's settings page (settings_route in plugin.json) is reached from
        // the plugin list, so it marks "Plugins" — not the plugin's content entry
        // (Posts, Forms …). Settings pages that are a menu entry of their own stay theirs.
        $ownEntries = $groups->flatten(1)
            ->flatMap(fn (array $item) => array_merge([$item['route'] ?? null], array_column($item['children'] ?? [], 'route')))
            ->filter()
            ->all();
        $settingsRoutes = array_values(array_diff(self::pluginSettingsRoutes(), $ownEntries));
        $onPluginSettings = $settingsRoutes !== [] && request()->routeIs(...$settingsRoutes);

        return collect(self::definition())
            ->map(fn (array $group, int $i) => [
                'label' => $group['label'],
                'items' => self::prepare(array_map(function (array $item) use ($onPluginSettings, $settingsRoutes) {
                    if (($item['route'] ?? null) === 'backend.plugins.index') {
                        $item['active'] = array_merge($item['active'] ?? [], $settingsRoutes);
                    } elseif ($onPluginSettings) {
                        $item['active'] = [];
                    }

                    return $item;
                }, $groups[$i])),
            ])
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * Settings routes of the loaded plugins (settings_route in plugin.json).
     *
     * @return list<string>
     */
    private static function pluginSettingsRoutes(): array
    {
        if (! app()->bound(PluginManager::class)) {
            return [];
        }

        return collect(app(PluginManager::class)->all())
            ->pluck('settings_route')
            ->filter(fn ($route) => is_string($route) && Route::has($route))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function definition(): array
    {
        return [
            [
                'label' => null,
                'items' => [
                    [
                        'label' => 'translation.dashboard.title',
                        'icon' => 'layout-dashboard',
                        'route' => 'dashboard',
                        'active' => ['dashboard'],
                    ],
                ],
            ],
            [
                'label' => 'translation.navigation.content',
                'section' => 'content',
                'items' => [
                    [
                        'label' => 'translation.navigation.pages',
                        'order' => 10,
                        'icon' => 'file-text',
                        'route' => 'backend.pages.index',
                        'active' => ['backend.pages.*', 'backend.page-categories.*'],
                        'permission' => 'show-page',
                    ],
                    [
                        'label' => 'translation.navigation.media',
                        'order' => 20,
                        'icon' => 'image',
                        'route' => 'backend.media.index',
                        'active' => ['backend.media.*'],
                        'permission' => 'show-media',
                    ],
                    [
                        // The trash covers pages, media and users, which makes
                        // it a content topic rather than a system one.
                        'label' => 'translation.trash',
                        'order' => 90,
                        'icon' => 'trash-2',
                        'route' => 'backend.trash.index',
                        'active' => ['backend.trash.*'],
                        'permission' => 'manage-trash',
                    ],
                ],
            ],
            [
                'label' => 'translation.navigation.user_management',
                'section' => 'users',
                'items' => [
                    [
                        'label' => 'translation.navigation.users',
                        'order' => 10,
                        'icon' => 'users',
                        'route' => 'backend.users.index',
                        'active' => ['backend.users.*'],
                        'permission' => 'show-user',
                    ],
                    [
                        'label' => 'translation.roles.management',
                        'order' => 20,
                        'icon' => 'shield',
                        'route' => 'backend.roles.index',
                        'active' => ['backend.roles.*'],
                        'permission' => 'show-role',
                    ],
                    [
                        'label' => 'translation.navigation.permissions',
                        'order' => 30,
                        'icon' => 'key-round',
                        'route' => 'backend.permissions.index',
                        'active' => ['backend.permissions.*'],
                        'permission' => 'show-permission',
                    ],
                ],
            ],
            [
                'label' => 'translation.navigation.system',
                'section' => 'system',
                'items' => [
                    [
                        // Pure group button without its own route: the sub-items
                        // carry the targets, the parent item only the state.
                        'label' => 'translation.navigation.settings',
                        'order' => 10,
                        'icon' => 'settings',
                        'children' => [
                            [
                                'label' => 'translation.navigation.general',
                                'route' => 'backend.settings.index',
                                'active' => [
                                    'backend.settings.index',
                                    'backend.settings.create',
                                    'backend.settings.show',
                                    'backend.settings.edit',
                                ],
                                'permission' => 'show-settings',
                            ],
                            [
                                'label' => 'translation.navigation.search',
                                'route' => 'backend.settings.search',
                                'active' => ['backend.settings.search*'],
                                'permission' => 'manage-search-settings',
                            ],
                            [
                                'label' => 'translation.navigation.security',
                                'route' => 'backend.settings.security',
                                'active' => ['backend.settings.security*'],
                                'permission' => 'manage-security-settings',
                            ],
                            [
                                'label' => 'translation.navigation.seo',
                                'route' => 'backend.settings.seo',
                                'active' => ['backend.settings.seo*'],
                                'permission' => 'manage-seo-settings',
                            ],
                            [
                                'label' => 'translation.navigation.api_tokens',
                                'route' => 'backend.api-tokens.index',
                                'active' => ['backend.api-tokens.*'],
                                'permission' => 'api-access',
                            ],
                        ],
                    ],
                    [
                        'label' => 'translation.navigation.logs',
                        'order' => 20,
                        'icon' => 'scroll-text',
                        'route' => 'backend.logs.index',
                        'active' => ['backend.logs.*'],
                        'permission' => 'show-logs',
                    ],
                    [
                        'label' => 'translation.navigation.updates',
                        'order' => 30,
                        'icon' => 'circle-arrow-up',
                        'route' => 'backend.updates.index',
                        'active' => ['backend.updates.*'],
                        'permission' => 'manage-updates',
                    ],
                    [
                        'label' => 'translation.navigation.system_info',
                        'order' => 40,
                        'icon' => 'server',
                        'route' => 'backend.system.info',
                        'active' => ['backend.system.*'],
                        'permission' => 'show-system',
                    ],
                ],
            ],
            [
                'label' => 'translation.navigation.extensions',
                'section' => 'extensions',
                'items' => [
                    [
                        'label' => 'translation.navigation.themes',
                        'order' => 10,
                        'icon' => 'palette',
                        'route' => 'backend.themes.index',
                        'active' => ['backend.themes.*'],
                        'permission' => 'manage-themes',
                    ],
                    [
                        'label' => 'translation.navigation.plugins',
                        'order' => 20,
                        'icon' => 'puzzle',
                        'route' => 'backend.plugins.index',
                        'active' => ['backend.plugins.*'],
                        'permission' => 'manage-plugins',
                    ],
                ],
            ],
        ];
    }

    /**
     * Entries of a group registered by plugins.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function pluginItems(?string $section): array
    {
        if ($section === null || ! app()->bound(ExtensionRegistrar::class)) {
            return [];
        }

        return collect(app(ExtensionRegistrar::class)->getSidebarItems())
            ->filter(fn (array $item) => ($item['section'] ?? null) === $section)
            ->map(fn (array $item) => [
                'label' => $item['label'],
                'icon' => $item['icon'] ?? 'plug',
                'route' => $item['route'],
                'active' => array_values(array_filter([$item['active'] ?? null])),
                'permission' => $item['permission'] ?? null,
                // Without an explicit order, plugin entries land before the
                // trash but after the core entries of their group.
                'order' => $item['order'] ?? 50,
            ])
            ->values()
            ->all();
    }

    /**
     * Check permissions, drop unknown routes, set the active state.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function prepare(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item) => self::visible($item))
            // Core and plugins share one ordering scale so that a plugin
            // entry can sit where it belongs topically.
            ->sortBy(fn (array $item) => $item['order'] ?? 50)
            ->map(function (array $item) {
                $children = self::prepare($item['children'] ?? []);
                $childIsCurrent = collect($children)->contains(fn (array $child) => $child['state'] === 'current');

                return [
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'url' => isset($item['route']) ? route($item['route']) : null,
                    'children' => $children,
                    // A parent item stays 'within' while a child is active.
                    'state' => match (true) {
                        $childIsCurrent => 'within',
                        self::matches($item['active'] ?? []) => 'current',
                        default => null,
                    },
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function visible(array $item): bool
    {
        // Group buttons without their own route stay visible as long as at
        // least one child is visible — prepare() decides that later.
        if (! isset($item['route'])) {
            return self::prepare($item['children'] ?? []) !== [];
        }

        if (! Route::has($item['route'])) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        return $permission === null || (Auth::check() && Auth::user()->hasPermission($permission));
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private static function matches(array $patterns): bool
    {
        return $patterns !== [] && request()->routeIs(...$patterns);
    }
}
