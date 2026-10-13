<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cache-busting value for files from a theme's or plugin's assets/: the
 * package version plus the file's modification time. The browser keeps such
 * a file for a year, so every change to the file needs a new address — also
 * when the version stays the same.
 */
final class AssetVersion
{
    public static function for(string $version, string $file): string
    {
        $mtime = is_file($file) ? @filemtime($file) : false;

        return $mtime ? ltrim($version.'-'.base_convert((string) $mtime, 10, 36), '-') : $version;
    }

    /** "?v=…" for an asset address, or "" without version and file */
    public static function query(string $version, string $file): string
    {
        $value = self::for($version, $file);

        return $value !== '' ? '?v='.rawurlencode($value) : '';
    }
}
