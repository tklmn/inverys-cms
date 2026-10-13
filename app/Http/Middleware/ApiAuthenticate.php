<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiToken = ApiToken::findByPlainText($bearer);

        if (! $apiToken) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['message' => 'Token expired.'], 401);
        }

        $user = $apiToken->user;

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Account inactive.'], 403);
        }

        // Enforce the token's scope: read-only tokens may only perform safe (read) requests.
        $required = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) ? 'read' : 'write';
        if (! $apiToken->hasAbility($required)) {
            return response()->json(['message' => 'This token is not permitted to perform this action.'], 403);
        }

        $apiToken->update(['last_used_at' => now()]);

        $request->attributes->set('api_token', $apiToken);
        auth()->setUser($user);

        return $next($request);
    }
}
