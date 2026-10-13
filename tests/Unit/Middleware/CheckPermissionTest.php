<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CheckPermissionTest extends TestCase
{
    use RefreshDatabase;

    private CheckPermission $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckPermission;
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);

        try {
            $this->middleware->handle($request, fn () => new Response, 'edit-page');
        } catch (HttpException $e) {
            $this->assertEquals(401, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'user'], ['is_system' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        try {
            $this->middleware->handle($request, fn () => new Response, 'edit-page');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_user_with_required_permission_passes_through(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'editor']);
        $perm = Permission::firstOrCreate(['name' => 'edit-page']);
        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        $this->actingAs($user);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK'),
            'edit-page'
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_admin_with_all_permissions_passes_through(): void
    {
        $this->seedPermissions();
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK'),
            'edit-page'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }
}
