<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProtectedAssetController extends Controller
{
    /**
     * Handle requests for protected assets like user avatars
     * This controller ensures only authenticated users can access these files
     *
     * @param  Request  $request
     * @param  string  $path  Path relative to the public disk (e.g., 'avatars/filename.jpg')
     * @return StreamedResponse
     */
    private const ALLOWED_DIRECTORIES = ['avatars', 'media'];

    public function show(Request $request, string $path)
    {
        // Prevent directory traversal by resolving and validating the real path
        $fullPath = Storage::disk('public')->path($path);
        $basePath = Storage::disk('public')->path('');

        if (! str_starts_with(realpath(dirname($fullPath)).'/', realpath($basePath).'/')) {
            throw new NotFoundHttpException('File not found');
        }

        // Only serve files from allowed directories
        $topDir = explode('/', trim($path, '/'))[0] ?? '';
        if (! in_array($topDir, self::ALLOWED_DIRECTORIES, true)) {
            throw new NotFoundHttpException('File not found');
        }

        if (! Storage::disk('public')->exists($path)) {
            throw new NotFoundHttpException('File not found');
        }

        return Storage::disk('public')->response($path);
    }
}
