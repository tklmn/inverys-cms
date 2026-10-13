<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Support\Locales;
use App\Support\Redirects;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects (Settings → SEO → Redirects) — only for requests that would
 * end in 404, so a live page always wins and normal requests cost nothing.
 *
 * Global middleware: it also sees addresses no route matches
 * (/old/section/page). It runs after the multilingual plugin has taken the
 * language prefix off the path; targets on this site therefore get the
 * visitor's prefix again through url().
 */
class ResolveRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethodSafe() || $request->is('backend', 'backend/*', 'api', 'api/*', 'install', 'install/*')) {
            return $response;
        }

        try {
            if (! Redirects::enabled()) {
                return $response;
            }

            $hit = Redirects::resolve($request->getPathInfo(), app(Locales::class)->visitor());
        } catch (\Throwable $e) {
            report($e);

            return $response;
        }

        if ($hit === null) {
            return $response;
        }

        $this->count($hit['redirect']);

        $target = $hit['target'];
        $url = Redirects::isInternal($target) ? url($target) : $target;

        // The query of the request travels along, unless the target has its own
        $query = $request->getQueryString();
        if ($query && ! str_contains($url, '?')) {
            $url .= '?'.$query;
        }

        return new RedirectResponse($url, in_array($hit['status'], Redirect::STATUS_CODES, true) ? $hit['status'] : 301);
    }

    private function count(Redirect $redirect): void
    {
        try {
            Redirect::query()->whereKey($redirect->getKey())->increment('hits', 1, ['last_hit_at' => now()]);
        } catch (\Throwable) {
            // Statistics are not worth a failed redirect
        }
    }
}
