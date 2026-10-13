<?php

namespace Tests\Feature\Security;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    public function test_x_frame_options_header(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_x_content_type_options_header(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_x_xss_protection_header(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('X-XSS-Protection', '0');
    }

    public function test_referrer_policy_header(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_header_present(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_permissions_policy_header(): void
    {
        Page::factory()->root()->create();
        $response = $this->get('/');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_csp_script_src_is_nonce_based_and_strict(): void
    {
        Page::factory()->root()->create();
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        // Scripts run off a per-request nonce — no 'unsafe-inline'/'unsafe-eval'.
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
        $scriptSrc = explode('style-src', $csp)[0];
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);

        // Report-Only is gone once the strict policy is enforced.
        $this->assertNull($this->get('/')->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_csp_nonce_is_unique_per_request(): void
    {
        Page::factory()->root()->create();

        $extract = function () {
            $header = $this->get('/')->headers->get('Content-Security-Policy');
            preg_match("/'nonce-([^']+)'/", $header, $m);

            return $m[1] ?? null;
        };

        $first = $extract();
        $second = $extract();

        $this->assertNotNull($first);
        $this->assertNotSame($first, $second, 'CSP nonce must be regenerated per request.');
    }

    public function test_csp_allows_youtube_and_vimeo_frames(): void
    {
        Page::factory()->root()->create();
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src', $csp);
        $this->assertStringContainsString('youtube-nocookie.com', $csp);
        $this->assertStringContainsString('player.vimeo.com', $csp);
    }
}
