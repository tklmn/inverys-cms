<?php

namespace Plugins\Captcha\Support;

use Illuminate\Contracts\Encryption\DecryptException;

/**
 * The captcha providers' secret keys are stored encrypted in the
 * settings — just like the password and client secrets in the
 * mail-providers plugin (encrypt()/decrypt(), type "encrypted").
 *
 * A value that cannot be decrypted (e.g. written to the database
 * directly) counts as plain text — so no captcha breaks.
 */
final class CaptchaSecrets
{
    /** Setting keys of all provider secrets */
    public const KEYS = [
        'captcha.recaptcha_v3_secret_key',
        'captcha.turnstile_secret_key',
        'captcha.hcaptcha_secret_key',
        'captcha.captchafox_secret_key',
        'captcha.friendly_captcha_secret_key',
    ];

    public const TYPE = 'encrypted';

    /**
     * Stores a secret encrypted. Empty values never arrive here:
     * an empty form field means "leave unchanged".
     */
    public static function store(string $key, string $value): void
    {
        cms()->settings()->set($key, encrypt($value), self::TYPE);
    }

    /**
     * Reads a secret in plain text (for verification with the provider).
     */
    public static function get(string $key): string
    {
        return self::reveal((string) cms()->settings()->get($key, ''));
    }

    /**
     * Decrypts a stored value. Plain text comes back unchanged; a
     * ciphertext that cannot be decrypted (APP_KEY changed) counts as not configured —
     * sending it to the provider would achieve nothing.
     */
    public static function reveal(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $plain = decrypt($value);
        } catch (DecryptException) {
            return self::looksEncrypted($value) ? '' : $value;
        }

        return is_string($plain) ? $plain : '';
    }

    /**
     * Does the value have the shape of a Laravel ciphertext (base64 JSON with iv/value/mac)?
     */
    public static function looksEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        $payload = $decoded === false ? null : json_decode($decoded, true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
