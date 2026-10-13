<?php

use App\Services\Cms\EloquentSettingRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Encrypts the captcha providers' secret keys, which were previously stored in
 * plain text in the settings table (the captcha plugin stores them with
 * encrypt() from 1.1.0 on, type "encrypted" — like the mail-providers plugin).
 *
 * Idempotent: values that can already be decrypted or that have the
 * shape of a Laravel ciphertext are left alone. Other settings are
 * not touched. Deliberately without plugin classes — the migration also runs
 * when the captcha plugin is not active.
 */
return new class extends Migration
{
    private const KEYS = [
        'captcha.recaptcha_v3_secret_key',
        'captcha.turnstile_secret_key',
        'captcha.hcaptcha_secret_key',
        'captcha.captchafox_secret_key',
        'captcha.friendly_captcha_secret_key',
    ];

    public function up(): void
    {
        foreach (DB::table('settings')->whereIn('key', self::KEYS)->get(['id', 'value', 'type']) as $row) {
            $value = (string) ($row->value ?? '');
            $update = [];

            if ($value !== '' && ! $this->isEncrypted($value)) {
                $update['value'] = encrypt($value);
            }

            if ($row->type !== 'encrypted') {
                $update['type'] = 'encrypted';
            }

            if ($update !== []) {
                DB::table('settings')->where('id', $row->id)->update($update);
            }
        }

        EloquentSettingRepository::clearCache();
    }

    public function down(): void
    {
        foreach (DB::table('settings')->whereIn('key', self::KEYS)->get(['id', 'value']) as $row) {
            $value = (string) ($row->value ?? '');
            $update = ['type' => 'string'];

            if ($value !== '') {
                try {
                    $plain = decrypt($value);
                    $update['value'] = is_string($plain) ? $plain : '';
                } catch (DecryptException) {
                    // Plain text or no longer decryptable: leave as is
                }
            }

            DB::table('settings')->where('id', $row->id)->update($update);
        }

        EloquentSettingRepository::clearCache();
    }

    private function isEncrypted(string $value): bool
    {
        try {
            // Only check whether it can be decrypted — without unserialize
            decrypt($value, false);

            return true;
        } catch (DecryptException) {
            // Do not encrypt a ciphertext made with an old APP_KEY a second time
            $decoded = base64_decode($value, true);
            $payload = $decoded === false ? null : json_decode($decoded, true);

            return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
        }
    }
};
