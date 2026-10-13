<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Mail\TwoFactorCodeMail;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Discard the 2FA state of an earlier login in this session:
        // regenerate() keeps the data, and a passed challenge of another
        // account must not apply to this one
        $request->session()->forget(['two_factor_verified', 'two_factor_code_sent', 'two_factor_pending_secret']);

        // Check if 2FA is required
        $user = Auth::user();
        $twoFactor = app(TwoFactorService::class);

        if ($twoFactor->isRequired($user)) {
            $method = $twoFactor->userMethod($user);

            if ($method === 'email') {
                $code = $twoFactor->generateEmailCode($user);
                $lifetime = (int) setting('security.two_factor_code_lifetime', '10');
                Mail::to($user)->send(new TwoFactorCodeMail($code, $lifetime));
                $request->session()->put('two_factor_code_sent', true);
            }

            return redirect()->route('two-factor.challenge');
        }

        // Always redirect to frontend by default.
        // If the user was trying to reach a backend URL, intended() will send them there.
        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
