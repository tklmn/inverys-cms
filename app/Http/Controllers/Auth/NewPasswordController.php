<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /** Session key holding token and address of the reset link that was opened */
    public const SESSION_KEY = 'password_reset';

    /**
     * Take the reset link from the email: token and address go into the
     * session, the browser continues on an address without them. Otherwise
     * the token would stay in the history, in the referrer and in the page
     * URL that scripts and analytics read.
     */
    public function create(Request $request, string $token): RedirectResponse
    {
        $email = $request->query('email');

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'email' => is_string($email) ? $email : '',
        ]);

        return redirect()->route('password.reset.form');
    }

    /**
     * Display the password reset view for the link kept in the session.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $reset = $request->session()->get(self::SESSION_KEY);

        if (! is_array($reset) || ! is_string($reset['token'] ?? null) || $reset['token'] === '') {
            return redirect()->route('password.request')
                ->withErrors(['email' => __(Password::INVALID_TOKEN)]);
        }

        return view('auth.reset-password', [
            'token' => $reset['token'],
            'email' => (string) ($reset['email'] ?? ''),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Reset = end all previous sessions and API tokens
                $user->endOtherSessions();
                $user->revokeApiTokens();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->with('status', __($status));
        }

        // Every failure gets the same answer: the broker reports an unknown
        // address (INVALID_USER) differently from a wrong token for an existing
        // account (INVALID_TOKEN), which would tell whether the address has an
        // account. The broker's timebox already keeps the response time alike.
        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __(Password::INVALID_TOKEN)]);
    }
}
