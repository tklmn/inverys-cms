<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Additional sources for a request's Content Security Policy.
 *
 * The CSP is strict (SetSecurityHeaders). If a page may exceptionally allow more —
 * e.g. an analytics service the visitor has consented to —, the code
 * registers it here while the page is being built:
 *
 *   app(CspSources::class)->allow('script-src', 'https://www.googletagmanager.com');
 *
 * Only https URLs (including *.domain), only for the directives below. The
 * addition applies only to this one response.
 */
final class CspSources
{
    public const DIRECTIVES = ['script-src', 'connect-src', 'img-src', 'frame-src', 'font-src', 'style-src', 'media-src'];

    /** @var array<string, array<string, true>> */
    private array $sources = [];

    /** Valid source: https://host[:port] or https://*.host — no path, no keywords */
    public static function isValidSource(string $source): bool
    {
        return (bool) preg_match('#^https://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(:\d{1,5})?$#i', $source);
    }

    public function allow(string $directive, string $source): void
    {
        if (in_array($directive, self::DIRECTIVES, true) && self::isValidSource($source)) {
            $this->sources[$directive][strtolower($source)] = true;
        }
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }

    /** Insert the registered sources into the response's CSP (duplicates are not added again). */
    public function applyTo(Response $response): void
    {
        $header = $response->headers->get('Content-Security-Policy');
        if (! $header || $this->sources === []) {
            return;
        }

        $parts = array_values(array_filter(array_map('trim', explode(';', $header))));
        foreach ($this->sources as $directive => $sources) {
            $found = false;
            foreach ($parts as $i => $part) {
                $tokens = preg_split('/\s+/', $part);
                if ($tokens[0] !== $directive) {
                    continue;
                }
                $found = true;
                foreach (array_keys($sources) as $source) {
                    if (! in_array($source, $tokens, true)) {
                        $tokens[] = $source;
                    }
                }
                $parts[$i] = implode(' ', $tokens);
            }
            if (! $found) {
                $parts[] = $directive." 'self' ".implode(' ', array_keys($sources));
            }
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $parts));
    }
}
