<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class TwoFactorSetupController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
    ) {}

    /**
     * Toggle 2FA on/off for the current user.
     * Enabling: no verification needed.
     * Disabling: requires verification via current 2FA method.
     */
    public function toggle(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $isDisabled = $user->getSetting('two_factor_disabled') === '1';

        if ($isDisabled) {
            $user->setSetting('two_factor_disabled', '0');

            return redirect()->route('profile.edit')
                ->with('status', __('auth.two_factor.user_enabled'));
        }

        // Disabling requires 2FA verification
        if (! $this->verifyCode($request, $user)) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        $user->setSetting('two_factor_disabled', '1');
        $user->update([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', __('auth.two_factor.user_disabled'));
    }

    /**
     * Start the TOTP setup: generate a secret and keep it pending in the session.
     *
     * Like disabling, switching the second factor requires a code from the
     * current method (email) — otherwise a hijacked session could bind the
     * login to its own app and then disable 2FA with that app's code. An
     * active app is not replaced: disable it first. Until confirmed, the new
     * secret lives only in this session; the previous setting stays in
     * effect until then.
     */
    public function enableTotp(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $this->twoFactor->isRequired($user) || $user->hasTotpEnabled()) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.totp_switch_unavailable')]);
        }

        if (! $this->verifyCode($request, $user)) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        $secret = $this->twoFactor->generateTotpSecret();
        $request->session()->put('two_factor_pending_secret', $secret);

        $this->flashSetup($request, $user, $secret);

        return redirect()->route('profile.edit');
    }

    /**
     * Confirm the TOTP setup by validating a code from the authenticator app.
     */
    public function confirmTotp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $secret = $request->session()->get('two_factor_pending_secret');

        if (! is_string($secret) || $secret === '' || $user->hasTotpEnabled()) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.setup_not_started')]);
        }

        if (! $this->twoFactor->verifyTotp($secret, $request->input('code'))) {
            // Show the QR code again for another attempt
            $this->flashSetup($request, $user, $secret);

            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        // Generate and store recovery codes
        $codes = $this->twoFactor->generateRecoveryCodes();
        $this->twoFactor->storeRecoveryCodes($user, $codes);

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_method' => 'totp',
            'two_factor_confirmed_at' => now(),
        ]);
        $request->session()->forget('two_factor_pending_secret');

        $request->session()->flash('two_factor_recovery_codes', $codes);

        return redirect()->route('profile.edit')
            ->with('status', __('auth.two_factor.totp_enabled'));
    }

    /** Show the secret and QR code for setup once */
    private function flashSetup(Request $request, $user, string $secret): void
    {
        $request->session()->flash('two_factor_secret', $secret);
        $request->session()->flash('two_factor_qr_uri', $this->twoFactor->getTotpProvisioningUri($user, $secret));
        $request->session()->flash('two_factor_setup', true);
    }

    /**
     * Disable TOTP and fall back to email method (requires TOTP code).
     */
    public function disableTotp(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = Auth::user();

        if (! $this->twoFactor->verifyTotp($user->two_factor_secret, $request->input('code'))) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        $user->update([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', __('auth.two_factor.totp_disabled'));
    }

    /**
     * Regenerate recovery codes (requires TOTP code).
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = Auth::user();

        if (! $this->twoFactor->verifyTotp($user->two_factor_secret, $request->input('code'))) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('auth.two_factor.invalid_code')]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $this->twoFactor->storeRecoveryCodes($user, $codes);

        $request->session()->flash('two_factor_recovery_codes', $codes);

        return redirect()->route('profile.edit')
            ->with('status', __('auth.two_factor.codes_regenerated'));
    }

    /**
     * Send an email verification code for profile 2FA actions.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $code = $this->twoFactor->generateEmailCode($user);
        $lifetime = (int) setting('security.two_factor_code_lifetime', '10');

        Mail::to($user)->send(new TwoFactorCodeMail($code, $lifetime));

        return redirect()->route('profile.edit')
            ->with('status', __('auth.two_factor.code_resent'));
    }

    /**
     * Verify a 2FA code based on the user's current method.
     */
    private function verifyCode(Request $request, $user): bool
    {
        $request->validate(['code' => 'required|string|size:6']);

        $method = $this->twoFactor->userMethod($user);

        return $method === 'totp'
            ? $this->twoFactor->verifyTotp($user->two_factor_secret, $request->input('code'))
            : $this->twoFactor->verifyEmailCode($user, $request->input('code'));
    }
}
