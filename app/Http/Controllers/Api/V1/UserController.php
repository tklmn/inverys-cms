<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->has('search')) {
            $search = SearchHelper::escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);

        return UserResource::collection($query->paginate($perPage));
    }

    public function show(User $user)
    {
        return new UserResource($user->load('roles'));
    }
}
