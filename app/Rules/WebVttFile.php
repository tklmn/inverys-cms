<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A WebVTT caption file (subtitles for videos, WCAG 1.2.2): extension .vtt,
 * plain UTF-8 text that starts with the "WEBVTT" signature. finfo knows no
 * VTT type (it says text/plain), so the content check carries the weight.
 *
 * Markup that a browser could treat as a document is refused as well —
 * the file is served as text/vtt with nosniff, this is a second fence.
 */
final class WebVttFile implements ValidationRule
{
    /** Read at most this much for the checks (caption files are small) */
    private const SCAN_BYTES = 1024 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('validation.file')->translate();

            return;
        }

        if (strtolower($value->getClientOriginalExtension()) !== 'vtt'
            || ! in_array($value->getMimeType(), ['text/plain', 'text/vtt'], true)
            || ! self::looksLikeWebVtt((string) file_get_contents($value->getRealPath(), false, null, 0, self::SCAN_BYTES))) {
            $fail('translation.media.invalid_vtt')->translate();
        }
    }

    public static function looksLikeWebVtt(string $content): bool
    {
        // Optional UTF-8 byte order mark, then "WEBVTT" alone or followed by space, tab or a line break
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! preg_match('/\AWEBVTT(?:[ \t\r\n]|\z)/', $content)) {
            return false;
        }

        // Text only: valid UTF-8, no NUL bytes, no HTML document or script markup
        return mb_check_encoding($content, 'UTF-8')
            && ! str_contains($content, "\0")
            && ! preg_match('/<\s*(?:script|html|body|iframe|object|embed|svg|\?php)\b/i', $content);
    }
}
