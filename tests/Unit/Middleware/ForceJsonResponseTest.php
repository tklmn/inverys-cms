<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ForceJsonResponseTest extends TestCase
{
    private ForceJsonResponse $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ForceJsonResponse;
    }

    public function test_sets_accept_header_to_application_json(): void
    {
        $request = Request::create('/api/v1/pages', 'GET');

        $this->middleware->handle(
            $request,
            fn ($req) => new Response('OK')
        );

        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }

    public function test_overrides_existing_accept_header(): void
    {
        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Accept', 'text/html');

        $this->middleware->handle(
            $request,
            fn ($req) => new Response('OK')
        );

        $this->assertEquals('application/json', $request->headers->get('Accept'));
    }

    public function test_passes_request_to_next_handler(): void
    {
        $request = Request::create('/api/v1/pages', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK', 200)
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
