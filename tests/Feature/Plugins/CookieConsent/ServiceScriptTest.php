<?php

namespace Tests\Feature\Plugins\CookieConsent;

use App\Models\Page;
use App\Models\Plugin;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use App\Support\CspSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\CookieConsent\ServiceScript;
use Tests\TestCase;

/**
 * Service scripts run despite a strict CSP: a nonce on the <script>, the
 * service's addresses as allowed sources — both only with consent.
 */
class ServiceScriptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        Plugin::create(['slug' => 'cookie-consent', 'name' => 'Cookie Consent', 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
        app(PluginManager::class)->boot();
        app('router')->getRoutes()->refreshNameLookups();

        Page::factory()->create(['title' => 'Home', 'slug' => '/', 'is_root' => true, 'is_active' => true]);
        Setting::updateOrCreate(['key' => 'cc.services'], ['value' => json_encode([[
            'id' => 'ga', 'name' => 'GA', 'category' => 'statistics', 'position' => 'head',
            'script' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script><script nonce="evil">gtag()</script>',
            'hosts' => ['https://www.googletagmanager.com', 'https://*.google-analytics.com'],
        ]]), 'type' => 'plugin_setting']);
        EloquentSettingRepository::clearCache();
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    public function test_with_consent_scripts_carry_the_nonce_and_hosts_are_allowed(): void
    {
        $response = $this->withUnencryptedCookie('cc_consent', json_encode(['ts' => time(), 'statistics' => true]))->get('/');

        $response->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $nonce = $m[1] ?? 'missing';

        $html = $response->getContent();
        $this->assertStringContainsString('<script nonce="'.$nonce.'" async src="https://www.googletagmanager.com/gtag/js?id=G-1">', $html);
        $this->assertStringContainsString('<script nonce="'.$nonce.'">gtag()</script>', $html);
        $this->assertStringNotContainsString('nonce="evil"', $html);
        $this->assertMatchesRegularExpression('#script-src [^;]*https://www\.googletagmanager\.com#', $csp);
        $this->assertMatchesRegularExpression('#connect-src [^;]*https://\*\.google-analytics\.com#', $csp);
    }

    public function test_without_consent_nothing_is_output_or_allowed(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('gtag', $response->getContent());
        $this->assertStringNotContainsString('googletagmanager', $response->headers->get('Content-Security-Policy'));
    }

    public function test_only_https_hosts_without_path_are_accepted(): void
    {
        $invalid = [];
        $hosts = ServiceScript::parseHosts("https://Plausible.io/\nhttp://evil.example\nhttps://a.example/path\n'unsafe-inline'\nhttps://*.matomo.cloud", $invalid);

        $this->assertSame(['https://plausible.io', 'https://*.matomo.cloud'], $hosts);
        $this->assertCount(3, $invalid);
        $this->assertFalse(CspSources::isValidSource("https://x.example 'unsafe-eval'"));
    }

    public function test_service_form_saves_hosts_and_rejects_bad_ones(): void
    {
        $admin = $this->createAdmin();
        $payload = ['name' => 'Plausible', 'category' => 'statistics', 'script' => '<script src="https://plausible.io/js/script.js"></script>', 'position' => 'head'];

        $this->actingAs($admin)->post(route('backend.cookie-consent.services.store'), $payload + ['hosts' => 'http://plausible.io'])
            ->assertSessionHasErrors('hosts');

        $this->actingAs($admin)->post(route('backend.cookie-consent.services.store'), $payload + ['hosts' => 'https://plausible.io'])
            ->assertRedirect(route('backend.cookie-consent.services'));

        EloquentSettingRepository::clearCache();
        $saved = collect(json_decode(Setting::where('key', 'cc.services')->value('value'), true))->firstWhere('name', 'Plausible');
        $this->assertSame(['https://plausible.io'], $saved['hosts']);
    }
}
