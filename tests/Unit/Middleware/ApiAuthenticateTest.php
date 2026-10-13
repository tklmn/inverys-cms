<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ApiAuthenticate;
use App\Models\User;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ApiAuthenticateTest extends TestCase
{
    use RefreshDatabase;

    private ApiAuthenticate $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ApiAuthenticate;
    }

    public function test_request_without_bearer_token_returns_401(): void
    {
        $request = Request::create('/api/v1/pages', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthenticated.', $data['message']);
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token-value');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid token.', $data['message']);
    }

    public function test_request_with_expired_token_returns_401(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $result = ApiTokenFactory::createWithPlainText($user, [
            'expires_at' => now()->subHour(),
        ]);

        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$result['plain_text']);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Token expired.', $data['message']);
    }

    public function test_request_with_valid_token_authenticates_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $result = ApiTokenFactory::createWithPlainText($user);

        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$result['plain_text']);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(auth()->check());
        $this->assertEquals($user->id, auth()->id());
        $this->assertNotNull($request->get('api_token'));
    }

    public function test_request_with_valid_token_for_inactive_user_returns_403(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $result = ApiTokenFactory::createWithPlainText($user);

        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$result['plain_text']);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Account inactive.', $data['message']);
    }

    public function test_last_used_at_is_updated_on_successful_auth(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->assertNull($result['token']->last_used_at);

        $request = Request::create('/api/v1/pages', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$result['plain_text']);

        $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $result['token']->refresh();
        $this->assertNotNull($result['token']->last_used_at);
    }
}
