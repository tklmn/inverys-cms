<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use MatthiasMullie\Minify;

/**
 * Shrink HTML without changing the rendering or data.
 *
 *   - Line breaks and indentation from the templates removed: next to a tag
 *     they become a space (the same for the browser), in the middle of text
 *     the line break stays (white-space: pre-line displays it).
 *     Otherwise spaces in text stay as they are (white-space: pre-wrap).
 *   - In tags only the whitespace between attributes; attribute values stay
 *     character for character — they contain data (e.g. the page builder's
 *     blocks as JSON, code with indentation)
 *   - HTML comments removed — except conditional comments, Livewire
 *     markers ([if BLOCK] …) and the copyright notice
 *   - <style> and <script>: properly minified (matthiasmullie/minify, which
 *     parses JavaScript and knows where a line break is needed); the
 *     result is cached per content. If that fails, a cautious custom
 *     variant kicks in (css()/script()). JSON is compacted.
 *   - <pre> and <textarea> stay character for character
 */
final class HtmlMinifier
{
    private const RAW = 'pre|textarea|script|style';

    /** A tag including attributes; quoted values may contain > */
    private const TAG = '/<[!\/]?[a-zA-Z][^>"\']*(?:(?:"[^"]*"|\'[^\']*\')[^>"\']*)*>/';

    public static function minify(string $html): string
    {
        $kept = [];
        $keep = function (string $part) use (&$kept): string {
            $kept[] = $part;

            return "\x00".(count($kept) - 1)."\x00";
        };

        // 1. pre, textarea, script, style: content minified or unchanged, set aside
        $minified = preg_replace_callback('#<('.self::RAW.')\b([^>]*)>(.*?)(</\1\s*>)#is', function (array $match) use ($keep) {
            [, $name, $attributes, $body, $close] = $match;
            $tag = strtolower($name);

            if ($tag === 'style') {
                $body = self::minifyAsset('css', $body, $attributes);
            } elseif ($tag === 'script' && ! preg_match('/\bsrc\s*=/i', $attributes)) {
                $body = self::minifyAsset('js', $body, $attributes);
            }

            return $keep(self::tag('<'.$name.$attributes.'>').$body.$close);
        }, $html);

        // 2. Comments: removed, the few intended ones set aside
        $minified = $minified === null ? null : preg_replace_callback('/<!--(.*?)-->/s', fn (array $match) => self::keepComment($match[1]) ? $keep($match[0]) : '', $minified);

        // 3. Tags: collapse whitespace between attributes, values unchanged, set aside
        $minified = $minified === null ? null : preg_replace_callback(self::TAG, fn (array $match) => $keep(self::tag($match[0])), $minified);

        // 4. Text: line break with indentation next to a tag → one space,
        //    in the middle of text → one line break
        $minified = $minified === null ? null : preg_replace('/(\x00\d+\x00)[ \t\r]*\n\s*/', '$1 ', $minified);
        $minified = $minified === null ? null : preg_replace('/[ \t\r]*\n\s*(?=\x00\d+\x00)/', ' ', $minified);
        $minified = $minified === null ? null : preg_replace('/[ \t\r]*\n\s*/', "\n", $minified);

        // Put back what was set aside (tags were never inside raw blocks: those came first)
        $minified = $minified === null ? null : preg_replace_callback("/\x00(\d+)\x00/", fn (array $match) => $kept[(int) $match[1]], $minified);

        // Regex limit reached (very large page): better deliver it unchanged
        return $minified === null ? $html : trim($minified);
    }

