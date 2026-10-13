<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PluginResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'plugins';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'author' => $this->author,
            'is_enabled' => $this->is_enabled,
            'installed_at' => $this->installed_at?->toISOString(),
            'enabled_at' => $this->enabled_at?->toISOString(),
        ];
    }
}
