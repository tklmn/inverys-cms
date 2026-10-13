<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThemeResource;
use App\Services\ThemeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index(ThemeManager $themeManager): JsonResponse
    {
        $themes = collect($themeManager->all())->map(function ($theme) {
            return new ThemeResource((object) $theme);
        });

        return response()->json([
            'data' => $themes,
            'active' => $themeManager->getActive(),
        ]);
    }

    public function activate(Request $request, ThemeManager $themeManager): JsonResponse
    {
        $request->validate(['slug' => 'required|string']);

        if ($themeManager->activate($request->slug)) {
            return response()->json(['message' => 'Theme activated.', 'active' => $request->slug]);
        }

        return response()->json(['message' => 'Theme not found.'], 404);
    }
}
