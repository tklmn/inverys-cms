<?php

namespace App\Http\Middleware;

use App\Services\ThemeManager;
use App\Support\ThemeViews;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the website, a signed-in user's own theme (profile) replaces the
 * globally active one for the whole request — theme:: views and the plugin
 * view overrides alike. The backend always uses the globally active theme.
 */
class ApplyVisitorTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(ThemeViews::class)->useTheme(app(ThemeManager::class)->getActiveForUser());
        } catch (\Throwable) {
            // Before installation there are no settings and no themes to switch
        }

        return $next($request);
    }
}
