<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
    ) {}

    /**
     * Show the 2FA challenge form.
     * Sends an email code when the user's method is email.
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $method = $this->twoFactor->userMethod($user);

        // Send email code if not yet sent this page load
        if ($method === 'email' && ! $request->session()->get('two_factor_code_sent')) {
            $this->sendEmailCode($user);
            $request->session()->put('two_factor_code_sent', true);
        }

        return view('auth.two-factor-challenge', [
            'method' => $method,
        ]);
    }

    /**
     * Verify the submitted 2FA code.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();
        $code = trim($request->input('code'));
        $useRecovery = $request->boolean('use_recovery');

        // Shared budget with the API login, per account
        if ($this->twoFactor->tooManyFailedAttempts($user)) {
            return back()->withErrors(['code' => __('auth.two_factor.too_many_attempts', [
                'seconds' => $this->twoFactor->failedAttemptsAvailableIn($user),
            ])]);
        }

        $valid = false;

        if ($useRecovery) {
            $valid = $this->twoFactor->verifyRecoveryCode($user, $code);
            if ($valid) {
                $remaining = $this->twoFactor->remainingRecoveryCodes($user);
                session()->flash('status', __('auth.two_factor.recovery_used', ['remaining' => $remaining]));

                // If all recovery codes are used, fall back to email method
                if ($remaining === 0) {
                    $user->update([
                        'two_factor_method' => null,
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ]);
                    session()->flash('warning', __('auth.two_factor.all_recovery_used'));
                }
            }
        } else {
            $method = $this->twoFactor->userMethod($user);
            $valid = match ($method) {
                'totp' => $this->twoFactor->verifyTotp($user->two_factor_secret, $code),
                default => $this->twoFactor->verifyEmailCode($user, $code),
            };
        }

        if (! $valid) {
            $this->twoFactor->recordFailedAttempt($user);

            return back()->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        $this->twoFactor->clearFailedAttempts($user);
        // Bound to the account, not just "passed" — otherwise the passed
        // challenge would, after the next login in the same session, also
        // apply to another account (see EnsureTwoFactorVerified)
        $request->session()->put('two_factor_verified', $user->getAuthIdentifier());
        $request->session()->forget('two_factor_code_sent');

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Resend an email code (rate limited: 1 per minute).
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $key = 'two-factor-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['code' => __('auth.two_factor.resend_throttle', ['seconds' => $seconds])]);
        }

        RateLimiter::hit($key, 60);
        $this->sendEmailCode($user);

        return back()->with('status', __('auth.two_factor.code_resent'));
    }

    private function sendEmailCode($user): void
    {
        $code = $this->twoFactor->generateEmailCode($user);
        $lifetime = (int) setting('security.two_factor_code_lifetime', '10');

        Mail::to($user)->send(new TwoFactorCodeMail($code, $lifetime));
    }
}
