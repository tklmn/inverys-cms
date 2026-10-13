<?php

namespace App\Http\Middleware;

use App\Support\EmailProtector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hide email addresses from spam bots (Settings → General →
 * "Protect email addresses", default: on). Applies to every theme and every
 * plugin, because it processes the finished response. Logged-in users see the
 * page unchanged — the page builder saves what it finds in the browser.
 * See App\Support\EmailProtector.
 */
class ProtectEmailAddresses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse || ! self::enabled()) {
            return $response;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        $content = $response->getContent();
        if (($type !== '' && ! str_contains($type, 'text/html')) || ! is_string($content)
            || ! str_contains($content, '@') || ! preg_match('/<html[\s>]/i', $content)
            || self::signedIn($request)) {
            return $response;
        }

        $original = $response instanceof IlluminateResponse ? $response->original : null;
        $response->setContent(EmailProtector::protect($content, asset('js/email-protection.js').'?v='.config('cms.version')));
        if ($response instanceof IlluminateResponse) {
            $response->original = $original;
        }

        return $response;
    }

    private static function signedIn(Request $request): bool
    {
        try {
            return $request->user() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function enabled(): bool
    {
        try {
            return (string) setting('html.protect_emails', '1') !== '0';
        } catch (\Throwable) {
            // Before installation there are no settings yet
            return false;
        }
    }
}
