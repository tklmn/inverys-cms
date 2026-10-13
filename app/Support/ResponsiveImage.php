<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;

/**
 * The attributes of an <img> for an image from the media library:
 * src, srcset/sizes (the scaled copies of MediaImageProcessor), width and
 * height (no layout shift), decoding, loading and fetchpriority.
 *
 *   <x-responsive-image :src="$data['src']" alt="…" class="…" sizes="100vw" :priority="$imagePriority ?? false" />
 *
 * External URLs, SVGs and files outside the library fall back to a plain
 * image (src, loading, decoding) — nothing breaks when a lookup fails.
 * Library entries are looked up once per request.
 *
 * The focal point of the library entry (Media::focalPosition()) becomes
 * style="object-position: …": cropped images (object-fit: cover) keep the
 * important part in view. Images shown uncropped are not affected; for
 * object-fit: contain pass focal=false, otherwise the image would shift
 * inside its box.
 */
final class ResponsiveImage
{
    /** Content width of the themes (--site-max-width "normal" = 80rem), full width below */
    public const DEFAULT_SIZES = '(min-width: 80rem) 80rem, 100vw';

    /** The original is offered in srcset only up to this width — huge camera files stay out */
    public const MAX_ORIGINAL_CANDIDATE = 2560;

    /** @var array<string, Media|null> */
    private array $cache = [];

    /**
     * @param  Media|string|null  $source  library entry, "storage/media/…", "media/…", a URL of this site or an external URL
     * @param  array{sizes?: ?string, priority?: bool, lazy?: bool, focal?: bool}  $options
     *                                                                                       priority: the image is the largest one above the fold
     *                                                                                       (eager + fetchpriority="high"); lazy=false loads it
     *                                                                                       eagerly without raising its priority; focal=false
     *                                                                                       leaves out the object-position of the focal point
     * @return array<string, string|int>
     */
    public static function attributes(Media|string|null $source, array $options = []): array
    {
        return app(self::class)->build($source, $options);
    }

    /** @return array<string, string|int> */
    public function build(Media|string|null $source, array $options = []): array
    {
        $media = $source instanceof Media ? $source : $this->lookup((string) $source);
        $src = $source instanceof Media ? $source->url : self::url((string) $source);
        $priority = (bool) ($options['priority'] ?? false);
        $lazy = ! $priority && ($options['lazy'] ?? true) !== false;

        $attributes = ['src' => $src];

        if ($media !== null) {
            $srcset = self::srcset($media);
            if ($srcset !== '') {
                $attributes['srcset'] = $srcset;
                $sizes = trim((string) ($options['sizes'] ?? ''));
                if ($sizes === 'intrinsic') {
                    // Shown at its own width (at most the content width)
                    $sizes = $media->width && $media->width <= 1280
                        ? '(min-width: '.$media->width.'px) '.$media->width.'px, 100vw'
                        : self::DEFAULT_SIZES;
                }
                $sizes = $sizes !== '' ? $sizes : self::DEFAULT_SIZES;
                // Lazy images: browsers that know sizes="auto" use the real layout width
                $attributes['sizes'] = $lazy && ! str_starts_with($sizes, 'auto') ? 'auto, '.$sizes : $sizes;
            }
            if ($media->width && $media->height) {
                $attributes['width'] = (int) $media->width;
                $attributes['height'] = (int) $media->height;
            }
            if (($options['focal'] ?? true) !== false && ($position = $media->focalPosition()) !== null) {
                $attributes['style'] = self::objectPosition($position);
            }
        }

        $attributes['loading'] = $lazy ? 'lazy' : 'eager';
        $attributes['decoding'] = 'async';
        if ($priority) {
            $attributes['fetchpriority'] = 'high';
        }

        return $attributes;
    }

    /**
     * The inline style for a focal point: "object-position: 30% 70%;". The
     * value is built from two numbers (Media::focalPosition()), never from
     * user text.
     */
    public static function objectPosition(string $position): string
    {
        return 'object-position: '.$position.';';
    }

    /**
     * Same for CSS background images: "background-position: 30% 70%;", or ''
     * for the center (and for files outside the library).
     */
    public function backgroundPosition(Media|string|null $source): string
    {
        $media = $source instanceof Media ? $source : $this->lookup((string) $source);
        $position = $media?->focalPosition();

        return $position !== null ? 'background-position: '.$position.';' : '';
    }

    /** The URL of a stored path: external URLs unchanged, everything else relative to public/ */
    public static function url(string $src): string
    {
        $src = trim($src);

        if ($src === '' || preg_match('#^(https?:)?//#i', $src) || str_starts_with($src, 'data:')) {
            return $src;
        }

        return asset(ltrim($src, '/'));
    }

    /**
     * srcset of a library entry: the scaled copies plus the original (when
     * it is not huge). Empty when there is nothing to choose from.
     */
    public static function srcset(Media $media): string
    {
        $variants = is_array($media->variants) ? $media->variants : [];
        $candidates = [];

        foreach ($variants as $variant) {
            $width = (int) ($variant['width'] ?? 0);
            $path = $variant['path'] ?? null;
            if ($width > 0 && is_string($path) && $path !== '') {
                $candidates[$width] = asset('storage/'.$path);
            }
        }

        if ($candidates === []) {
            return '';
        }

        $original = (int) $media->width;
        if ($original > 0 && $original <= self::MAX_ORIGINAL_CANDIDATE) {
            $candidates[$original] ??= $media->url;
        }

        ksort($candidates);

        return implode(', ', array_map(
            fn (int $width, string $url) => self::escapeCandidate($url).' '.$width.'w',
            array_keys($candidates),
            $candidates,
        ));
    }

    /** The library entry behind a stored path, null for everything else */
    public function lookup(string $src): ?Media
    {
        $path = self::libraryPath($src);
        if ($path === null) {
            return null;
        }

        if (! array_key_exists($path, $this->cache)) {
            try {
                $this->cache[$path] = Media::withTrashed()->where('path', $path)->first();
            } catch (\Throwable) {
                $this->cache[$path] = null;
            }
        }

        return $this->cache[$path];
    }

    /**
     * Look up several stored paths with a single query (rich text with many
     * images); afterwards lookup() answers them from the cache.
     *
     * @param  iterable<string>  $sources
     */
    public function preload(iterable $sources): void
    {
        $missing = [];
        foreach ($sources as $source) {
            $path = self::libraryPath((string) $source);
            if ($path !== null && ! array_key_exists($path, $this->cache)) {
                $missing[$path] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        $paths = array_keys($missing);
        foreach ($paths as $path) {
            $this->cache[$path] = null;
        }

        try {
            foreach (array_chunk($paths, 500) as $chunk) {
                foreach (Media::withTrashed()->whereIn('path', $chunk)->get() as $media) {
                    $this->cache[$media->path] = $media;
                }
            }
        } catch (\Throwable) {
            // Missing table (installer) or database error: plain images
        }
    }

    /** "media/…" for paths and URLs of the media library, otherwise null (no query needed) */
    public static function libraryPath(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $src)) {
            $storageUrl = rtrim(asset('storage'), '/').'/';
            if (! str_starts_with($src, $storageUrl)) {
                return null;
            }
            $src = substr($src, strlen($storageUrl));
        }

        $path = (string) preg_replace('#^/?storage/#', '', ltrim(strtok($src, '?#') ?: '', '/'));

        return str_starts_with($path, 'media/') && ! str_contains($path, '..') ? $path : null;
    }

    /** Commas and spaces would split a srcset candidate */
    private static function escapeCandidate(string $url): string
    {
        return str_replace([' ', ','], ['%20', '%2C'], $url);
    }
}
