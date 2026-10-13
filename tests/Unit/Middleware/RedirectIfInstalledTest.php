<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RedirectIfInstalled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Concerns\PreservesInstalledMarker;
use Tests\TestCase;

class RedirectIfInstalledTest extends TestCase
{
    use PreservesInstalledMarker;

    private RedirectIfInstalled $middleware;

    private string $installedPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RedirectIfInstalled;
        $this->installedPath = $this->installedMarkerPath();
    }

    public function test_redirects_when_installed_file_exists(): void
    {
        file_put_contents($this->installedPath, now()->toDateTimeString());

        $request = Request::create('/install', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertTrue($response->isRedirect());
        $location = $response->headers->get('Location');
        $this->assertTrue(
            $location === url('/') || str_ends_with($location, '/'),
            "Expected redirect to root URL, got: {$location}"
        );
    }

    public function test_passes_through_when_not_installed(): void
    {
        // Ensure no installed file
        if (file_exists($this->installedPath)) {
            unlink($this->installedPath);
        }

        $request = Request::create('/install', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
