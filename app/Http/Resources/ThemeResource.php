<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ThemeResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'themes';
    }

    /** Themes are config-backed (not Eloquent models); the slug is their identifier. */
    protected function resourceId(): string
    {
        return (string) ($this->slug ?? '');
    }

    protected function data(Request $request): array
    {
        return [
            'slug' => $this->slug ?? '',
            'name' => $this->name ?? '',
            'version' => $this->version ?? '1.0.0',
            'description' => $this->description ?? '',
            'author' => $this->author ?? '',
        ];
    }
}
