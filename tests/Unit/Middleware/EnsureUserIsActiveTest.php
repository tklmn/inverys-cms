<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    private EnsureUserIsActive $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->middleware = new EnsureUserIsActive;
    }

    public function test_active_user_passes_through(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(session()->driver());
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_inactive_user_is_logged_out_and_redirected(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user);

        $request = Request::create('/admin/dashboard', 'GET');
        $request->setLaravelSession(session()->driver());
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertTrue($response->isRedirect());
        $this->assertStringContains('login', $response->headers->get('Location'));
        $this->assertFalse(Auth::check());
    }

    public function test_guest_passes_through(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(session()->driver());

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * Assert that a string contains a substring.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
