<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ExtensionRegistrar;
use App\Events\MediaUploaded;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\EditLocale;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
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
            ->paginate(config('cms.pagination.media'));

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
                    'mime_type' => $item->mime_type,
                ]),
            ]);
        }

        return view('backend.media.index', compact('media'));
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
            ...$this->uploadValidationRules(),
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
            'description' => 'nullable|string|max:1000',
        ]);

        $media->update([
            'name' => $request->name,
            'alt_text' => $request->alt_text,
            'description' => $request->description,
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
        $request->validate($this->uploadValidationRules());

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
                'thumbnail' => $item->thumbnail_path ? asset('storage/'.$item->thumbnail_path) : null,
                'mime_type' => $item->mime_type,
                'size' => $item->size,
                'alt' => $item->alt_text,
                'alt_text' => $item->alt_text,
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

    /**
     * Shared validation rules for file uploads.
     */
    /** Allowed file types — one list for validation and the hint in the upload form */
    public const UPLOAD_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'mp3', 'zip'];

    private function uploadValidationRules(): array
    {
        return [
            'file' => [
                'required',
                // types() only checks the content (MIME) — but the file is stored with
                // the client's extension. Without extensions() a JPEG named
                // "x.html" would pass and be served as HTML (stored XSS).
                File::types(self::UPLOAD_TYPES)
                    ->extensions(self::UPLOAD_TYPES)
                    ->max(config('cms.max_upload_size')),
            ],
            'alt_text' => 'nullable|string|max:255',
        ];
    }

    /**
     * Process an uploaded file: store it, create a thumbnail for images,
     * persist the Media record, and dispatch the MediaUploaded event.
     */
    private function processUpload(Request $request): Media
    {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $name = pathinfo($filename, PATHINFO_FILENAME);
        // Validation limits the extension to UPLOAD_TYPES
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Generate a unique filename
        $uniqueFilename = Str::uuid().'.'.$extension;

        // Store the file
        $path = 'media/'.date('Y/m');
        $file->storeAs($path, $uniqueFilename, 'public');
        $fullPath = $path.'/'.$uniqueFilename;

        // Generate thumbnail for images. Render to a temp file first, then store it
        // through the Storage disk so it honours the configured (or faked) filesystem
        // and the target directory is always created.
        $thumbnailPath = null;
        if (str_starts_with($mimeType, 'image/')) {
            $thumbnailPath = $path.'/thumb_'.$uniqueFilename;
            $tmpThumb = tempnam(sys_get_temp_dir(), 'thumb_');

            if ($this->createThumbnail($file->getRealPath(), $tmpThumb)) {
                Storage::disk('public')->put($thumbnailPath, file_get_contents($tmpThumb));
            } else {
                $thumbnailPath = null;
            }

            if (is_string($tmpThumb) && file_exists($tmpThumb)) {
                @unlink($tmpThumb);
            }
        }

        // Create media record
        $media = Media::create([
            'name' => $name,
            'file_name' => $uniqueFilename,
            'path' => $fullPath,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'alt_text' => $request->alt_text ?? $name,
            'description' => $request->description ?? '',
            'user_id' => auth()->id(),
        ]);

        MediaUploaded::dispatch($media);

        return $media;
    }

    /**
     * Create a thumbnail from an image
     *
     * @param  string  $sourcePath  The source image path
     * @param  string  $targetPath  The target thumbnail path
     * @param  int  $width  The desired width
     * @return bool
     */
    private function createThumbnail($sourcePath, $targetPath, $width = 300)
    {
        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$originalWidth, $originalHeight] = $imageInfo;
        $mimeType = $imageInfo['mime'];

        // Calculate new dimensions (never upscale, never round down to a zero-height image)
        $ratio = $originalWidth / $originalHeight;
        $newWidth = min($width, $originalWidth);
        $newHeight = max(1, (int) floor($newWidth / $ratio));

        // Create new image
        $sourceImage = $this->createImageFromFile($sourcePath);

        if (! $sourceImage) {
            return false;
        }

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
        imagefill($thumbImage, 0, 0, $transparent);

        // Resize
        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        // Save thumbnail based on mime type
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbImage, $targetPath, 90);
                break;
            case 'image/png':
                imagepng($thumbImage, $targetPath, 9);
                break;
            case 'image/gif':
                imagegif($thumbImage, $targetPath);
                break;
            case 'image/webp':
                imagewebp($thumbImage, $targetPath, 90);
                break;
            default:
                // If we can't determine the type, try JPEG as a fallback
                imagejpeg($thumbImage, $targetPath, 90);
                break;
        }

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return true;
    }

    /**
     * Create an image resource from file
     *
     * @param  string  $filepath
     * @return resource|false
     */
    private function createImageFromFile($filepath)
    {
        // Detect image type using getimagesize
        $imageInfo = getimagesize($filepath);
        if ($imageInfo === false) {
            return false;
        }

        $mimeType = $imageInfo['mime'];

        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filepath);
            case 'image/png':
                return imagecreatefrompng($filepath);
            case 'image/gif':
                return imagecreatefromgif($filepath);
            case 'image/webp':
                return imagecreatefromwebp($filepath);
            default:
                return false;
        }
    }
}
