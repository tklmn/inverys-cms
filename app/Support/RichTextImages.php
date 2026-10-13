<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;

/**
 * Responsive images inside rich text (text block, accordion, blog post …).
 *
 * Runs at render time on the output of HtmlHelper::clean() — the stored HTML
 * stays as the editor wrote it. Every <img> whose src is a media library file
 * gets srcset/sizes, width/height, loading="lazy" and decoding="async" from
 * ResponsiveImage (same variants, same rules). External images, unknown files
 * and everything that is not an <img> stay byte for byte unchanged.
 *
 * Safety: the input is purified markup — HTMLPurifier writes every attribute
 * double-quoted and escapes <, >, & and " inside values, so an <img> tag is
 * always "<img" … ">" with no ">" in between, and text never contains a raw
 * "<". The tag's own attributes are kept verbatim; only the added attributes
 * are new, each value escaped with e(). A tag that does not parse cleanly is
 * left alone.
 *
 * All library images of one text are looked up with a single query.
 */
final class RichTextImages
{
    /** An <img> tag of purified markup: attributes, optional self-closing slash */
    private const TAG = '#<img\b((?:\s+[^\s>/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'))?)*)\s*/?>#i';

    /** One attribute: name, optionally ="value" or ='value' */
    private const ATTRIBUTE = '#\s+([^\s>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?#';

    /** Attributes this class may add, in output order (src and editor attributes stay as they are) */
    private const ADDED = ['srcset', 'sizes', 'width', 'height', 'loading', 'decoding'];

    public function __construct(private readonly ResponsiveImage $images) {}

    public static function enhance(string $html): string
    {
        return app(self::class)->apply($html);
    }

    public function apply(string $html): string
    {
        if (stripos($html, '<img') === false || ! preg_match_all(self::TAG, $html, $matches)) {
            return $html;
        }

        // One query for all library images of this text
        $sources = [];
        foreach ($matches[1] as $attributeString) {
            $src = self::parseAttributes($attributeString)['src'] ?? null;
            if (is_string($src) && ResponsiveImage::libraryPath($src) !== null) {
                $sources[] = $src;
            }
        }
        if ($sources === []) {
            return $html;
        }
        $this->images->preload($sources);

        return preg_replace_callback(self::TAG, fn (array $m) => $this->enhanceTag($m[0], $m[1]), $html) ?? $html;
    }

    private function enhanceTag(string $tag, string $attributeString): string
    {
        $attributes = self::parseAttributes($attributeString);
        $src = $attributes['src'] ?? null;
        if (! is_string($src) || ResponsiveImage::libraryPath($src) === null) {
            return $tag;
        }

        $media = $this->images->lookup($src);
        if ($media === null) {
            return $tag;
        }

        [$width, $height] = self::dimensions($media, self::pixels($attributes['width'] ?? null), self::pixels($attributes['height'] ?? null));
        // An image the editor sized is shown at most that wide; otherwise at its own width
        $editorSized = isset($attributes['width']) || isset($attributes['height']);
        $sizes = match (true) {
            ! $editorSized || $width === null => 'intrinsic',
            $width <= 1280 => '(min-width: '.$width.'px) '.$width.'px, 100vw',
            // Wider than the content: as wide as the content
            default => null,
        };

        $responsive = $this->images->build($media, [
            'sizes' => $sizes,
            'lazy' => strtolower((string) ($attributes['loading'] ?? '')) !== 'eager',
            // Images in running text are not cropped
            'focal' => false,
        ]);
        if ($width !== null && $height !== null) {
            $responsive['width'] = $width;
            $responsive['height'] = $height;
        }

        $added = '';
        foreach (self::ADDED as $name) {
            if (! array_key_exists($name, $attributes) && isset($responsive[$name]) && $responsive[$name] !== '') {
                $added .= ' '.$name.'="'.e((string) $responsive[$name]).'"';
            }
        }

        if ($added === '') {
            return $tag;
        }

        $selfClosing = str_ends_with(rtrim(substr($tag, 0, -1)), '/');

        return '<img'.rtrim($attributeString).$added.($selfClosing ? ' />' : '>');
    }

    /**
     * width/height for the tag: the editor's values win; a missing half is
     * derived from the aspect ratio of the original, nothing set → the size
     * of the original.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function dimensions(Media $media, ?int $width, ?int $height): array
    {
        $originalWidth = (int) $media->width;
        $originalHeight = (int) $media->height;
        $known = $originalWidth > 0 && $originalHeight > 0;

        if ($width !== null && $height !== null) {
            return [$width, $height];
        }
        if ($width !== null) {
            return [$width, $known ? max(1, (int) round($width * $originalHeight / $originalWidth)) : null];
        }
        if ($height !== null) {
            return [$known ? max(1, (int) round($height * $originalWidth / $originalHeight)) : null, $height];
        }

        return $known ? [$originalWidth, $originalHeight] : [null, null];
    }

    /** A pixel value set by the editor ("300", "300px"); null for anything else (percentages …) */
    private static function pixels(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^\s*(\d{1,5})(?:px)?\s*$/i', $value, $m)) {
            return null;
        }

        $pixels = (int) $m[1];

        return $pixels > 0 ? $pixels : null;
    }

    /**
     * Attributes of a tag, names lower-cased, values decoded. Valueless
     * attributes map to ''.
     *
     * @return array<string, string>
     */
    private static function parseAttributes(string $attributeString): array
    {
        $attributes = [];
        if (preg_match_all(self::ATTRIBUTE, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');
                $attributes[$name] ??= html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }
}
