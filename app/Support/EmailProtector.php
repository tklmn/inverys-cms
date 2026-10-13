<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hide email addresses in the finished HTML from spam bots.
 *
 *   - mailto links: the address is only stored encoded in the attribute
 *     data-cms-email (reversed, Base64); href is "#" until the script
 *     public/js/email-protection.js assembles the link in the browser
 *   - addresses in text: instead of "@" there is <span class="cms-at"></span>, a
 *     style displays the "@" there, the script inserts the real character
 *     (so that copying works)
 *   - title, aria-label and alt: "@" becomes "(at)", the script reverts it
 *
 * Only in <body>; <script>, <style>, <textarea>, <select>, <svg> and
 * comments stay untouched, as do all other attributes.
 */
final class EmailProtector
{
    private const EMAIL = '[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}';

    private const RAW = 'script|style|textarea|select|svg|title';

    private const ATTRIBUTES = 'title|aria-label|alt';

    public static function protect(string $html, string $scriptUrl): string
    {
        if (! preg_match('#<body\b[^>]*>#i', $html, $open, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $start = $open[0][1] + strlen($open[0][0]);
        $head = substr($html, 0, $start);
        $body = substr($html, $start);
        $changed = false;

        // Set aside untouched regions
        $raw = [];
        $body = preg_replace_callback('#<('.self::RAW.')\b[^>]*>.*?</\1\s*>|<!--.*?-->#is', function (array $match) use (&$raw) {
            $raw[] = $match[0];

            return "\x00".(count($raw) - 1)."\x00";
        }, $body);

        // Process tags and text separately
        $parts = preg_split('#(<[^>]*>)#', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            $new = str_starts_with($part, '<') ? self::tag($part) : self::text($part);
            if ($new !== $part) {
                $parts[$i] = $new;
                $changed = true;
            }
        }

        if (! $changed) {
            return $html;
        }

        $body = preg_replace_callback("#\x00(\d+)\x00#", fn (array $m) => $raw[(int) $m[1]], implode('', $parts));

        $style = '<style>.cms-at::after{content:"@"}</style>';
        $head = preg_match('#</head>#i', $head)
            ? preg_replace('#</head>#i', $style.'</head>', $head, 1)
            : $head.$style;
        $script = '<script src="'.e($scriptUrl).'" defer></script>';
        $body = preg_match('#</body>#i', $body)
            ? preg_replace('#</body>(?!.*</body>)#is', $script.'</body>', $body, 1)
            : $body.$script;

        return $head.$body;
    }

    /** Address for data-cms-email: characters reversed, then Base64 */
    public static function encode(string $address): string
    {
        return base64_encode(implode('', array_reverse(mb_str_split($address))));
    }

    private static function text(string $text): string
    {
        if (! str_contains($text, '@')) {
            return $text;
        }

        return preg_replace_callback('/'.self::EMAIL.'/', fn (array $m) => str_replace('@', '<span class="cms-at"></span>', $m[0]), $text);
    }

    private static function tag(string $tag): string
    {
        if (! str_contains($tag, '@')) {
            return $tag;
        }

        // mailto link: address into the encoded attribute
        if (preg_match('#^<a\b#i', $tag)) {
            $tag = preg_replace_callback('#\shref\s*=\s*(["\'])\s*mailto:(.*?)\1#is', function (array $m) {
                $address = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return ' href="#" data-cms-email="'.self::encode($address).'"';
            }, $tag);
        }

        // Readable attributes: "@" → "(at)", the script restores it
        $marked = [];
        $tag = preg_replace_callback('#\s('.self::ATTRIBUTES.')\s*=\s*(["\'])(.*?)\2#is', function (array $m) use (&$marked) {
            if (! preg_match('/'.self::EMAIL.'/', $m[3])) {
                return $m[0];
            }
            $marked[] = strtolower($m[1]);

            return ' '.$m[1].'='.$m[2].preg_replace_callback('/'.self::EMAIL.'/', fn (array $e) => str_replace('@', '(at)', $e[0]), $m[3]).$m[2];
        }, $tag);

        if ($marked) {
            $tag = preg_replace('#\s*(/?)>$#', ' data-cms-at="'.implode(' ', $marked).'"$1>', $tag, 1);
        }

        return $tag;
    }
}
