<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Image work for the media library, done with plain GD (available on
 * practically every host):
 *
 *  - the intrinsic size (width/height attributes, og:image size),
 *  - the 300 px thumbnail of the backend,
 *  - scaled copies for srcset ("variants"): 480, 768, 1280 and 1920 px wide,
 *    never upscaled, in WebP when GD can write it, otherwise in the format
 *    of the original. They sit next to the original:
 *      media/2026/10/<uuid>.jpg  →  media/2026/10/<uuid>-768w.webp
 *
 * GIF (animation would be lost), SVG and everything GD cannot read keep the
 * original only. Failures never break an upload: the image is then simply
 * served without variants.
 */
class MediaImageProcessor
{
    /** Widths of the scaled copies (px) */
    public const WIDTHS = [480, 768, 1280, 1920];

    public const THUMBNAIL_WIDTH = 300;

    /** Formats that get scaled copies */
    private const VARIANT_SOURCES = ['image/jpeg', 'image/png', 'image/webp'];

    private const DISK = 'public';

    /**
     * Size, thumbnail and variants for a file that was just stored at
     * $storedPath on the public disk. Returns the media attributes to save.
     *
     * @return array{width: ?int, height: ?int, variants: ?array, thumbnail_path?: ?string}
     */
    public function processUpload(string $localFile, string $storedPath, string $mimeType, bool $withThumbnail = true): array
    {
        $attributes = ['width' => null, 'height' => null, 'variants' => null];

        if (! str_starts_with($mimeType, 'image/')) {
            return $attributes;
        }

        [$width, $height] = $this->dimensions($localFile) ?? [null, null];
        $attributes['width'] = $width;
        $attributes['height'] = $height;

        if ($withThumbnail) {
            $attributes['thumbnail_path'] = $this->createThumbnail($localFile, $storedPath);
        }

        $attributes['variants'] = $this->createVariants($localFile, $storedPath) ?: null;

        return $attributes;
    }

    /**
     * Fill in size and variants of an existing library entry (backfill).
     * Idempotent: an entry that is complete stays untouched unless $force
     * is set. Only width, height and variants are written — without
     * changing updated_at or firing model events.
     *
     * @return string 'updated', 'skipped' or 'failed'
     */
    public function refresh(Media $media, bool $force = false): string
    {
        // SVG scales by itself and has no pixel size
        if (! str_starts_with((string) $media->mime_type, 'image/') || str_starts_with((string) $media->mime_type, 'image/svg') || ! $media->path) {
            return 'skipped';
        }

        if (! $force && $this->isComplete($media)) {
            return 'skipped';
        }

        if (! Storage::disk(self::DISK)->exists($media->path)) {
            return 'failed';
        }

        $tmp = $this->temporaryCopy($media->path);
        if ($tmp === null) {
            return 'failed';
        }

        try {
            $this->deleteVariants($media);

            $size = $this->dimensions($tmp);
            $media->width = $size[0] ?? null;
            $media->height = $size[1] ?? null;
            $media->variants = $this->createVariants($tmp, $media->path) ?: null;

            Media::withoutTimestamps(fn () => $media->saveQuietly());
        } finally {
            @unlink($tmp);
        }

        return $size === null ? 'failed' : 'updated';
    }

    /** Whether an entry already has its size and every variant it should have */
    public function isComplete(Media $media): bool
    {
        if (! $media->width || ! $media->height) {
            return false;
        }

        $expected = in_array($media->mime_type, self::VARIANT_SOURCES, true)
            ? count($this->targetWidths((int) $media->width))
            : 0;
        $variants = is_array($media->variants) ? $media->variants : [];

        if (count($variants) !== $expected) {
            return false;
        }

        $disk = Storage::disk(self::DISK);
        foreach ($variants as $variant) {
            if (! is_string($variant['path'] ?? null) || ! $disk->exists($variant['path'])) {
                return false;
            }
        }

        return true;
    }

