<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MediaUploaded;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

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
            'file' => [
                'required',
                // extensions(): the file is stored with the client extension — the
                // MIME check alone would let a JPEG named "x.html" through as HTML.
                File::types(AdminMediaController::UPLOAD_TYPES)
                    ->extensions(AdminMediaController::UPLOAD_TYPES)
                    ->max(config('cms.max_upload_size', 10 * 1024)),
            ],
            'alt_text' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $file = $request->file('file');
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $uniqueFilename = Str::uuid().'.'.$extension;
        $path = 'media/'.date('Y/m');

        $file->storeAs($path, $uniqueFilename, 'public');

        $media = Media::create([
            'name' => $name,
            'file_name' => $uniqueFilename,
            'path' => $path.'/'.$uniqueFilename,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $request->alt_text,
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
