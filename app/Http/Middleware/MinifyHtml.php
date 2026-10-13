<?php

namespace App\Http\Middleware;

use App\Support\HtmlMinifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve full HTML pages minified (Settings → General →
 * "Minify HTML", default: on). Applies to every theme and every plugin,
 * because it processes the finished response. See App\Support\HtmlMinifier.
 */
class MinifyHtml
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never the page builder preview: inline editing saves what is in the HTML
        // there — it must match the stored content character for character
        if (! self::enabled() || $request->routeIs('backend.pages.canvas')
            || $response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        $content = $response->getContent();
        if (($type !== '' && ! str_contains($type, 'text/html')) || ! is_string($content) || ! preg_match('/<html[\s>]/i', $content)) {
            return $response;
        }

        $original = $response instanceof IlluminateResponse ? $response->original : null;
        $response->setContent(HtmlMinifier::minify($content));
        if ($response instanceof IlluminateResponse) {
            $response->original = $original;
        }

        return $response;
    }

    private static function enabled(): bool
    {
        try {
            return (string) setting('html.minify', '1') !== '0';
        } catch (\Throwable) {
            // Before installation there are no settings yet
            return false;
        }
    }
}
