<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MediaUploaded;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        return MediaResource::collection(
            Media::latest()
                ->ofType($request->type)
                ->search($request->search)
                ->paginate(min((int) ($request->per_page ?? 20), 100))
        );
    }

    public function show(Media $media)
    {
        return new MediaResource($media);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Same rules as the backend upload: MIME + extension (the file is stored
            // with the client extension), caption files (.vtt) by their content
            'file' => AdminMediaController::fileRules($request->file('file')),
            'alt_text' => 'nullable|string|max:255',
            'is_decorative' => 'nullable|boolean',
            ...Media::focalPointRules(),
            'description' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $uniqueFilename = Str::uuid().'.'.$extension;
        $path = 'media/'.date('Y/m');

        $file->storeAs($path, $uniqueFilename, 'public');
        $mimeType = AdminMediaController::storedMimeType($file);

        // Same image handling as the backend: size, thumbnail, scaled copies
        $image = app(MediaImageProcessor::class)->processUpload($file->getRealPath(), $path.'/'.$uniqueFilename, $mimeType);

        $media = Media::create([
            'name' => $name,
            'file_name' => $uniqueFilename,
            'path' => $path.'/'.$uniqueFilename,
            'thumbnail_path' => $image['thumbnail_path'] ?? null,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'width' => $image['width'],
            'height' => $image['height'],
            'variants' => $image['variants'],
            'alt_text' => $request->alt_text,
            'is_decorative' => $request->boolean('is_decorative'),
            ...Media::focalPointFrom($request->input('focal_x'), $request->input('focal_y')),
            'description' => $request->description,
            'user_id' => auth()->id(),
        ]);

        MediaUploaded::dispatch($media);

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function destroy(Media $media): JsonResponse
    {
        // As in the backend, only to the trash: the files are only removed by
        // permanent deletion (Media::booted), which requires manage-trash.
        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }
}
