<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustConfiguredHosts;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Host header injection: without a fixed list of allowed hosts, Laravel
 * builds every absolute URL from the request's Host header — an attacker could
 * thus point password reset links at their own domain (password reset
 * poisoning) or fill the sitemap cache with any number of hosts.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    protected function tearDown(): void
    {
        // Request::setTrustedHosts() is static — don't carry it into other tests
        Request::setTrustedHosts([]);
        TrustConfiguredHosts::flushState();

        parent::tearDown();
    }

    public function test_foreign_host_header_is_rejected(): void
    {
        $this->get('http://evil.example/login')->assertStatus(400);
    }

    public function test_application_host_and_subdomains_are_accepted(): void
    {
        $this->get('http://localhost/login')->assertOk();
        $this->get('http://www.localhost/login')->assertOk();
    }

    public function test_password_reset_link_cannot_be_poisoned_via_host_header(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'victim@example.com']);

        $this->post('http://evil.example/forgot-password', ['email' => 'victim@example.com'])
            ->assertStatus(400);

        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_uses_application_host(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'victim@example.com']);

        $this->post('http://localhost/forgot-password', ['email' => 'victim@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost/');
        });
    }

    public function test_additional_hosts_can_be_configured(): void
    {
        config(['cms.trusted_hosts' => ['cms.example.org']]);

        $this->get('http://cms.example.org/login')->assertOk();
        $this->get('http://evil.example/login')->assertStatus(400);
    }

    public function test_www_app_url_also_trusts_the_bare_domain(): void
    {
        config(['app.url' => 'http://www.example.org']);

        $this->get('http://example.org/login')->assertOk();
        $this->get('http://www.example.org/login')->assertOk();
        $this->get('http://example.org.evil.example/login')->assertStatus(400);
    }

    public function test_wildcard_disables_the_host_check(): void
    {
        config(['cms.trusted_hosts' => ['*']]);

        $this->get('http://evil.example/login')->assertOk();
    }

    public function test_robots_txt_does_not_reflect_a_foreign_host(): void
    {
        $this->get('http://evil.example/robots.txt')->assertStatus(400);

        $this->get('http://localhost/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);
    }
}
