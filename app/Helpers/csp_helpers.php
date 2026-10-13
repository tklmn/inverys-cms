<?php

if (! function_exists('csp_nonce')) {
    /**
     * The per-request Content-Security-Policy nonce.
     *
     * Set by App\Http\Middleware\SetSecurityHeaders at the start of the request.
     * Use on every inline <script> so it survives the strict (nonce-based) CSP:
     *
     *   <script nonce="{{ csp_nonce() }}"> ... </script>   (or the @cspNonce directive)
     *
     * Returns an empty string outside the web middleware (e.g. console, tests
     * without the middleware) so Blade output stays harmless.
     */
    function csp_nonce(): string
    {
        return app()->bound('csp-nonce') ? app('csp-nonce') : '';
    }
}
