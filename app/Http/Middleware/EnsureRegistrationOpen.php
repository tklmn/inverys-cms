<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registration disabled (Security → Allow registration):
 * /register then does not exist — 404 instead of a form that does nothing.
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(registration_open(), 404);

        return $next($request);
    }
}
