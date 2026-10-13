<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PageResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'pages';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'content' => $this->content,
            'content_type' => $this->content_type ?? 'html',
            'blocks' => $this->blocks,
            'parent_id' => $this->parent_id,
            'level' => $this->level,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'is_root' => $this->is_root,
            'template' => $this->template,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
