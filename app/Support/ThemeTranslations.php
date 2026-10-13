<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ThemeManager;

/**
 * Translated theme texts (multilingual plugin).
 *
 * Only settings of type text/textarea (e.g. the copyright text in the
 * footer) and the block regions are translatable. Colors, images and
 * toggles apply to all languages. Storage: SettingTranslations.
 */
final class ThemeTranslations
{
    public const TEXT_TYPES = ['text', 'textarea'];

    /** Value in the current content language — otherwise the original value. */
    public static function setting(string $slug, string $key, mixed $value): mixed
    {
        if (! SettingTranslations::translating() || ! self::isText($slug, $key)) {
            return $value;
        }

        return SettingTranslations::lookup("theme.{$slug}.{$key}") ?? $value;
    }

    /** Region (block JSON) in the current content language — otherwise the original. */
    public static function region(string $slug, string $key, mixed $json): mixed
    {
        if (! SettingTranslations::translating()) {
            return $json;
        }

        return SettingTranslations::lookup("theme.{$slug}.region.{$key}") ?? $json;
    }

    public static function isText(string $slug, string $key): bool
    {
        $schema = app(ThemeManager::class)->getSettingsSchema($slug)[$key] ?? null;

        return in_array($schema['type'] ?? 'text', self::TEXT_TYPES, true);
    }

    /** Read a setting's translated raw value for an editor. */
    public static function translated(string $settingKey, string $locale): ?string
    {
        return SettingTranslations::translated($settingKey, $locale);
    }

    public static function save(string $settingKey, string $locale, ?string $value, string $originalValue, string $type): void
    {
        SettingTranslations::save($settingKey, $locale, $value, $originalValue, $type);
    }
}
