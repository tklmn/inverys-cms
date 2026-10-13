<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckRoleTest extends TestCase
{
    use RefreshDatabase;

    private CheckRole $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckRole;
    }

    public function test_unauthenticated_user_gets_403(): void
    {
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);

        try {
            $this->middleware->handle($request, fn () => new Response, 'admin');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_user_without_role_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user'], ['is_system' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        try {
            $this->middleware->handle($request, fn () => new Response, 'admin');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_user_with_correct_role_passes_through(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['is_system' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK'),
            'admin'
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
