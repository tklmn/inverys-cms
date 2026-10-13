<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ApiRateLimit
{
    protected int $decaySeconds = 60;

    public function handle(Request $request, Closure $next)
    {
        $maxAttempts = max(10, (int) setting('security.api_rate_limit', '60'));
        // Per IP address: this runs before api.auth, so no user is known yet — and keying
        // on the unverified token would give every made-up token its own budget
        $key = 'api_rate_limit:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        RateLimiter::hit($key, $this->decaySeconds);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
        ]);
    }
}
