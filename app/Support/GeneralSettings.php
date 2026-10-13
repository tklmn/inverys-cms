<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ThemeManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Registry of the general settings (Settings → General).
 *
 * Every setting has exactly ONE place where it is edited. The general
 * list therefore shows only the keys of this registry — not "everything
 * except known prefixes": disabled plugins do not register their prefix,
 * and their settings (SMTP user, blog prefix …) would show up here,
 * without any validation.
 *
 * Keys with their own page (active theme → Themes, default language →
 * Languages) are still listed, but locked with a link — you should
 * see that they exist and where they are managed. They can only be saved
 * there, not via the ID or the REST API either.
 *
 * Precedence: the user's profile over the website default (`user_override`).
 */
final class GeneralSettings
{
    /** Order = order in the list */
    public const KEYS = [
        'site_name',
        'language',
        'timezone',
        'date_format',
        'theme',
        'active_theme',
        'favicon',
        'revisions.max_per_model',
        'html.minify',
        'html.protect_emails',
    ];

    private const ICONS = [
        'site_name' => 'type',
        'language' => 'languages',
        'timezone' => 'clock',
        'date_format' => 'calendar',
        'theme' => 'monitor',
        'active_theme' => 'palette',
        'favicon' => 'image',
        'revisions.max_per_model' => 'history',
        'html.minify' => 'zap',
        'html.protect_emails' => 'mail',
    ];

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * Description of a setting.
     *
     * @return array{key: string, icon: string, label: string, help: string, input: string, user_override: bool, managed: ?array{route: string, label: string, permission: ?string}}|null
     */
    public static function definition(string $key): ?array
    {
        if (! self::has($key)) {
            return null;
        }

        $base = ['key' => $key, 'icon' => self::ICONS[$key], 'input' => 'text', 'user_override' => false, 'managed' => null];

        return match ($key) {
            'site_name' => [
                ...$base,
                'label' => __('translation.settings.general_keys.site_name'),
                'help' => __('translation.settings.general_help.site_name')
                    .(Route::has('backend.multilingual.texts') ? ' '.__('translation.settings.general_help.site_name_translations') : ''),
            ],
            'language' => [
                ...$base,
                'label' => __('translation.settings.general_keys.language'),
                'input' => 'select',
                'user_override' => true,
                // With the multilingual plugin the default language belongs on the
                // Languages page: there it also determines which language needs no
                // prefix in the URL. One place for the value, and an
                // explanation here.
                'managed' => Route::has('backend.multilingual.settings') ? [
                    'route' => 'backend.multilingual.settings',
                    'label' => __('multilingual::ml.title'),
                    'permission' => 'multilingual-settings',
                ] : null,
                'help' => Route::has('backend.multilingual.settings')
                    ? __('translation.settings.general_help.language_managed')
                    : __('translation.settings.general_help.language'),
            ],
            'timezone' => [
                ...$base,
                'label' => __('translation.settings.general_keys.timezone'),
                'input' => 'select',
                'user_override' => true,
                'help' => __('translation.settings.general_help.timezone'),
            ],
            'date_format' => [
                ...$base,
                'label' => __('translation.settings.general_keys.date_format'),
                'user_override' => true,
                'help' => __('translation.settings.general_help.date_format'),
            ],
            'theme' => [
                ...$base,
                'label' => __('translation.settings.general_keys.theme'),
                'input' => 'select',
                'user_override' => true,
                'help' => __('translation.settings.general_help.theme'),
            ],
            'active_theme' => [
                ...$base,
                'label' => __('translation.settings.general_keys.active_theme'),
                'input' => 'select',
                'user_override' => true,
                // Activate only via ThemeManager::activate() — it checks whether
                // the theme exists; free text here could enter a missing one
                'managed' => [
                    'route' => 'backend.themes.index',
                    'label' => __('translation.navigation.themes'),
                    'permission' => 'manage-themes',
                ],
                'help' => __('translation.settings.general_help.active_theme'),
            ],
            'favicon' => [
                ...$base,
                'label' => __('translation.settings.general_keys.favicon'),
                'help' => __('translation.settings.general_help.favicon'),
            ],
            'revisions.max_per_model' => [
                ...$base,
                'label' => __('translation.settings.general_keys.revisions'),
                'input' => 'number',
                'help' => __('translation.settings.general_help.revisions'),
            ],
            'html.minify' => [
                ...$base,
                'label' => __('translation.settings.general_keys.html_minify'),
                'input' => 'select',
                'help' => __('translation.settings.general_help.html_minify'),
            ],
            'html.protect_emails' => [
                ...$base,
                'label' => __('translation.settings.general_keys.html_protect_emails'),
                'input' => 'select',
                'help' => __('translation.settings.general_help.html_protect_emails'),
            ],
        };
    }

    /** Where is the setting managed, if not here? null = here. */
    public static function managedBy(string $key): ?array
    {
        return self::definition($key)['managed'] ?? null;
    }

    public static function isEditable(string $key): bool
    {
        return self::has($key) && self::managedBy($key) === null;
    }

    /**
     * Choice values (value => label) or null for free input.
     *
     * @return array<string, string>|null
     */
    public static function options(string $key): ?array
    {
        return match ($key) {
            'language' => array_map(fn ($meta) => $meta['native'], app(Locales::class)->all()),
            'timezone' => collect(timezone_identifiers_list())
                ->mapWithKeys(fn ($zone) => [$zone => str_replace('_', ' ', $zone)])
                ->all(),
            'theme' => [
                'light' => __('translation.users.theme_light'),
                'dark' => __('translation.users.theme_dark'),
            ],
            'html.minify' => [
                '1' => __('translation.settings.html_minify_on'),
                '0' => __('translation.settings.html_minify_off'),
            ],
            'html.protect_emails' => [
                '1' => __('translation.settings.html_minify_on'),
                '0' => __('translation.settings.html_minify_off'),
            ],
            'active_theme' => collect(app(ThemeManager::class)->all())
                ->mapWithKeys(fn ($theme, $slug) => [$slug => __($theme['name'] ?? ucfirst((string) $slug))])
                ->all(),
            default => null,
        };
    }

    /** Validation rules for the value — the same for backend and REST API */
    public static function rules(string $key): array
    {
        $options = self::options($key);

        return match ($key) {
            'site_name' => ['required', 'string', 'max:255'],
            // Empty: the format of the UI language (App\Support\Dates)
            'date_format' => ['nullable', 'string', 'max:50'],
            'favicon' => ['required', 'string', 'max:255', 'regex:/^(?!.*\.\.)[A-Za-z0-9._\/-]+$/'],
            'revisions.max_per_model' => ['required', 'integer', 'min:0', 'max:1000'],
            default => $options !== null
                ? ['required', 'string', Rule::in(array_map('strval', array_keys($options)))]
                : ['required', 'string', 'max:1000'],
        };
    }

    /** Value for display: language name instead of code, "Light" instead of light */
    public static function display(string $key, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (self::options($key)[$value] ?? $value);
    }

    /**
     * Notice when the default appearance does not take effect in the frontend:
     * the active theme has no dark mode.
     */
    public static function darkModeWarning(): ?string
    {
        $themes = app(ThemeManager::class);
        $slug = $themes->getActive() ?? 'default';
        $theme = $themes->get($slug);

        if ($theme === null || ($theme['supports_dark_mode'] ?? true)) {
            return null;
        }

        return __('translation.settings.general_help.theme_no_dark', ['theme' => __($theme['name'] ?? $slug)]);
    }
}
