<?php

namespace App\Http\Middleware;

use App\Models\CmsNotification;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LogHttpErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        // Don't log notification API calls or AJAX media requests
        if (str_contains($request->path(), 'notifications/')) {
            return $response;
        }

        try {
            $trackedCodes = $this->getTrackedCodes();
            if (! in_array($status, $trackedCodes)) {
                return $response;
            }

            // Rate-limit logging to max 30 entries per minute to protect DB under error storms
            $rateLimitKey = 'http_error_log';
            if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
                return $response;
            }
            RateLimiter::hit($rateLimitKey, 60);

            // Deduplicate: same URL + status within last 5 minutes
            $exists = CmsNotification::where('type', 'http_error')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->whereJsonContains('data->status', $status)
                ->whereJsonContains('data->url', $request->fullUrl())
                ->exists();

            if (! $exists) {
                CmsNotification::create([
                    'type' => 'http_error',
                    'title' => "HTTP {$status}: {$request->method()} ".\Str::limit($request->path(), 120),
                    'body' => $this->statusLabel($status),
                    'data' => [
                        'status' => $status,
                        'method' => $request->method(),
                        'url' => \Str::limit($request->fullUrl(), 500),
                        'ip' => $request->ip(),
                        'user_agent' => \Str::limit($request->userAgent() ?? '', 200),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail — never break the response
        }

        return $response;
    }

    private function getTrackedCodes(): array
    {
        static $codes = null;
        if ($codes !== null) {
            return $codes;
        }

        try {
            $setting = Setting::where('key', 'notification.http_status_codes')->value('value');
            $codes = $setting
                ? array_map('intval', array_filter(explode(',', $setting)))
                : [500, 503];
        } catch (\Exception $e) {
            $codes = [500, 503];
        }

        return $codes;
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired (CSRF)',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => "HTTP Error {$status}",
        };
    }
}
