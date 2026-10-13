<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetUserLocale
{
    public function handle($request, Closure $next)
    {
        try {
            $locales = app(Locales::class);

            // Frontend with the multilingual plugin: the page's language applies,
            // even for logged-in editors — otherwise English buttons would sit
            // above German text.
            $locale = $locales->visitor();
            $session = $request->hasSession() ? $request->session() : null;

            // Remember the last language read: login, registration and
            // forgot password have no language prefix — a guest coming from
            // /de/… would otherwise see them in English, error messages included.
            if ($locale !== null && $session && ! Auth::check() && $session->get('visitor_locale') !== $locale) {
                $session->put('visitor_locale', $locale);
            }

            if ($locale === null && Auth::check()) {
                $locale = Auth::user()->getSetting('language');
            } elseif ($locale === null && $session) {
                $locale = $session->get('visitor_locale');
            }

            // Installed languages only; otherwise the default language (ultimately English)
            if (! $locales->has($locale)) {
                $locale = $locales->default();
            }

            App::setLocale($locale);
        } catch (\Throwable $e) {
            // Settings table may not exist during testing or migrations
        }

        return $next($request);
    }
}
