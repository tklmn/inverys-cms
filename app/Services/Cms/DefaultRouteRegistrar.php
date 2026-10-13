<?php

namespace App\Services\Cms;

use App\Contracts\RouteRegistrar;
use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

class DefaultRouteRegistrar implements RouteRegistrar
{
    public function web(string $prefix, callable $routes): void
    {
        Route::middleware('web')
            ->prefix($prefix)
            ->group($routes);
    }

    public function backend(string $prefix, callable $routes): void
    {
        Route::middleware(['web', 'auth', 'verified', 'permission:access-dashboard'])
            ->prefix('backend/'.$prefix)
            ->group($routes);
    }

    public function api(string $prefix, callable $routes): void
    {
        Route::middleware([
            ForceJsonResponse::class,
            ApiRateLimit::class,
            SubstituteBindings::class,
        ])
            ->prefix('api/v1/'.$prefix)
            ->group($routes);
    }

    public function apiProtected(string $prefix, callable $routes): void
    {
        // Like the core API: JSON responses and rate limit for writing plugin
        // endpoints too
        Route::middleware([
            ForceJsonResponse::class,
            ApiRateLimit::class,
            'api.auth',
            'permission:api-access',
            SubstituteBindings::class,
        ])
            ->prefix('api/v1/'.$prefix)
            ->group($routes);
    }
}
