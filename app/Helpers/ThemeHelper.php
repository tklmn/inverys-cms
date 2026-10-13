<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Database\QueryException;

class ThemeHelper
{
    /** Session key for the choice of a visitor who is not logged in. */
    public const GUEST_KEY = 'guest_theme';

    /**
     * Set the theme — permanently for logged-in users, in the session for
     * guests.
     */
    public static function setTheme(string $theme): void
    {
        $theme = $theme === 'dark' ? 'dark' : 'light';

        if (auth()->check()) {
            auth()->user()->setSetting('theme', $theme);

            return;
        }

        session([self::GUEST_KEY => $theme]);
    }

    /**
     * Get the current theme (dark or light).
     * First checks user preferences, then falls back to global setting, then defaults to 'light'.
     *
     * @return string 'dark' or 'light'
     */
    public static function getTheme()
    {
        // Check if user is logged in and has a theme preference
        if (auth()->check()) {
            $userTheme = auth()->user()->getSetting('theme');
            if ($userTheme) {
                return $userTheme;
            }
        }

        /*
         * Guests can switch in the frontend as well. Their choice lives in
         * the session — it belongs to no one and should not end up in the
         * database, but it survives page changes.
         */
        if (! auth()->check()) {
            $guestTheme = session(self::GUEST_KEY);
            if (in_array($guestTheme, ['light', 'dark'], true)) {
                return $guestTheme;
            }
        }

        // If no user preference, check global settings
        // Only try to get from settings if the theme column exists
        try {
            // Use a safer approach to get the setting
            $globalTheme = Setting::where('key', 'theme')->value('value');
            if ($globalTheme) {
                return $globalTheme;
            }
        } catch (QueryException $e) {
            // If there was an error (like the column or table doesn't exist), just continue
            // This can happen during fresh migrations
        }

        // Default fallback
        return 'light';
    }
}
