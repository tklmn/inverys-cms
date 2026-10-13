<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pages outside /backend that carry credentials or account data: log in,
 * registration, forgotten and new password, two-factor challenge, email
 * verification and change, password confirmation and the profile.
 *
 * Like the backend they get no plugin head or body hooks. Third-party
 * scripts injected there (analytics, marketing, consent services) would see
 * reset tokens and signed links in the address and could read typed passwords
 * — and an analytics account is usually readable by people without a CMS
 * account.
 */
final class AccountPages
{
    /** Request paths (relative, as for Request::is) of the account pages */
    public const PATHS = [
        'login', 'logout', 'register',
        'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge', 'two-factor-resend',
        'verify-email', 'verify-email/*', 'email/verification-notification',
        'confirm-password', 'password',
        'email-change', 'email-change/*',
        'profile', 'profile/*',
    ];

    /** Route names of the same pages — also covers them if a path ever moves */
    public const ROUTES = [
        'login', 'logout', 'register',
        'password.*', 'two-factor.*', 'verification.*',
        'email-change.*', 'profile.*',
    ];

    public static function matches(Request $request): bool
    {
        return $request->is(...self::PATHS) || $request->routeIs(...self::ROUTES);
    }

    /** Pages where frontend plugin hooks must not run: the backend, the API and the account pages */
    public static function withoutPluginHooks(Request $request): bool
    {
        return $request->is('backend', 'backend/*', 'api', 'api/*') || self::matches($request);
    }
}
