<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ExtensionRegistrar;
use App\Events\MediaUploaded;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Rules\WebVttFile;
use App\Services\MediaImageProcessor;
use App\Support\EditLocale;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

#[Middleware('permission:show-media', only: ['index', 'show', 'getMedia'])]
#[Middleware('permission:create-media', only: ['create', 'store', 'ajaxUpload'])]
#[Middleware('permission:edit-media', only: ['edit', 'update'])]
#[Middleware('permission:delete-media', only: ['destroy', 'restore'])]
class MediaController extends Controller
{
    /**
     * Display a listing of the media.
     */
    public function index(Request $request): View|JsonResponse
    {
        $media = Media::latest()
            ->ofType($request->type)
            ->search($request->search)
            ->when($request->query('alt') === 'missing', fn ($query) => $query->missingAltText())
            ->paginate(config('cms.pagination.media'))
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $media->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'path' => $item->path,
                    'storage_path' => 'storage/'.$item->path,
                    'url' => $item->url,
                    'thumbnail' => $item->thumbnail_url,
                    'alt_text' => $item->alt_text,
                    'is_decorative' => (bool) $item->is_decorative,
                    'focal_x' => $item->focal_x,
                    'focal_y' => $item->focal_y,
                    'mime_type' => $item->mime_type,
                ]),
            ]);
        }

        $missingAltCount = Media::missingAltText()->count();

        return view('backend.media.index', compact('media', 'missingAltCount'));
    }

    /**
     * Show the form for creating new media.
     */
    public function create(): View
    {
        return view('backend.media.create');
    }

    /**
     * Store newly uploaded media.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            ...$this->uploadValidationRules($request),
            'description' => 'nullable|string|max:1000',
        ]);

        $media = $this->processUpload($request);

        return redirect()
            ->route('backend.media.index')
            ->with('success', __('translation.media.uploaded'));
    }

    /**
     * Display the specified media.
     */
    public function show(Media $media): View
    {
        $usages = $this->collectUsages($media);

        return view('backend.media.show', compact('media', 'usages'));
    }

    /**
     * Show the form for editing the specified media.
     */
    public function edit(Media $media): View
    {
        $usages = $this->collectUsages($media);

        return view('backend.media.edit', compact('media', 'usages'));
    }

    /**
     * Update the specified media.
     */
    public function update(Request $request, Media $media): RedirectResponse
    {
        // Translation: only alt text and description, the original stays
        if ($locale = EditLocale::current($request)) {
            Translations::saveFromRequest($request, $media, $locale, [
                'alt_text' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
            ], null);

            return redirect()
                ->route('backend.media.edit', ['media' => $media, 'lang' => $locale])
                ->with('success', __('translation.media.updated'));
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'is_decorative' => 'nullable|boolean',
            ...Media::focalPointRules(),
            'description' => 'nullable|string|max:1000',
        ]);

        $media->update([
            'name' => $request->name,
            'alt_text' => $request->alt_text,
            // Shared by all languages; only sent by the form of the original
            'is_decorative' => $request->has('is_decorative') ? $request->boolean('is_decorative') : $media->is_decorative,
            'description' => $request->description,
            // Focal point (shared by all languages): empty fields = center
            ...($request->has('focal_x') || $request->has('focal_y')
                ? Media::focalPointFrom($request->input('focal_x'), $request->input('focal_y'))
                : []),
        ]);

        return redirect()
            ->route('backend.media.edit', $media)
            ->with('success', __('translation.media.updated'));
    }

    /**
     * Remove the specified media.
     */
    public function destroy(Media $media): RedirectResponse
    {
        // Soft delete only — physical files are removed on forceDelete (see Media::booted)
        // so the item remains restorable from the trash.
        $media->delete();

        return redirect()
            ->route('backend.media.index')
            ->with('success', __('translation.media.deleted'));
    }

    /**
     * Handle AJAX upload
     */
    public function ajaxUpload(Request $request): JsonResponse
    {
        $request->validate($this->uploadValidationRules($request));

        $media = $this->processUpload($request);

        return response()->json([
            'success' => true,
            'id' => $media->id,
            'path' => $media->path,
            'storage_path' => 'storage/'.$media->path,
            'url' => asset('storage/'.$media->path),
            'thumbnail' => $media->thumbnail_path ? asset('storage/'.$media->thumbnail_path) : null,
            'name' => $media->name,
            'type' => $media->mime_type,
            'alt' => $media->alt_text,
            'alt_text' => $media->alt_text,
            'is_decorative' => (bool) $media->is_decorative,
            'focal_x' => $media->focal_x,
            'focal_y' => $media->focal_y,
        ]);
    }

    /**
     * Get media API endpoint
     */
    public function getMedia(Request $request): JsonResponse
    {
        $media = Media::latest()
            ->ofType($request->type)
            ->search($request->search)
            ->paginate(config('cms.pagination.media'));

        $formattedMedia = $media->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'path' => $item->path,
                'storage_path' => 'storage/'.$item->path,
                'url' => asset('storage/'.$item->path),
                // Files without a thumbnail (captions, PDFs …) show their type icon in pickers
                'thumbnail' => $item->thumbnail_path ? asset('storage/'.$item->thumbnail_path) : (str_starts_with((string) $item->mime_type, 'image/') ? null : $item->thumbnail_url),
                'media_type' => $item->type,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'alt' => $item->alt_text,
                'alt_text' => $item->alt_text,
                'is_decorative' => (bool) $item->is_decorative,
                'focal_x' => $item->focal_x,
                'focal_y' => $item->focal_y,
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $formattedMedia,
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Collect media usages from all registered checkers.
     */
    private function collectUsages(Media $media): array
    {
        $usages = [];

        foreach (app(ExtensionRegistrar::class)->getMediaUsageCheckers() as $checker) {
            try {
                $usages = array_merge($usages, $checker->getUsages($media));
            } catch (\Exception $e) {
                // Skip silently — plugin may be partially uninstalled
            }
        }

        return $usages;
    }

    /** Allowed file types — one list for validation and the hint in the upload form */
    public const UPLOAD_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mp3', 'zip'];

    /** Caption files for videos (WebVTT). Checked by content, not by MIME type: see WebVttFile */
    public const CAPTION_TYPES = ['vtt'];

    /** Caption files are small text files; anything bigger is not one */
    public const CAPTION_MAX_KB = 1024;

    /** Every extension the upload accepts (form hint, accept attribute) */
    public static function allowedExtensions(): array
    {
        return [...self::UPLOAD_TYPES, ...self::CAPTION_TYPES];
    }

    private static function isCaptionFile(mixed $file): bool
    {
        return $file instanceof UploadedFile
            && in_array(strtolower($file->getClientOriginalExtension()), self::CAPTION_TYPES, true);
    }

    /** MIME type stored for an upload: to finfo a VTT file is just "text/plain" */
    public static function storedMimeType(UploadedFile $file): string
    {
        return self::isCaptionFile($file) ? 'text/vtt' : (string) $file->getMimeType();
    }

    /**
     * Rules for the uploaded file — shared with the API. Caption files get
     * their own check (extension + "WEBVTT" signature + text only), all
     * other files the MIME and extension check.
     *
     * @return list<mixed>
     */
    public static function fileRules(mixed $file): array
    {
        if (self::isCaptionFile($file)) {
            return ['required', 'file', 'max:'.self::CAPTION_MAX_KB, new WebVttFile];
        }

        return [
            'required',
            // types() only checks the content (MIME) — but the file is stored with
            // the client's extension. Without extensions() a JPEG named
            // "x.html" would pass and be served as HTML (stored XSS).
            File::types(self::UPLOAD_TYPES)
                ->extensions(self::UPLOAD_TYPES)
                ->max(config('cms.max_upload_size')),
        ];
    }

    /**
     * Shared validation rules for file uploads.
     */
    private function uploadValidationRules(Request $request): array
    {
        return [
            'file' => self::fileRules($request->file('file')),
            'alt_text' => 'nullable|string|max:255',
            'is_decorative' => 'nullable|boolean',
        ];
    }

    /**
     * Process an uploaded file: store it, create the thumbnail, size and
     * scaled copies for images (MediaImageProcessor), persist the Media
     * record, and dispatch the MediaUploaded event.
     */
    private function processUpload(Request $request): Media
    {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $name = pathinfo($filename, PATHINFO_FILENAME);
        // Validation limits the extension to UPLOAD_TYPES and CAPTION_TYPES
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = self::storedMimeType($file);

        // Generate a unique filename
        $uniqueFilename = Str::uuid().'.'.$extension;

        // Store the file
        $path = 'media/'.date('Y/m');
        $file->storeAs($path, $uniqueFilename, 'public');
        $fullPath = $path.'/'.$uniqueFilename;

        // Images: size, backend thumbnail and the scaled copies for srcset
        $image = app(MediaImageProcessor::class)->processUpload($file->getRealPath(), $fullPath, $mimeType);

        // Create media record
        $media = Media::create([
            'name' => $name,
            'file_name' => $uniqueFilename,
            'path' => $fullPath,
            'thumbnail_path' => $image['thumbnail_path'] ?? null,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'width' => $image['width'],
            'height' => $image['height'],
            'variants' => $image['variants'],
            // Never the file name: "IMG_2041" helps no one. Empty means "still to do"
            // (see Media::needsAltText()) unless the image is marked decorative.
            'alt_text' => $request->filled('alt_text') ? $request->alt_text : null,
            'is_decorative' => $request->boolean('is_decorative'),
            'description' => $request->description ?? '',
            'user_id' => auth()->id(),
        ]);

        MediaUploaded::dispatch($media);

        return $media;
    }
}
