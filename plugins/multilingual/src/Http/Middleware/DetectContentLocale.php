<?php

namespace Plugins\Multilingual\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Plugins\Multilingual\Support\Paths;

/**
 * Detects the language from the path and passes the request on without the prefix.
 *
 *   /about-us        → default language
 *   /en/about-us     → English; continues as /about-us
 *   /de/about-us     → if German is the default: 301 to /about-us (one URL per page)
 *
 * Because the prefix is stripped before routing, pages, blog and search
 * need no language routes of their own — they work in every language just
 * as they are registered in the core.
 *
 * Runs as global middleware before routing (see ServiceProvider).
 */
class DetectContentLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->getPathInfo(), '/');
        $first = explode('/', $path, 2)[0];

        // Backend, API, login, assets …: no content language
        if (! Paths::isContent($first) && ! $this->locales->has($first)) {
            return $next($request);
        }

        $default = $this->locales->default();

        if ($this->locales->has($first)) {
            $rest = substr($path, strlen($first));

            // The default language has no prefix — exactly one URL per page.
            // Relative URL: this middleware runs before TrustHosts, so an
            // absolute URL would come from the unvalidated Host header (and
            // redirect()->to() would append the base URL a second time). Strip
            // leading "/" and "\": browsers read "/\evil.example" as "//evil.example".
            if ($first === $default) {
                return new RedirectResponse($request->getBaseUrl().'/'.ltrim($rest, '/\\').$this->query($request), 301);
            }

            $this->locales->setVisitor($first);
            $this->rewrite($request, '/'.ltrim($rest, '/'));
        } else {
            $this->locales->setVisitor($default);
        }

        return $next($request);
    }

    /**
     * Replace the request path before the router reads it. Symfony caches
     * path and base URL on first access — initialize() resets these
     * caches.
     */
    private function rewrite(Request $request, string $path): void
    {
        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$path.$this->query($request);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
    }

    private function query(Request $request): string
    {
        $query = $request->getQueryString();

        return $query ? '?'.$query : '';
    }
}
