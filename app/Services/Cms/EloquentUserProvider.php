<?php

namespace App\Services\Cms;

use App\Contracts\CmsUserProvider;
use App\Models\User;

class EloquentUserProvider implements CmsUserProvider
{
    public function first(): mixed
    {
        return User::first();
    }

    public function find(int $id): mixed
    {
        return User::find($id);
    }

    public function currentUser(): mixed
    {
        return auth()->user();
    }

    public function currentUserId(): ?int
    {
        return auth()->id();
    }

    public function allForSelect(): array
    {
        return User::orderBy('name')->pluck('name', 'id')->all();
    }
}
