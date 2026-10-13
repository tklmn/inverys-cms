<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role Check Middleware
 *
 * This middleware verifies if the authenticated user has a specific role.
 * If not, it aborts with a 403 Forbidden response.
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $role  The role to check for
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        if (auth()->check() && auth()->user()->hasRole($role)) {
            return $next($request);
        }

        abort(403, 'Access Denied');
    }
}
