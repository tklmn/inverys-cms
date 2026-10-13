<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RedirectIfNotInstalled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Concerns\PreservesInstalledMarker;
use Tests\TestCase;

class RedirectIfNotInstalledTest extends TestCase
{
    use PreservesInstalledMarker;

    private RedirectIfNotInstalled $middleware;

    private string $installedPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RedirectIfNotInstalled;
        $this->installedPath = $this->installedMarkerPath();
    }

    public function test_redirects_to_install_when_not_installed(): void
    {
        // Ensure no installed file
        if (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }

        $request = Request::create('/admin/dashboard', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/install', $response->headers->get('Location'));
    }

    public function test_passes_through_when_installed(): void
    {
        file_put_contents($this->installedPath, now()->toDateTimeString());

        $request = Request::create('/admin/dashboard', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_install_routes_are_not_redirected(): void
    {
        // Ensure no installed file
        if (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }

        $request = Request::create('/install', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('Installer')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Installer', $response->getContent());
    }

    public function test_install_sub_routes_are_not_redirected(): void
    {
        // Ensure no installed file
        if (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }

        $request = Request::create('/install/database', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('DB Setup')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('DB Setup', $response->getContent());
    }
}
