<?php

namespace Plugins\CookieConsent;

use App\Support\CspSources;

/**
 * Output the code of a service the visitor has consented to.
 *
 * The website only allows scripts carrying this request's approval token
 * (CSP nonce) — every <script> of the service gets it here. The addresses
 * entered for the service count as allowed sources for this page (loading,
 * sending, images, embedded frames). Without consent, neither appears.
 */
class ServiceScript
{
    public static function render(array $service): string
    {
        $csp = app(CspSources::class);
        foreach (self::hosts($service) as $host) {
            foreach (['script-src', 'connect-src', 'img-src', 'frame-src'] as $directive) {
                $csp->allow($directive, $host);
            }
        }

        $nonce = e(csp_nonce());

        // Replace existing nonce attributes, otherwise append one
        return (string) preg_replace_callback('/<script\b([^>]*)>/i', function ($m) use ($nonce) {
            $attrs = preg_replace('/\snonce\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i', '', $m[1]);

            return '<script nonce="'.$nonce.'"'.$attrs.'>';
        }, (string) ($service['script'] ?? ''));
    }

    /** @return list<string> */
    public static function hosts(array $service): array
    {
        $hosts = $service['hosts'] ?? [];

        return array_values(array_filter(is_array($hosts) ? $hosts : [], fn ($h) => is_string($h) && CspSources::isValidSource($h)));
    }

    /** Input (one address per line) into a list; invalid lines go to $invalid. */
    public static function parseHosts(?string $input, array &$invalid = []): array
    {
        $hosts = [];
        foreach (preg_split('/[\r\n,]+/', (string) $input) as $line) {
            $line = rtrim(strtolower(trim($line)), '/');
            if ($line === '') {
                continue;
            }
            CspSources::isValidSource($line) ? $hosts[] = $line : $invalid[] = $line;
        }

        return array_values(array_unique($hosts));
    }
}
