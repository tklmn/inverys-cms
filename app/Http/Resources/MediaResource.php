<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MediaResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'media';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'formatted_size' => $this->formatted_size,
            'type' => $this->type,
            'alt_text' => $this->alt_text,
            'is_decorative' => (bool) $this->is_decorative,
            // Focal point in percent from the left / top; null = center
            'focal_x' => $this->focal_x,
            'focal_y' => $this->focal_y,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** JSON:API forbids an attribute named `type`; expose the media kind as `media_type`. */
    protected function jsonApiAttributes(Request $request): array
    {
        $data = $this->data($request);
        $data['media_type'] = $data['type'] ?? null;

        return Arr::except($data, ['id', 'type']);
    }
}
