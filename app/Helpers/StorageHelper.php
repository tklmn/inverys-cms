<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class StorageHelper
{
    /**
     * Format file size in bytes to a human-readable string.
     */
    public static function formatFileSize(int $bytes): string
    {
        foreach (['GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                return Number::format($bytes / $size, 2, locale: app()->getLocale()).' '.$unit;
            }
        }

        return trans_choice('translation.media.size_bytes', $bytes, ['count' => Number::format($bytes, locale: app()->getLocale())]);
    }

    /**
     * Get the URL to a file in storage with the correct base path
     *
     * @param  bool  $protected  Whether the file should be served via the protected route (requiring authentication)
     */
    public static function url(?string $path, bool $protected = true): string
    {
        if (empty($path)) {
            return '';
        }

        // For avatar images, use the protected route that requires authentication
        if ($protected) {
            return route('protected.assets', ['path' => $path]);
        }

        // Otherwise use the standard storage URL
        $storageUrl = Storage::url($path);

        // If APP_URL is set and we're in local environment, handle the URL correctly
        $appUrl = config('app.url');
        if (! empty($appUrl) && app()->environment('local')) {
            // Remove any trailing slash from APP_URL
            $appUrl = rtrim($appUrl, '/');

            // Remove leading slash from storage URL to prevent double slashes
            $storageUrl = ltrim($storageUrl, '/');

            return "{$appUrl}/{$storageUrl}";
        }

        // In production, use the standard URL
        return $storageUrl;
    }
}
