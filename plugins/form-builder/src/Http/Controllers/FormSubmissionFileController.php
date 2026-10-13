<?php

namespace Plugins\FormBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Middleware('auth')]
#[Middleware('permission:show-form-submission')]
class FormSubmissionFileController extends Controller
{
    public function show(Request $request, string $formSlug, string $filename)
    {
        $relativePath = 'form-submissions/'.$formSlug.'/'.$filename;

        // Validate the file exists on the local disk
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new NotFoundHttpException('File not found');
        }

        // Prevent path traversal by verifying the resolved real path
        $fullPath = Storage::disk('local')->path($relativePath);
        $basePath = Storage::disk('local')->path('form-submissions');

        $realFullPath = realpath($fullPath);
        $realBasePath = realpath($basePath);

        if ($realFullPath === false || $realBasePath === false || ! str_starts_with($realFullPath, $realBasePath.DIRECTORY_SEPARATOR)) {
            throw new NotFoundHttpException('File not found');
        }

        // Force a download (attachment) with nosniff so a malicious upload (e.g. an
        // SVG/HTML masquerading past the blocklist) can never execute inline on our origin.
        return Storage::disk('local')->download($relativePath, $filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
