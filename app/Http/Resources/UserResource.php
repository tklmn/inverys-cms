<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends JsonApiResource
{
    protected function resourceType(): string
    {
        return 'users';
    }

    protected function data(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'job_title' => $this->job_title,
            'is_active' => $this->is_active,
            'roles' => $this->roles->pluck('name'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
