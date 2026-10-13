<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

use function Illuminate\Support\defer;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Sent once the response is out: the broker's timebox does not cover a
        // slow mail server, so the response time would tell which addresses
        // have an account
        $credentials = $request->only('email');
        defer(function () use ($credentials) {
            try {
                Password::sendResetLink($credentials);
            } catch (Throwable $e) {
                report($e);
            }
        });

        // Always the same response — whether the account exists ("user") or a
        // link is already on its way ("throttled") stays hidden from strangers,
        // otherwise registered addresses could be probed
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