    /** Remove the scaled copies of an entry from the disk (not the original or the thumbnail) */
    public function deleteVariants(Media $media): void
    {
        $disk = Storage::disk(self::DISK);

        foreach (is_array($media->variants) ? $media->variants : [] as $variant) {
            $path = $variant['path'] ?? null;
            if (is_string($path) && $path !== '' && $path !== $media->path && $path !== $media->thumbnail_path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /** Whether the scaled copies are written as WebP */
    public static function writesWebp(): bool
    {
        return function_exists('imagewebp')
            && function_exists('gd_info')
            && (bool) (gd_info()['WebP Support'] ?? false)
            && (bool) config('cms.media.webp_variants', true);
    }

    /**
     * Displayed width and height (EXIF rotation applied), null when the file
     * is no readable image.
     *
     * @return array{0: int, 1: int}|null
     */
    public function dimensions(string $file): ?array
    {
        $info = @getimagesize($file);
        if (! is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return null;
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];

        // Photos taken upright are often stored sideways plus an EXIF flag
        return $this->orientation($file, (string) ($info['mime'] ?? '')) >= 5
            ? [$height, $width]
            : [$width, $height];
    }

    /** Widths to generate for an image of the given width */
    public function targetWidths(int $originalWidth): array
    {
        return array_values(array_filter(self::WIDTHS, fn (int $width) => $width < $originalWidth));
    }

    /** The 300 px thumbnail (same format as the original), null on failure */
    private function createThumbnail(string $file, string $storedPath): ?string
    {
        $info = @getimagesize($file);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        $image = $this->load($file, $mime);
        if ($image === null) {
            return null;
        }

        $directory = dirname($storedPath);
        $path = ($directory === '.' ? '' : $directory.'/').'thumb_'.basename($storedPath);
        $written = $this->writeScaled($image, self::THUMBNAIL_WIDTH, $mime, $path);

        return $written ? $path : null;
    }

    /**
     * Generate the scaled copies.
     *
     * @return list<array{width: int, height: int, path: string, mime: string}>
     */
    private function createVariants(string $file, string $storedPath): array
    {
        $info = @getimagesize($file);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (! in_array($mime, self::VARIANT_SOURCES, true)) {
            return [];
        }

        $size = $this->dimensions($file);
        if ($size === null || $this->targetWidths($size[0]) === []) {
            return [];
        }

        $image = $this->load($file, $mime);
        if ($image === null) {
            return [];
        }

        $outputMime = self::writesWebp() ? 'image/webp' : $mime;
        if ($outputMime === 'image/webp' && ! function_exists('imagewebp')) {
            return [];
        }
        $extension = ['image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$outputMime];
        $base = preg_replace('/\.[^.\/]+$/', '', $storedPath);

        $variants = [];
        foreach ($this->targetWidths(imagesx($image)) as $width) {
            $path = $base.'-'.$width.'w.'.$extension;
            $height = $this->writeScaled($image, $width, $outputMime, $path);
            if ($height !== null) {
                $variants[] = ['width' => $width, 'height' => $height, 'path' => $path, 'mime' => $outputMime];
            }
        }

        return $variants;
    }

    /**
     * Scale $image to $width (never up) and store it on the public disk.
     * Returns the height written, null on failure.
     */
    private function writeScaled(GdImage $image, int $width, string $mime, string $path): ?int
    {
        if ($mime === 'image/webp' && ! function_exists('imagewebp')) {
            return null;
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $width = min($width, $sourceWidth);
        $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));

        $target = imagecreatetruecolor($width, $height);
        if ($target === false) {
            return null;
        }

        // Keep transparency (PNG, WebP)
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $tmp = tempnam(sys_get_temp_dir(), 'img_');
        if ($tmp === false) {
            return null;
        }

        try {
            $ok = match ($mime) {
                'image/webp' => imagewebp($target, $tmp, 82),
                'image/png' => imagepng($target, $tmp, 6),
                'image/gif' => imagegif($target, $tmp),
                default => imagejpeg($target, $tmp, 85),
            };

            if (! $ok || ! is_file($tmp) || filesize($tmp) === 0) {
                return null;
            }

            Storage::disk(self::DISK)->put($path, (string) file_get_contents($tmp));

            return $height;
        } finally {
            @unlink($tmp);
        }
    }

    /** Load an image with GD and turn it upright; null when it cannot (or must not) be loaded */
    private function load(string $file, string $mime): ?GdImage
    {
        $info = @getimagesize($file);
        if (! is_array($info) || ! $this->fitsInMemory((int) $info[0], (int) $info[1])) {
            return null;
        }

        try {
            $image = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($file),
                'image/png' => @imagecreatefrompng($file),
                'image/gif' => @imagecreatefromgif($file),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
                default => false,
            };
        } catch (\Throwable) {
            return null;
        }

        if (! $image instanceof GdImage) {
            return null;
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $this->orient($image, $this->orientation($file, $mime));
    }

    /** EXIF orientation (1–8) of a JPEG, 1 when unknown */
    private function orientation(string $file, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($file);
        $value = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return $value >= 1 && $value <= 8 ? $value : 1;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        $rotate = fn (GdImage $source, int $angle): GdImage => ($rotated = imagerotate($source, $angle, 0)) instanceof GdImage ? $rotated : $source;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $rotate($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $rotate($image, -90);
                break;
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $rotate($image, 90);
                break;
        }

        return $image;
    }

    /**
     * Rough check whether GD can decode the image within the memory limit:
     * about 5 bytes per pixel for the source plus room for the largest copy.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            return false;
        }

        $limit = $this->memoryLimit();
        if ($limit <= 0) {
            return true;
        }

        $largest = min($width, max(self::WIDTHS));
        $needed = ($width * $height * 5) + ($largest * (int) ceil($height * $largest / $width) * 5);

        return $needed < ($limit - memory_get_usage(true)) * 0.9;
    }

    /** memory_limit in bytes, 0 for "unlimited" */
    private function memoryLimit(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Copy a file of the public disk to a local temporary file (works for remote disks too) */
    private function temporaryCopy(string $path): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'media_');
        if ($tmp === false) {
            return null;
        }

        $stream = Storage::disk(self::DISK)->readStream($path);
        if (! is_resource($stream)) {
            @unlink($tmp);

            return null;
        }

        $target = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $tmp;
    }
}
