<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Notices about running this installation (Dashboard → System & security).
 *
 * Only things that can be checked at a glance and fixed with a click.
 * Each check returns tone, title, explanation and — if the user has the
 * permission — the place where it can be changed.
 */
final class SystemHealth
{
    /**
     * @return list<array{key: string, tone: string, icon: string, title: string, text: string, href: ?string, warning: bool}>
     */
    public static function checks(): array
    {
        $user = auth()->user();
        $can = fn (string $permission) => $user !== null && $user->hasPermission($permission);
        $production = app()->isProduction();
        $checks = [];

        if (! SeoSettings::allowIndexing()) {
            $checks[] = [
                'key' => 'indexing',
                'tone' => 'caution',
                'icon' => 'eye-off',
                'title' => __('translation.dashboard.health.indexing_title'),
                'text' => __('translation.dashboard.health.indexing_text'),
                'href' => $can('manage-seo-settings') ? route('backend.settings.seo') : null,
                'warning' => true,
            ];
        }

        if (config('app.debug')) {
            $checks[] = [
                'key' => 'debug',
                // Desired on a test environment, a data leak in production
                'tone' => $production ? 'critical' : 'caution',
                'icon' => 'triangle-alert',
                'title' => __('translation.system.info.debug_on_title'),
                'text' => __('translation.system.info.debug_on_text'),
                'href' => null,
                'warning' => true,
            ];
        }

        if ($production && str_starts_with(strtolower((string) config('app.url')), 'http://')) {
            $checks[] = [
                'key' => 'https',
                'tone' => 'caution',
                'icon' => 'unlock',
                'title' => __('translation.dashboard.health.https_title'),
                'text' => __('translation.dashboard.health.https_text'),
                'href' => null,
                'warning' => true,
            ];
        }

        // Not a warning: a security.txt is recommended, not required
        if (SecurityTxt::build() === null) {
            $checks[] = [
                'key' => 'security_txt',
                'tone' => 'neutral',
                'icon' => 'info',
                'title' => __('translation.dashboard.health.security_txt_title'),
                'text' => __('translation.dashboard.health.security_txt_text'),
                'href' => $can('manage-security-settings') ? route('backend.settings.security') : null,
                'warning' => false,
            ];
        }

        return $checks;
    }
}
