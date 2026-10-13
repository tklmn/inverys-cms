<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Content of /.well-known/security.txt (RFC 9116): where security vulnerabilities
 * of this website are reported.
 *
 * The details are maintained under Settings → Security (security.txt_*).
 * When left empty, the values from .env apply (SECURITY_TXT_*, config/cms.php).
 * Without a contact there is no file — a security.txt without a reachable
 * contact misleads reporters. Default: empty, so no file.
 */
final class SecurityTxt
{
    /** Selectable validity periods (days) */
    public const EXPIRES_OPTIONS = [30, 90, 180, 365];

    /**
     * Configured values (setting takes precedence over .env).
     *
     * @return array{contact: string, policy: string, encryption: string, preferred_languages: string, expires: string, expires_days: int}
     */
    public static function values(): array
    {
        $config = (array) config('cms.security_txt', []);
        $pick = fn (string $key, string $configKey) => trim((string) (setting('security.txt_'.$key) ?: ($config[$configKey] ?? '')));

        return [
            'contact' => $pick('contact', 'contact'),
            'policy' => $pick('policy', 'policy'),
            'encryption' => $pick('encryption', 'encryption'),
            'preferred_languages' => $pick('languages', 'preferred_languages'),
            'expires' => trim((string) ($config['expires'] ?? '')),
            'expires_days' => (int) (setting('security.txt_expires_days') ?: ($config['expires_days'] ?? 180)),
        ];
    }

    /** Finished file, or null if no valid contact is configured */
    public static function build(?array $values = null): ?string
    {
        $values ??= self::values();

        $contacts = array_values(array_filter(array_map(
            fn ($contact) => self::contactUri(trim($contact)),
            self::splitContacts($values['contact'])
        )));
        if ($contacts === []) {
            return null;
        }

        $lines = [];
        foreach ($contacts as $contact) {
            $lines[] = 'Contact: '.$contact;
        }

        $lines[] = 'Expires: '.self::expires($values)->format('Y-m-d\TH:i:s\Z');

        foreach (['encryption' => 'Encryption', 'policy' => 'Policy'] as $key => $field) {
            $value = self::httpsUri($values[$key]);
            if ($value !== null) {
                $lines[] = $field.': '.$value;
            }
        }

        $languages = self::languages($values['preferred_languages']);
        if ($languages !== '') {
            $lines[] = 'Preferred-Languages: '.$languages;
        }

        // Canonical URL from APP_URL, not from the request's Host header
        $lines[] = 'Canonical: '.rtrim((string) config('app.url'), '/').'/.well-known/security.txt';

        return implode("\n", $lines)."\n";
    }

    /** Contacts: one entry per line (also comma-separated, as in .env) */
    public static function splitContacts(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }

    /** Email address → mailto:, https URLs and tel: stay as they are; anything else is invalid */
    public static function contactUri(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'mailto:')) {
            $value = substr($value, 7);
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:'.$value;
        }

        if (preg_match('/^tel:\+?[0-9 ()-]+$/i', $value)) {
            return $value;
        }

        return self::httpsUri($value);
    }

    /** RFC 9116: web URLs in security.txt must use https */
    public static function httpsUri(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! str_starts_with(strtolower($value), 'https://')
            || filter_var($value, FILTER_VALIDATE_URL) === false || preg_match('/\s/', $value)) {
            return null;
        }

        return $value;
    }

    /** Language tags (de, en, pt-BR), comma-separated — unknown ones are dropped */
    public static function languages(string $value): string
    {
        $tags = array_filter(
            array_map('trim', explode(',', $value)),
            fn ($tag) => (bool) preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $tag)
        );

        return implode(', ', $tags);
    }

    /** Fixed date from .env, otherwise a rolling "expires_days" from today */
    private static function expires(array $values): CarbonImmutable
    {
        if ($values['expires'] !== '') {
            try {
                return CarbonImmutable::parse($values['expires'], 'UTC')->endOfDay()->startOfSecond()->utc();
            } catch (\Throwable) {
                // Unreadable date: rolling period instead of a broken file
            }
        }

        $days = max(1, min(365, $values['expires_days']));

        return CarbonImmutable::now('UTC')->startOfDay()->addDays($days);
    }
}
