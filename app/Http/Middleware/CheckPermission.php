<?php

namespace App\Http\Middleware;

use App\Support\CodeAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission Check Middleware
 *
 * This middleware verifies if the authenticated user has a specific permission.
 * If not, it aborts with a 403 Forbidden response.
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $permission  The permission to check
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (! auth()->check()) {
            abort(401, 'Unauthenticated');
        }

        if (! auth()->user()->hasPermission($permission)) {
            abort(403, 'Access Denied (Missing Permission)');
        }

        // Permissions that allow putting code on the server: admins only,
        // even if another role (still) holds the permission
        if (CodeAccess::isAdminOnly($permission) && ! auth()->user()->hasRole('admin')) {
            abort(403, 'Access Denied (Administrators only)');
        }

        return $next($request);
    }
}
