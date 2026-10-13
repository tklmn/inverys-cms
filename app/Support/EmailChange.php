<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangedNoticeMail;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Change the email address in one's own profile — in three steps:
 *
 *   1. Profile: new address + current password → confirmation link to the
 *      NEW address. Until then the old one applies.
 *   2. Link confirmed → address switched; the OLD address receives a
 *      message with "This wasn't me" (valid for 7 days).
 *   3. "This wasn't me" → old address restored, password locked, an
 *      authenticator app removed, all sessions and API tokens ended, reset
 *      link sent to the old address.
 *
 * Anyone who no longer has access to the old address does not need it: password
 * and new address are enough. Admins change addresses directly in user
 * management (without this flow).
 *
 * The pending request is stored in the user setting email_change; the
 * confirmation link is signed and bound to a random nonce that becomes
 * invalid when the request is discarded or replaced by a new one.
 *
 * The revert window (user setting email_change_revert) remembers the address
 * the account had before the first change. Further changes within the window
 * keep it, and their notices go to that address as well, so a second quick
 * change cannot move the "This wasn't me" link to an address the person who
 * changed it controls. While the window is open, no other account may take
 * that address (registration, user management, profile), so the revert
 * cannot be blocked by occupying it.
 */
final class EmailChange
{
    public const SETTING = 'email_change';

    public const REVERT_SETTING = 'email_change_revert';

    public const CONFIRM_HOURS = 24;

    public const REVERT_DAYS = 7;

    /** @return array{email: string, nonce: string}|null */
    public static function pending(User $user): ?array
    {
        $data = json_decode((string) $user->getSetting(self::SETTING, ''), true);

        return is_array($data) && isset($data['email'], $data['nonce']) ? $data : null;
    }

    /**
     * A taken or reserved address is stored as pending like any other, so the
     * profile looks the same, but gets no mail: the answer must not tell
     * which addresses have an account, and the owner of that address is not
     * bothered. canConfirm() refuses it anyway.
     */
    public static function request(User $user, string $newEmail): void
    {
        $nonce = Str::random(40);
        $user->setSetting(self::SETTING, json_encode(['email' => $newEmail, 'nonce' => $nonce]));

        if (self::isTaken($newEmail, $user->getKey())) {
            return;
        }

        $url = URL::temporarySignedRoute('email-change.confirm', now()->addHours(self::CONFIRM_HOURS), [
            'user' => $user->getKey(),
            'nonce' => $nonce,
        ]);

        // Sent once the response is out, so its duration does not set taken
        // addresses apart either
        defer(function () use ($user, $newEmail, $url) {
            try {
                Mail::to($newEmail)->send(new EmailChangeConfirmMail($user, $newEmail, $url, self::CONFIRM_HOURS));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    public static function cancel(User $user): void
    {
        $user->forgetSetting(self::SETTING);
    }

    /** Valid: pending request with this nonce, address still available. */
    public static function canConfirm(User $user, string $nonce): ?string
    {
        $pending = self::pending($user);
        if (! $pending || ! hash_equals($pending['nonce'], $nonce)) {
            return null;
        }

        return self::isTaken($pending['email'], $user->getKey()) ? null : $pending['email'];
    }

    /** Does another account (also a deleted one) use $email, or may it return to it? */
    public static function isTaken(string $email, int|string|null $exceptUserId = null): bool
    {
        $normalized = strtolower(trim($email));

        return User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists()
            || self::isReserved($normalized, $exceptUserId);
    }

    public static function confirm(User $user, string $nonce): bool
    {
        $newEmail = self::canConfirm($user, $nonce);
        if ($newEmail === null) {
            return false;
        }

        $oldEmail = $user->email;

        // The first change opens the window and fixes the address to go back
        // to; a later change within it keeps that address and only extends it
        $window = self::revertWindow($user);
        $window = [
            'email' => $window['email'] ?? $oldEmail,
            'nonce' => $window['nonce'] ?? Str::random(40),
            'until' => now()->addDays(self::REVERT_DAYS)->getTimestamp(),
        ];
        $user->setSetting(self::REVERT_SETTING, json_encode($window));

        $user->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();
        self::cancel($user);

        $revertUrl = URL::temporarySignedRoute('email-change.revert', now()->addDays(self::REVERT_DAYS), [
            'user' => $user->getKey(),
            'nonce' => $window['nonce'],
        ]);
        Mail::to($window['email'])->send(new EmailChangedNoticeMail($user, $oldEmail, $newEmail, $revertUrl, self::REVERT_DAYS));

        return true;
    }

    /** @return array{email: string, nonce: string, until: int}|null the open revert window */
    public static function revertWindow(User $user): ?array
    {
        return self::openWindow(json_decode((string) $user->getSetting(self::REVERT_SETTING, ''), true));
    }

    /**
     * Is $email the address another account may still return to? Nobody else
     * may take it until that account's revert window has closed.
     */
    public static function isReserved(string $email, int|string|null $exceptUserId = null): bool
    {
        $email = strtolower(trim($email));

        return UserSetting::query()
            ->where('key', self::REVERT_SETTING)
            ->when($exceptUserId !== null, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->pluck('value')
            ->contains(fn ($value) => strtolower((string) (self::openWindow(json_decode((string) $value, true))['email'] ?? '')) === $email);
    }

    /** The address to restore, if the link is valid and the window still open. */
    public static function canRevert(User $user, string $nonce): ?string
    {
        $window = self::revertWindow($user);
        if (! $window || ! hash_equals($window['nonce'], $nonce)) {
            return null;
        }

        // Reserved while the window is open; checked again for accounts that
        // took the address before the reservation existed
        $taken = User::withTrashed()->where('email', $window['email'])->whereKeyNot($user->getKey())->exists();

        return $taken ? null : $window['email'];
    }

    public static function revert(User $user, string $nonce): bool
    {
        $old = self::canRevert($user, $nonce);
        if ($old === null) {
            return false;
        }

        // Whoever changed the address knew the password — it is no longer
        // valid. They may have set up an authenticator app as well: codes go
        // to the restored address again until a new app is set up.
        $user->forceFill([
            'email' => $old,
            'password' => Hash::make(Str::random(64)),
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        self::cancel($user);
        $user->forgetSetting(self::REVERT_SETTING);
        $user->endOtherSessions();
        $user->revokeApiTokens();

        Password::broker()->sendResetLink(['email' => $old]);

        return true;
    }

    /**
     * @param  mixed  $data  the decoded setting value
     * @return array{email: string, nonce: string, until: int}|null
     */
    private static function openWindow(mixed $data): ?array
    {
        if (! is_array($data) || ! is_string($data['email'] ?? null) || ! is_string($data['nonce'] ?? null) || ! is_int($data['until'] ?? null)) {
            return null;
        }

        return $data['until'] >= now()->getTimestamp() ? $data : null;
    }
}
