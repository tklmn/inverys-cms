<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class BlockTypeResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'block-types';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'icon' => $this->icon,
            'is_core' => $this->is_core,
            'schema' => $this->schema,
        ];
    }
}
