<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Quick search in the admin area (Ctrl/⌘ + K): hits from all providers as JSON */
class AdminSearchController extends Controller
{
    public function __invoke(Request $request, AdminSearch $search): JsonResponse
    {
        $query = is_string($request->query('q')) ? mb_substr($request->query('q'), 0, 100) : '';

        return response()->json([
            'query' => $query,
            'groups' => $search->search($query, $request->user()),
        ]);
    }
}
