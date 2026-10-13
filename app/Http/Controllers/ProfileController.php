<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\ThemeManager;
use App\Support\EmailChange;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Personal settings in the profile. They take precedence over the site
     * default (Settings → General); when empty, the default applies again.
     */
    public const SETTINGS_KEYS = ['language', 'timezone', 'date_format'];

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Only the user's own values — empty means "site default". Showing the
        // global value here would copy it into the profile on every save, and
        // changes to the site would never reach this user again.
        $settings = $user->ownSettings(self::SETTINGS_KEYS);

        $locales = app(Locales::class);
        $siteTimezone = (string) (setting('timezone') ?: config('app.timezone'));

        return view('profile.edit', [
            'user' => $user,
            'settings' => $settings,
            'siteDefaults' => [
                'language' => $locales->native($locales->default()),
                'timezone' => str_replace('_', ' ', $siteTimezone),
                'date_format' => (string) setting('date_format', ''),
            ],
        ]);
    }

    /**
     * Display a user's public profile.
     */
    public function show(Request $request, User $user): View
    {
        // The profile shows email, phone and last login — other users' profiles
        // only with show-user, otherwise any registered visitor could enumerate
        // all accounts by ID
        $viewer = $request->user();
        abort_unless($viewer->id === $user->id || $viewer->hasPermission('show-user'), 403);

        return view('profile.show', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // The address only changes after confirmation via a link sent to the new
        // address (App\Support\EmailChange); until then the old one applies
        $newEmail = strtolower((string) $validated['email']);
        $emailChanged = $newEmail !== strtolower((string) $user->email);

        $user->fill(Arr::except($validated, [...self::SETTINGS_KEYS, 'email', 'current_password']));
        $user->save();

        if ($emailChanged) {
            EmailChange::request($user, $newEmail);
        }

        // Personal settings: empty = site default (remove the entry)
        foreach (self::SETTINGS_KEYS as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = trim((string) $request->input($key));
            $value === '' ? $user->forgetSetting($key) : $user->setSetting($key, $value);
        }

        return Redirect::route('profile.edit')->with('status', $emailChanged ? 'email-change-sent' : 'profile-updated');
    }

    /**
     * Delete the user's avatar.
     */
    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
            $user->save();

            return Redirect::route('profile.edit')->with('status', 'avatar-deleted');
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Update the user's theme setting.
     */
    public function updateTheme(Request $request): RedirectResponse
    {
        // Empty = site default (Settings → General or Themes)
        $request->validate([
            'theme' => ['sometimes', 'nullable', 'string', 'in:light,dark'],
            'frontend_theme' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();

        if ($request->has('theme')) {
            filled($request->theme) ? $user->setSetting('theme', $request->theme) : $user->forgetSetting('theme');
        }

        if ($request->has('frontend_theme')) {
            $tm = app(ThemeManager::class);
            if (blank($request->frontend_theme)) {
                $user->forgetSetting('frontend_theme');
            } elseif ($tm->has($request->frontend_theme)) {
                $user->setSetting('frontend_theme', $request->frontend_theme);
            }
        }

        return Redirect::back()->with('status', 'theme-updated');
    }
}
