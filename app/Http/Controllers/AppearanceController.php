<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ThemeHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switch between light and dark appearance.
 *
 * Deliberately without login: logged-in users get their choice stored
 * permanently, guests for the duration of their session. Logged-in users
 * additionally have profile.theme.update, which also sets the frontend theme.
 */
class AppearanceController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => 'nullable|in:light,dark',
        ]);

        $theme = $validated['theme'] ?? (ThemeHelper::getTheme() === 'dark' ? 'light' : 'dark');

        ThemeHelper::setTheme($theme);

        return back();
    }
}
