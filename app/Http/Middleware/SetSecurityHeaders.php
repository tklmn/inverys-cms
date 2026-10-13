<?php

namespace App\Http\Middleware;

use App\Support\CspSources;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Per-request CSP nonce, generated BEFORE the view renders so inline
        // <script> tags can reference it via csp_nonce() / @cspNonce.
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Disabled per OWASP guidance: the legacy XSS auditor is deprecated and
        // '1; mode=block' can itself introduce vulnerabilities in old browsers.
        // The strict CSP below is the real XSS defence.
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Strict, nonce-based Content Security Policy.
        // - script-src: no 'unsafe-inline'/'unsafe-eval' — every inline <script>
        //   carries the per-request nonce (@cspNonce); inline event handlers were
        //   removed in favour of addEventListener / delegated listeners.
        // - style-src keeps 'unsafe-inline': inline style="" attributes (page
        //   builder) cannot carry a nonce.
        // - frame-src/media-src/img-src allow YouTube (nocookie) & Vimeo embeds
        //   plus self-hosted media for the video block.
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://i.ytimg.com; font-src 'self' data:; connect-src 'self'; media-src 'self' data: blob:; object-src 'none'; frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

        // Exceptions the page registered itself (App\Support\CspSources)
        app(CspSources::class)->applyTo($response);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
