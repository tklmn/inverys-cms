<?php

namespace App\Http\Controllers;

use App\Services\PluginManager;
use App\Services\ThemeManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Static files of themes and plugins: /ext-assets/{theme|plugin}/{slug}/{path}.
 *
 * This lets a theme ship its CSS as a file the browser caches —
 * even if it arrived as a ZIP and nothing can be built on the server.
 *
 *   - only from the package's assets/ folder, only known file types
 *   - no hidden files, no way out of assets/ (not even via symlink)
 *   - with ?v=<version> cached for a year (the version changes with every update)
 */
class ExtensionAssetController extends Controller
{
    private const TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
    ];

    public function show(string $type, string $slug, string $path, ThemeManager $themes, PluginManager $plugins): BinaryFileResponse
    {
        $root = match ($type) {
            'theme' => $themes->get($slug)['path'] ?? null,
            'plugin' => $plugins->get($slug)['path'] ?? null,
            default => null,
        };

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $segments = explode('/', $path);

        if ($root === null || ! isset(self::TYPES[$extension])
            || array_filter($segments, fn ($s) => $s === '' || str_starts_with($s, '.')) !== []) {
            throw new NotFoundHttpException;
        }

        $base = realpath($root.'/assets');
        $file = realpath($root.'/assets/'.$path);

        if ($base === false || $file === false || ! is_file($file) || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException;
        }

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => self::TYPES[$extension],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => request()->has('v') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
        ], true, null, true, true);

        // An SVG is a document — opened as a file it must not execute anything
        if ($extension === 'svg') {
            $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }

        $response->isNotModified(request());

        return $response;
    }
}
