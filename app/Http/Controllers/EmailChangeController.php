<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Links from the email change mails (App\Support\EmailChange).
 *
 * Clicking the link only shows a page with a button; only the button (POST)
 * acts. Mail clients often open links themselves to check them — otherwise a
 * scanner in the old mailbox could revert a genuine change.
 * Both links are signed and work without login (different device).
 */
class EmailChangeController extends Controller
{
    public function showConfirm(Request $request, User $user, string $nonce): View
    {
        $email = EmailChange::canConfirm($user, $nonce);

        return view('auth.email-change', [
            'mode' => 'confirm',
            'valid' => $email !== null,
            'email' => $email,
            'action' => $request->fullUrl(),
        ]);
    }

    public function confirm(User $user, string $nonce): RedirectResponse
    {
        $ok = EmailChange::confirm($user, $nonce);

        return redirect()->route(auth()->check() ? 'profile.edit' : 'login')
            ->with($ok ? 'success' : 'error', __($ok ? 'translation.email_change.confirmed' : 'translation.email_change.invalid'));
    }

    public function showRevert(Request $request, User $user): View
    {
        $email = EmailChange::canRevert($user, (string) $request->query('nonce'));

        return view('auth.email-change', [
            'mode' => 'revert',
            'valid' => $email !== null,
            'email' => $email,
            'action' => $request->fullUrl(),
        ]);
    }

    public function revert(Request $request, User $user): RedirectResponse
    {
        $ok = EmailChange::revert($user, (string) $request->query('nonce'));

        // The session that made the change has been ended — log out here too if applicable
        if ($ok && auth()->id() === $user->getKey()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->with($ok ? 'success' : 'error', __($ok ? 'translation.email_change.reverted' : 'translation.email_change.invalid'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        EmailChange::cancel($request->user());

        return redirect()->route('profile.edit')->with('success', __('translation.email_change.cancelled'));
    }
}
