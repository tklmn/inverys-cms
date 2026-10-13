<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PluginResource;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function index()
    {
        return PluginResource::collection(Plugin::all());
    }

    public function update(Request $request, string $slug, PluginManager $pluginManager)
    {
        $request->validate(['enabled' => 'required|boolean']);

        if ($request->enabled) {
            $pluginManager->enable($slug);
        } else {
            $pluginManager->disable($slug);
        }

        $plugin = Plugin::where('slug', $slug)->first();

        if (! $plugin) {
            return response()->json(['message' => 'Plugin not found.'], 404);
        }

        return new PluginResource($plugin);
    }
}
