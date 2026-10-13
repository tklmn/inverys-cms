<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiRateLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private ApiRateLimit $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiRateLimit;
        Cache::flush();
    }

    public function test_request_under_limit_passes_through_with_headers(): void
    {
        $request = Request::create('/api/v1/pages', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
        $this->assertEquals(60, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(59, $response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_request_at_limit_returns_429(): void
    {
        $ip = '192.168.1.100';
        $key = 'api_rate_limit:'.$ip;

        // Pre-fill the cache to simulate 60 previous requests
        Cache::put($key, 60, now()->addMinutes(1));
        Cache::put($key.':timer', 60, now()->addMinutes(1));

        $request = Request::create('/api/v1/pages', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Too many requests.', $data['message']);
    }

    public function test_response_includes_rate_limit_headers(): void
    {
        $request = Request::create('/api/v1/pages', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertEquals(60, $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_429_response_includes_rate_limit_headers(): void
    {
        $ip = '10.0.0.2';
        $key = 'api_rate_limit:'.$ip;

        Cache::put($key, 60, now()->addMinutes(1));
        Cache::put($key.':timer', 60, now()->addMinutes(1));

        $request = Request::create('/api/v1/pages', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
        ]);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertEquals(60, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    public function test_remaining_count_decreases_with_each_request(): void
    {
        $request = Request::create('/api/v1/pages', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.3',
        ]);

        $response1 = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );
        $this->assertEquals(59, $response1->headers->get('X-RateLimit-Remaining'));

        $response2 = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );
        $this->assertEquals(58, $response2->headers->get('X-RateLimit-Remaining'));
    }
}
