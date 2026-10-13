<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Language codes in the formats the standards expect:
 *   hreflang (BCP 47):  de, pt-BR, zh-Hant
 *   og:locale:          de_DE, pt_BR — language_TERRITORY
 */
final class LanguageCodes
{
    /** Usual territory of a language without one (og:locale needs both) */
    private const TERRITORIES = [
        'ar' => 'AR', 'bg' => 'BG', 'ca' => 'ES', 'cs' => 'CZ', 'da' => 'DK', 'de' => 'DE', 'el' => 'GR',
        'en' => 'US', 'es' => 'ES', 'et' => 'EE', 'fi' => 'FI', 'fr' => 'FR', 'he' => 'IL', 'hi' => 'IN',
        'hr' => 'HR', 'hu' => 'HU', 'id' => 'ID', 'it' => 'IT', 'ja' => 'JP', 'ko' => 'KR', 'lt' => 'LT',
        'lv' => 'LV', 'nb' => 'NO', 'nl' => 'NL', 'nn' => 'NO', 'no' => 'NO', 'pl' => 'PL', 'pt' => 'PT',
        'ro' => 'RO', 'ru' => 'RU', 'sk' => 'SK', 'sl' => 'SI', 'sr' => 'RS', 'sv' => 'SE', 'th' => 'TH',
        'tr' => 'TR', 'uk' => 'UA', 'vi' => 'VN', 'zh' => 'CN',
    ];

    /** hreflang value: "_" becomes "-", language lowercase, region uppercase */
    public static function hreflang(string $code): string
    {
        if ($code === 'x-default') {
            return $code;
        }

        $parts = preg_split('/[-_]/', trim($code)) ?: [$code];
        $out = [strtolower((string) array_shift($parts))];

        foreach ($parts as $part) {
            // Region (DE, BR, 419) uppercase, script (Hant) title case
            $out[] = strlen($part) === 4 ? ucfirst(strtolower($part)) : strtoupper($part);
        }

        return implode('-', $out);
    }

    /** og:locale value or null if the territory is unknown */
    public static function ogLocale(string $code): ?string
    {
        $parts = preg_split('/[-_]/', trim($code)) ?: [$code];
        $language = strtolower((string) ($parts[0] ?? ''));

        if (! preg_match('/^[a-z]{2,3}$/', $language)) {
            return null;
        }

        foreach (array_slice($parts, 1) as $part) {
            if (preg_match('/^[A-Za-z]{2}$/', $part)) {
                return $language.'_'.strtoupper($part);
            }
        }

        return isset(self::TERRITORIES[$language]) ? $language.'_'.self::TERRITORIES[$language] : null;
    }
}
