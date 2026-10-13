<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a 6-digit email verification code for the user.
     * Invalidates any previous unused codes.
     */
    public function generateEmailCode(User $user): string
    {
        // Invalidate previous unused codes
        DB::table('two_factor_codes')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $lifetime = (int) setting('security.two_factor_code_lifetime', '10');

        DB::table('two_factor_codes')->insert([
            'user_id' => $user->id,
            'code' => $code,
            'expires_at' => now()->addMinutes($lifetime),
            'created_at' => now(),
        ]);

        return $code;
    }

    /**
     * Verify an email code for the user.
     */
    public function verifyEmailCode(User $user, string $code): bool
    {
        $record = DB::table('two_factor_codes')
            ->where('user_id', $user->id)
            ->where('code', $code)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return false;
        }

        DB::table('two_factor_codes')
            ->where('id', $record->id)
            ->update(['used_at' => now()]);

        return true;
    }

    /**
     * Generate a new TOTP secret (20 bytes, Base32-encoded).
     */
    public function generateTotpSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Build the otpauth:// provisioning URI for QR code generation.
     */
    public function getTotpProvisioningUri(User $user, string $secret): string
    {
        $issuer = setting('site_name', config('app.name', 'InverysCMS'));
        $label = urlencode($issuer).':'.urlencode($user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ]);
    }

    /**
     * Verify a TOTP code against a secret (RFC 6238, SHA1, 30s, ±1 window).
     */
    public function verifyTotp(string $secret, string $code): bool
    {
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        $secretBytes = $this->base32Decode($secret);
        $timeStep = 30;
        $currentTime = time();

        // Check current and ±1 time windows
        for ($offset = -1; $offset <= 1; $offset++) {
            $counter = intdiv($currentTime, $timeStep) + $offset;
            $generated = $this->generateOtp($secretBytes, $counter);
            if (hash_equals($generated, $code)) {
                // Each code is valid only once (RFC 6238, 5.2) — otherwise an
                // intercepted code could be reused within the time window.
                // Cache::add() is atomic and fails if the code was already used.
                return Cache::add(
                    'two-factor:totp-used:'.hash('sha256', $secret).':'.$counter,
                    true,
                    $timeStep * 3
                );
            }
        }

        return false;
    }

    /**
     * Generate 8 recovery codes in XXXX-XXXX-XXXX format.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = sprintf(
                '%s-%s-%s',
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4))
            );
        }

        return $codes;
    }

    /**
     * Verify a recovery code for the user and remove it if valid.
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $codes = json_decode($user->two_factor_recovery_codes, true);
        if (! is_array($codes)) {
            return false;
        }

        $code = strtoupper(trim($code));
        foreach ($codes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Check if 2FA is required for the given user.
     * Globally enabled by default. Users can opt out individually in their profile.
     */
    public function isRequired(User $user): bool
    {
        if (setting('security.two_factor_enabled', 'on') !== 'on') {
            return false;
        }

        return $user->getSetting('two_factor_disabled') !== '1';
    }

    /**
     * Failed second-factor attempts are counted per account — regardless of IP
     * and channel (browser sign-in, API login). Otherwise anyone who knows the
     * password could brute-force the code from changing addresses.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    public function tooManyFailedAttempts(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->attemptsKey($user), self::MAX_FAILED_ATTEMPTS);
    }

    /** Seconds until the next allowed attempt */
    public function failedAttemptsAvailableIn(User $user): int
    {
        return RateLimiter::availableIn($this->attemptsKey($user));
    }

    public function recordFailedAttempt(User $user): void
    {
        // Lockout duration as for login (Security → lockout duration in minutes)
        RateLimiter::hit($this->attemptsKey($user), max(1, (int) setting('security.login_lockout_duration', '1')) * 60);
    }

    public function clearFailedAttempts(User $user): void
    {
        RateLimiter::clear($this->attemptsKey($user));
    }

    private function attemptsKey(User $user): string
    {
        return 'two-factor-attempts:'.$user->getKey();
    }

    /**
     * Determine the 2FA method for the user: 'totp' if confirmed, otherwise 'email'.
     */
    public function userMethod(User $user): string
    {
        return $user->hasTotpEnabled() ? 'totp' : 'email';
    }

    /**
     * Get the number of remaining recovery codes for a user.
     */
    public function remainingRecoveryCodes(User $user): int
    {
        $codes = json_decode($user->two_factor_recovery_codes, true);

        return is_array($codes) ? count($codes) : 0;
    }

    /**
     * Hash and store recovery codes on the user model.
     */
    public function storeRecoveryCodes(User $user, array $codes): void
    {
        $hashed = array_map(fn ($code) => Hash::make($code), $codes);
        $user->two_factor_recovery_codes = json_encode($hashed);
        $user->save();
    }

    /**
     * Generate a TOTP one-time password from secret bytes and counter.
     */
    private function generateOtp(string $secretBytes, int $counter): string
    {
        // Pack counter as 8-byte big-endian
        $counterBytes = pack('J', $counter);

        $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Encode binary data to Base32.
     */
    private function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $result;
    }

    /**
     * Decode Base32 string to binary data.
     */
    private function base32Decode(string $data): string
    {
        $data = strtoupper($data);
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos(self::BASE32_ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $result .= chr(bindec($byte));
        }

        return $result;
    }
}