    /** Collapse whitespace between attributes; quoted values stay as they are */
    private static function tag(string $tag): string
    {
        $parts = preg_split('/("[^"]*"|\'[^\']*\')/', $tag, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$tag];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = (string) preg_replace('/\s+/', ' ', $part);
            }
        }

        return (string) preg_replace('/\s+(\/?>)$/', '$1', implode('', $parts));
    }

    /** @var array<string, string> Results of this request */
    private static array $memo = [];

    /**
     * Minify the content of a <style> or <script> — with the library,
     * cached by content; JSON and other script types handled separately.
     */
    private static function minifyAsset(string $kind, string $body, string $attributes): string
    {
        if (trim($body) === '') {
            return $body;
        }

        // JSON and non-JavaScript (templates, data) as before
        if ($kind === 'js' && ! in_array(self::scriptType($attributes), ['', 'module', 'text/javascript', 'application/javascript'], true)) {
            return self::script($body, $attributes);
        }

        $key = 'html-minify.v2.'.$kind.'.'.hash('xxh128', $body);
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $minify = function () use ($kind, $body): string {
            try {
                // Process only the text: otherwise the library reads a file
                // if the content looks like a path (or via @import) — never here
                $minified = $kind === 'css'
                    ? (new class($body) extends Minify\CSS
                    {
                        protected function canImportFile($path)
                        {
                            return false;
                        }
                    })->minify()
                    : (new class($body) extends Minify\JS
                    {
                        protected function canImportFile($path)
                        {
                            return false;
                        }
                    })->minify();

                if (trim($minified) === '') {
                    return $body;
                }

                if ($kind === 'css') {
                    // The library leaves custom property values (--x: …) as they are;
                    // outside of strings a line break in CSS is a space
                    return (string) preg_replace('/\s*\n\s*/', ' ', $minified);
                }

                // After a block the next statement is on its own line: a ";"
                // separates just as well (not before else/catch/finally/while, not in template strings)
                return str_contains($minified, '`')
                    ? $minified
                    : (string) preg_replace('/\}\n(?=[A-Za-z_$])(?!(?:else|catch|finally|while)\b)/', '};', $minified);
            } catch (\Throwable) {
                return $kind === 'css' ? self::css($body) : self::script($body);
            }
        };

        try {
            $result = Cache::store('file')->rememberForever($key, $minify);
        } catch (\Throwable) {
            $result = $minify();
        }

        return self::$memo[$key] = $result;
    }

    private static function scriptType(string $attributes): string
    {
        return preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attributes, $m) ? strtolower($m[1]) : '';
    }

    /** CSS without comments and superfluous whitespace */
    public static function css(string $css): string
    {
        // Protect strings ("…", '…') so that content: "/* */" or similar stays
        $strings = [];
        $css = (string) preg_replace_callback('/"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'/s', function (array $m) use (&$strings) {
            $strings[] = $m[0];

            return "\x01".(count($strings) - 1)."\x01";
        }, $css);

        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('/\s+/', ' ', $css);
        $css = (string) preg_replace('/\s*([{};,])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);

        return trim((string) preg_replace_callback("/\x01(\d+)\x01/", fn (array $m) => $strings[(int) $m[1]], $css));
    }

    /** Inline script: JSON compacted, JavaScript without comment lines and indentation */
    public static function script(string $body, string $attributes = ''): string
    {
        $type = preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attributes, $m) ? strtolower($m[1]) : '';

        if (str_contains($type, 'json')) {
            $data = json_decode($body, true);

            return json_last_error() === JSON_ERROR_NONE && $data !== null
                ? (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                : $body;
        }

        // Only JavaScript; other types (templates, data) stay, as do template strings
        if (! in_array($type, ['', 'module', 'text/javascript', 'application/javascript'], true) || str_contains($body, '`')) {
            return $body;
        }

        $lines = [];
        $inComment = false;
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim($line);

            // Multi-line block comment starting at the beginning of a line
            if ($inComment) {
                if (str_contains($line, '*/')) {
                    $inComment = false;
                    $line = trim(substr($line, strpos($line, '*/') + 2));
                } else {
                    continue;
                }
            }
            if (str_starts_with($line, '/*')) {
                $end = strpos($line, '*/', 2);
                if ($end === false) {
                    $inComment = true;

                    continue;
                }
                $line = trim(substr($line, $end + 2));
            }

            if ($line === '' || str_starts_with($line, '//')) {
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private static function keepComment(string $body): bool
    {
        $body = ltrim($body);

        return str_starts_with($body, '[')
            || str_starts_with($body, ']')
            || str_starts_with($body, 'Copyright')
            || stripos($body, 'livewire') !== false;
    }
}
