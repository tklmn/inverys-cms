<?php

namespace App\Http\Middleware;

use App\Support\Icon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A page's icons appear once as a sprite before </body>; every occurrence
 * merely references it (<use href="#ui-i-…">). In the backend page list
 * this saves a good quarter of the source.
 *
 * Only for regular page requests (GET, no fetch, no JSON). If the result
 * turns out not to be a full document (HTML snippet, JSON), the references
 * become complete SVGs again — so an icon is never missing.
 */
class ServeIconSprite
{
    public function handle(Request $request, Closure $next): Response
    {
        $sprite = $request->isMethod('GET') && ! $request->ajax() && ! $request->expectsJson() && ! $request->is('api/*');

        Icon::useSprite($sprite);
        try {
            $response = $next($request);
        } finally {
            Icon::useSprite(false);
        }

        return $sprite ? self::apply($response) : $response;
    }

    public static function apply(Response $response): Response
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content) || ! str_contains($content, '#ui-i-')) {
            return $response;
        }

        $type = strtolower((string) $response->headers->get('Content-Type', ''));
        $document = ($type === '' || str_contains($type, 'text/html'))
            && preg_match('/<html[\s>]/i', $content)
            && stripos($content, '</body>') !== false;

        $content = $document ? self::withSprite($content) : self::inline($content);

        $original = $response instanceof IlluminateResponse ? $response->original : null;
        $response->setContent($content);
        if ($response instanceof IlluminateResponse) {
            $response->original = $original;
        }

        return $response;
    }

    /** All used icons once as <symbol> before </body> */
    private static function withSprite(string $content): string
    {
        preg_match_all('/#ui-i-([a-z0-9-]+)/', $content, $matches);

        $symbols = '';
        foreach (array_unique($matches[1]) as $name) {
            $symbols .= Icon::symbol($name) ?? '';
        }

        $sprite = '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute;overflow:hidden">'.$symbols.'</svg>';
        $at = (int) strripos($content, '</body>');

        return substr($content, 0, $at).$sprite.substr($content, $at);
    }

    /** Turn references back into complete SVGs — JSON-escaped ones too */
    private static function inline(string $content): string
    {
        $content = (string) preg_replace_callback(
            '#<svg ([^>]*)><use href="\#ui-i-([a-z0-9-]+)"></use></svg>#',
            fn (array $m) => Icon::inlineFromSprite($m[2], $m[1]) ?? $m[0],
            $content,
        );

        return (string) preg_replace_callback(
            '#<svg ((?:(?!<svg).)*?)><use href=\\\\"\#ui-i-([a-z0-9-]+)\\\\"><\\\\/use><\\\\/svg>#',
            function (array $m) {
                $svg = Icon::inlineFromSprite($m[2], stripslashes($m[1]));

                return $svg === null ? $m[0] : substr(json_encode($svg, JSON_UNESCAPED_UNICODE), 1, -1);
            },
            $content,
        );
    }
}
