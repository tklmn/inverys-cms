<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangedNoticeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Change the email address in one's own profile — in three steps:
 *
 *   1. Profile: new address + current password → confirmation link to the
 *      NEW address. Until then the old one applies.
 *   2. Link confirmed → address switched; the OLD address receives a
 *      message with "This wasn't me" (valid for 7 days).
 *   3. "This wasn't me" → old address restored, password locked, all
 *      sessions and API tokens ended, reset link sent to the old address.
 *
 * Anyone who no longer has access to the old address does not need it: password
 * and new address are enough. Admins change addresses directly in user
 * management (without this flow).
 *
 * Only the pending request is stored (user setting email_change);
 * the links are signed and bound to a random nonce that becomes invalid
 * when the request is discarded or replaced by a new one.
 */
final class EmailChange
{
    public const SETTING = 'email_change';

    public const CONFIRM_HOURS = 24;

    public const REVERT_DAYS = 7;

    /** @return array{email: string, nonce: string}|null */
    public static function pending(User $user): ?array
    {
        $data = json_decode((string) $user->getSetting(self::SETTING, ''), true);

        return is_array($data) && isset($data['email'], $data['nonce']) ? $data : null;
    }

    public static function request(User $user, string $newEmail): void
    {
        $nonce = Str::random(40);
        $user->setSetting(self::SETTING, json_encode(['email' => $newEmail, 'nonce' => $nonce]));

        $url = URL::temporarySignedRoute('email-change.confirm', now()->addHours(self::CONFIRM_HOURS), [
            'user' => $user->getKey(),
            'nonce' => $nonce,
        ]);

        Mail::to($newEmail)->send(new EmailChangeConfirmMail($user, $newEmail, $url, self::CONFIRM_HOURS));
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

        $taken = User::withTrashed()->where('email', $pending['email'])->whereKeyNot($user->getKey())->exists();

        return $taken ? null : $pending['email'];
    }

    public static function confirm(User $user, string $nonce): bool
    {
        $newEmail = self::canConfirm($user, $nonce);
        if ($newEmail === null) {
            return false;
        }

        $oldEmail = $user->email;
        $user->forceFill(['email' => $newEmail, 'email_verified_at' => now()])->save();
        self::cancel($user);

        $revertUrl = URL::temporarySignedRoute('email-change.revert', now()->addDays(self::REVERT_DAYS), [
            'user' => $user->getKey(),
            'old' => $oldEmail,
            'new' => $newEmail,
        ]);
        Mail::to($oldEmail)->send(new EmailChangedNoticeMail($user, $oldEmail, $newEmail, $revertUrl, self::REVERT_DAYS));

        return true;
    }

    /** Only as long as the address has not been changed again since and the old one is available. */
    public static function canRevert(User $user, string $old, string $new): bool
    {
        return $user->email === $new
            && ! User::withTrashed()->where('email', $old)->whereKeyNot($user->getKey())->exists();
    }

    public static function revert(User $user, string $old, string $new): bool
    {
        if (! self::canRevert($user, $old, $new)) {
            return false;
        }

        // Whoever changed the address knew the password — it is no longer valid
        $user->forceFill(['email' => $old, 'password' => Hash::make(Str::random(64))])->save();
        self::cancel($user);
        $user->endOtherSessions();
        $user->revokeApiTokens();

        Password::broker()->sendResetLink(['email' => $old]);

        return true;
    }
}
