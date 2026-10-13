<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Copyright notice in the source of every HTML page, right after the doctype:
 *
 *   <!-- Copyright © 2026 Inverys · Inverys CMS -->
 *
 * The year is always the current one. It is inserted into the finished
 * response after theme and plugins have rendered — so a theme can neither
 * override nor omit the notice. As the outermost global middleware it also
 * reaches error pages (404, 500).
 */
class AddCopyrightNotice
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }

    public static function notice(): string
    {
        return '<!-- Copyright © '.now()->year.' Inverys · Inverys CMS -->';
    }

    public static function apply(Response $response): Response
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        // Full documents only — no HTML snippets for fetch requests
        if (! is_string($content) || ! preg_match('/<html[\s>]/i', $content)) {
            return $response;
        }

        $notice = self::notice();
        if (str_contains($content, $notice)) {
            return $response;
        }

        $content = preg_match('/^\s*<!doctype[^>]*>/i', $content, $match)
            ? $match[0]."\n".$notice.substr($content, strlen($match[0]))
            : $notice."\n".$content;

        // Otherwise setContent() keeps the text instead of the view (assertViewIs, viewData)
        $original = $response instanceof IlluminateResponse ? $response->original : null;
        $response->setContent($content);
        if ($response instanceof IlluminateResponse) {
            $response->original = $original;
        }

        return $response;
    }
}
