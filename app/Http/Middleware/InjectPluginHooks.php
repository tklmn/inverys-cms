<?php

namespace App\Http\Middleware;

use App\Support\AccountPages;
use App\Support\CspSources;
use App\Support\Seo\SeoContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InjectPluginHooks
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only inject on frontend HTML responses: skip backend, API and the
        // account pages (login, password reset, email change …), whose
        // addresses and forms carry tokens and passwords
        if (AccountPages::withoutPluginHooks($request) || $request->ajax()) {
            return $response;
        }

        if (! $response instanceof Response) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        $modified = false;

        // Head hooks (injected before </head>)
        $headHooks = app()->bound('plugin.head_hooks') ? app('plugin.head_hooks') : [];
        if (! empty($headHooks)) {
            // SEO tags need the status (none on error pages) and the theme's
            // own <head> (tags it already prints are not repeated)
            $headEnd = stripos($content, '</head>');
            app(SeoContext::class)->respond($response->getStatusCode(), $headEnd === false ? '' : substr($content, 0, $headEnd));

            $headHtml = '';
            foreach ($headHooks as $hook) {
                try {
                    $headHtml .= view($hook)->render();
                } catch (\Throwable $e) {
                    // Skip broken plugin views
                }
            }
            if ($headHtml) {
                $content = self::insertBefore($content, '</head>', $headHtml."\n", false);
                $modified = true;
            }
        }

        // Body hooks (injected before </body>)
        $bodyHooks = app()->bound('plugin.body_hooks') ? app('plugin.body_hooks') : [];
        if (! empty($bodyHooks)) {
            $bodyHtml = '';
            foreach ($bodyHooks as $hook) {
                try {
                    $bodyHtml .= view($hook)->render();
                } catch (\Throwable $e) {
                    // Skip broken plugin views
                }
            }
            if ($bodyHtml) {
                $content = self::insertBefore($content, '</body>', $bodyHtml."\n", true);
                $modified = true;
            }
        }

        if ($modified) {
            $response->setContent($content);
            // Hooks run after SetSecurityHeaders — add their CSP exceptions now
            app(CspSources::class)->applyTo($response);
        }

        return $response;
    }

    /**
     * Insert before exactly one tag: </head> is the first occurrence, </body>
     * the last — a "</body>" inside content or a script stays untouched.
     */
    public static function insertBefore(string $html, string $tag, string $insert, bool $last): string
    {
        $pos = $last ? strripos($html, $tag) : stripos($html, $tag);

        return $pos === false ? $html : substr_replace($html, $insert, $pos, 0);
    }
}
