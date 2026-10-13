<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(
        private TwoFactorService $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Not authenticated → let other middleware handle it
        if (! Auth::check()) {
            return $next($request);
        }

        // 2FA globally disabled → pass through
        if (! $this->twoFactor->isRequired(Auth::user())) {
            return $next($request);
        }

        // Already passed in this session — and by exactly this account.
        // A mere flag would survive the next login (e.g. after the user's own
        // account was deleted) and would then apply to a different account.
        $verifiedId = $request->session()->get('two_factor_verified');
        if ($verifiedId !== null && ! is_bool($verifiedId) && (string) $verifiedId === (string) Auth::id()) {
            return $next($request);
        }

        // Whitelist: only the challenge itself and logout. The setup routes
        // (two-factor.toggle, .disable-totp, .regenerate-codes …) deliberately NOT —
        // otherwise someone with a stolen password could disable 2FA before the
        // challenge, generate new recovery codes or guess codes without throttling.
        if ($request->routeIs('two-factor.challenge', 'two-factor.verify', 'two-factor.resend', 'logout')) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
