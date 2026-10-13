<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockTypeController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ThemeController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Authenticated routes with API access permission
    Route::middleware(['api.auth', 'permission:api-access'])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Pages
        Route::get('pages', [PageController::class, 'index'])->middleware('permission:show-page');
        Route::get('pages/{page}', [PageController::class, 'show'])->middleware('permission:show-page');
        Route::post('pages', [PageController::class, 'store'])->middleware('permission:create-page');
        Route::put('pages/{page}', [PageController::class, 'update'])->middleware('permission:edit-page');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->middleware('permission:delete-page');

        // Media
        Route::get('media', [MediaController::class, 'index'])->middleware('permission:show-media');
        Route::get('media/{media}', [MediaController::class, 'show'])->middleware('permission:show-media');
        Route::post('media', [MediaController::class, 'store'])->middleware('permission:create-media');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->middleware('permission:delete-media');

        // Users (read-only)
        Route::get('users', [UserController::class, 'index'])->middleware('permission:show-user');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:show-user');

        // Settings
        Route::get('settings', [SettingController::class, 'index'])->middleware('permission:show-settings');
        Route::put('settings/{key}', [SettingController::class, 'update'])->middleware('permission:edit-settings');

        // Block types
        Route::get('blocks/types', [BlockTypeController::class, 'index'])->middleware('permission:manage-blocks');

        // Themes
        Route::get('themes', [ThemeController::class, 'index'])->middleware('permission:manage-themes');
        Route::put('themes/active', [ThemeController::class, 'activate'])->middleware('permission:manage-themes');

        // Plugins
        Route::get('plugins', [PluginController::class, 'index'])->middleware('permission:manage-plugins');
        Route::put('plugins/{slug}', [PluginController::class, 'update'])->middleware('permission:manage-plugins');
    });
});
