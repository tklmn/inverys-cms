<?php

namespace App\Http\Middleware;

use App\Support\SeoSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Search engines blocked (Settings → SEO): every frontend response carries
 * "X-Robots-Tag: noindex, nofollow". The header works regardless of the theme —
 * including Composer themes and responses without HTML (sitemap, files).
 * The <meta name="robots"> in the <head> is added by a head hook
 * (frontend.partials.robots-meta).
 *
 * Backend and API are left untouched: they are not public anyway.
 */
class BlockSearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (self::applies($request)) {
            $response->headers->set('X-Robots-Tag', SeoSettings::NOINDEX);
        }

        return $response;
    }

    public static function applies(Request $request): bool
    {
        if ($request->is('backend', 'backend/*', 'api', 'api/*', 'install', 'install/*')) {
            return false;
        }

        try {
            return ! SeoSettings::allowIndexing();
        } catch (\Throwable) {
            // Without a database (installation) there is no setting — block nothing
            return false;
        }
    }
}
