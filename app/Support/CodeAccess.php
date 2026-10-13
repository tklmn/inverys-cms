<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Permissions that allow program code to be put on the server.
 *
 * Theme files (.blade.php is PHP), plugin packages and theme packages run
 * as code in the CMS. Whoever may do that can do anything — therefore:
 *
 *   - admins only (even if another role still carries the permission)
 *   - role management grants these permissions only to the admin role
 *   - can be switched off per server: CMS_CODE_EDITING=false (e.g. in production,
 *     when changes should only come via Git/deploy)
 */
final class CodeAccess
{
    /** Permissions that only the admin role can have */
    public const ADMIN_ONLY = ['edit-theme-files', 'manage-plugins', 'manage-updates'];

    public static function isAdminOnly(string $permission): bool
    {
        return in_array($permission, self::ADMIN_ONLY, true);
    }

    /** May code files be edited and packages uploaded on this server at all? */
    public static function enabled(): bool
    {
        return (bool) config('cms.code_editing', true);
    }

    /** Upload/delete packages, edit theme files: admin and enabled */
    public static function allowed(?User $user): bool
    {
        return self::enabled() && $user !== null && $user->hasRole('admin');
    }

    /** Reason for a greyed-out button, or null */
    public static function blockedReason(?User $user): ?string
    {
        return match (true) {
            ! self::enabled() => __('translation.code_access.disabled'),
            ! $user?->hasRole('admin') => __('translation.code_access.admin_only'),
            default => null,
        };
    }
}
