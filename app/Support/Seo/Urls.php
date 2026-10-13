<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Media;
use App\Support\ResponsiveImage;

/**
 * Addresses and images for SEO tags: social networks and search engines
 * need absolute URLs; image sizes and alt texts come from the file and the
 * media library when they are known.
 */
final class Urls
{
    /** Absolute address of a path in public/ ("storage/media/a.jpg", "/images/x.png") or an existing URL */
    public static function absolute(string $pathOrUrl): string
    {
        $value = trim($pathOrUrl);

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return request()->getScheme().':'.$value;
        }

        return asset(ltrim($value, '/'));
    }

    /**
     * Images that social networks show: raster formats only. SVG
     * previews are not rendered by Facebook, LinkedIn, X & co.
     */
    public static function isSocialImage(?string $pathOrUrl): bool
    {
        if ($pathOrUrl === null || trim($pathOrUrl) === '') {
            return false;
        }

        $path = strtolower((string) parse_url(trim($pathOrUrl), PHP_URL_PATH));

        return ! str_ends_with($path, '.svg') && ! str_ends_with($path, '.svgz');
    }

    /** First usable social image of the candidates (null/empty/SVG are skipped) */
    public static function firstSocialImage(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (self::isSocialImage($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    /**
     * Width and height of a local image, null when unknown (remote URL,
     * missing file, not an image).
     *
     * @return array{0: int, 1: int}|null
     */
    public static function imageSize(string $pathOrUrl): ?array
    {
        // Media library: the size stored on upload, no need to read the file
        $media = ResponsiveImage::libraryPath($pathOrUrl) !== null
            ? app(ResponsiveImage::class)->lookup($pathOrUrl)
            : null;
        if ($media !== null && $media->width > 0 && $media->height > 0) {
            return [(int) $media->width, (int) $media->height];
        }

        $file = self::localFile($pathOrUrl);

        if ($file === null) {
            return null;
        }

        $size = @getimagesize($file);

        return is_array($size) && $size[0] > 0 && $size[1] > 0 ? [(int) $size[0], (int) $size[1]] : null;
    }

    /** Alt text of an image from the media library (storage/… path), otherwise null */
    public static function mediaAlt(string $pathOrUrl): ?string
    {
        $path = self::publicPath($pathOrUrl);

        if ($path === null || ! str_starts_with($path, 'storage/')) {
            return null;
        }

        try {
            $alt = Media::where('path', substr($path, strlen('storage/')))->first()?->alt_text;
        } catch (\Throwable) {
            return null;
        }

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    /** Path below public/ for a relative path or a URL of this site */
    private static function publicPath(string $pathOrUrl): ?string
    {
        $value = trim($pathOrUrl);

        if (preg_match('#^https?://#i', $value)) {
            $root = rtrim(asset(''), '/').'/';
            if (! str_starts_with($value, $root)) {
                return null;
            }
            $value = substr($value, strlen($root));
        }

        $path = ltrim((string) parse_url($value, PHP_URL_PATH), '/');

        // No way out of public/
        return $path === '' || str_contains($path, '..') ? null : rawurldecode($path);
    }

    private static function localFile(string $pathOrUrl): ?string
    {
        $path = self::publicPath($pathOrUrl);

        if ($path === null) {
            return null;
        }

        $file = public_path($path);

        return is_file($file) ? $file : null;
    }
}
