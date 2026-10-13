<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * /.well-known/security.txt (RFC 9116) from config/cms.php.
 */
class SecurityTxtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_not_served_without_contact(): void
    {
        config(['cms.security_txt.contact' => '']);

        $this->get('/.well-known/security.txt')->assertNotFound();
    }

    public function test_serves_required_fields(): void
    {
        Carbon::setTestNow('2026-09-28 12:00:00');
        config([
            'cms.security_txt.contact' => 'security@example.com, https://example.com/report',
            'cms.security_txt.expires' => '',
            'cms.security_txt.expires_days' => 180,
            'cms.security_txt.policy' => 'https://example.com/security-policy',
            'cms.security_txt.preferred_languages' => 'en, de',
        ]);

        $response = $this->get('/.well-known/security.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertStringContainsString("Contact: mailto:security@example.com\n", $body);
        $this->assertStringContainsString("Contact: https://example.com/report\n", $body);
        $this->assertStringContainsString("Expires: 2027-03-27T00:00:00Z\n", $body);
        $this->assertStringContainsString("Policy: https://example.com/security-policy\n", $body);
        $this->assertStringContainsString("Preferred-Languages: en, de\n", $body);
        $this->assertStringContainsString("Canonical: http://localhost/.well-known/security.txt\n", $body);
    }

    public function test_fixed_expiry_date_is_used(): void
    {
        config([
            'cms.security_txt.contact' => 'mailto:security@example.com',
            'cms.security_txt.expires' => '2027-01-31',
        ]);

        $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertSee('Expires: 2027-01-31T23:59:59Z', false);
    }

    public function test_invalid_values_are_dropped(): void
    {
        config([
            // Each line is a contact of its own and is checked separately — injected fields (Policy: …) are dropped
            'cms.security_txt.contact' => "not-an-address, http://insecure.example/report, a@example.com\nPolicy: https://evil.example, security@example.com",
            'cms.security_txt.policy' => 'http://example.com/policy',
            'cms.security_txt.preferred_languages' => 'en, <script>',
        ]);

        $body = $this->get('/.well-known/security.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString('not-an-address', $body);
        $this->assertStringNotContainsString('insecure.example', $body);
        $this->assertStringNotContainsString('evil.example', $body);
        $this->assertStringNotContainsString('Policy:', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('Preferred-Languages: en', $body);
        $this->assertStringContainsString("Contact: mailto:security@example.com\n", $body);
        $this->assertStringContainsString("Contact: mailto:a@example.com\n", $body);
        $this->assertSame(2, substr_count($body, 'Contact:'));
    }
}
