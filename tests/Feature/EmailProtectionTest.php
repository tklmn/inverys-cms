<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Support\EmailProtector;
use App\Support\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Email protection: addresses are not in plain text in the HTML, visitors
 * get them back in the browser; signed-in users see the original.
 */
class EmailProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const HTML = '<!DOCTYPE html><html><head><title>Contact info@example.com</title>'
        .'<meta name="author" content="info@example.com"></head><body>'
        .'<p>Write to info@example.com or <a href="mailto:anna.m%C3%BCller@example.org?subject=Hello" title="Mail to anna@example.org">Anna</a>.</p>'
        .'<input type="email" placeholder="name@example.com" value="">'
        .'<textarea>stays@example.com</textarea>'
        .'<script>var a = "script@example.com";</script>'
        .'<!-- comment@example.com -->'
        .'</body></html>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    // ── Conversion ────────────────────────────────────────────────────

    public function test_addresses_in_the_body_are_hidden(): void
    {
        $html = EmailProtector::protect(self::HTML, '/js/email-protection.js');
        $body = substr($html, strpos($html, '<body'));

        $this->assertStringContainsString('Write to info<span class="cms-at"></span>example.com', $body);
        $this->assertStringNotContainsString('mailto:', $body);
        $this->assertStringContainsString('href="#" data-cms-email="'.EmailProtector::encode('anna.m%C3%BCller@example.org?subject=Hello').'"', $body);
        $this->assertStringContainsString('title="Mail to anna(at)example.org"', $body);
        $this->assertStringContainsString('data-cms-at="title"', $body);
        $this->assertStringContainsString('<script src="/js/email-protection.js" defer></script></body>', $html);
        $this->assertStringContainsString('<style>.cms-at::after{content:"@"}</style></head>', $html);
    }

    public function test_head_form_fields_raw_blocks_and_comments_stay_untouched(): void
    {
        $html = EmailProtector::protect(self::HTML, '/js/email-protection.js');

        $this->assertStringContainsString('<title>Contact info@example.com</title>', $html);
        $this->assertStringContainsString('content="info@example.com"', $html);
        $this->assertStringContainsString('placeholder="name@example.com"', $html);
        $this->assertStringContainsString('<textarea>stays@example.com</textarea>', $html);
        $this->assertStringContainsString('"script@example.com"', $html);
        $this->assertStringContainsString('<!-- comment@example.com -->', $html);
    }

    public function test_html_without_addresses_stays_as_it_is(): void
    {
        $html = '<!DOCTYPE html><html><head></head><body><p>No contact, just @tklmn on GitHub.</p></body></html>';

        $this->assertSame($html, EmailProtector::protect($html, '/x.js'));
    }

    public function test_encoding_round_trips_unicode(): void
    {
        $decoded = implode('', array_reverse(mb_str_split(base64_decode(EmailProtector::encode('zoë@café.fr')))));

        $this->assertSame('zoë@café.fr', $decoded);
    }

    // ── On the website ──────────────────────────────────────────────

    private function contactPage(): void
    {
        Page::create([
            'title' => 'Contact',
            'slug' => '/',
            'is_root' => true,
            'is_active' => true,
            'content_type' => 'html',
            'content' => '<p>Write to info@example.com or <a href="mailto:anna@example.org">Anna</a>.</p>',
        ]);
    }

    public function test_visitors_get_the_protected_page(): void
    {
        $this->contactPage();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('info@example.com', $html);
        $this->assertStringNotContainsString('mailto:anna@example.org', $html);
        $this->assertStringContainsString('data-cms-email="'.EmailProtector::encode('anna@example.org').'"', $html);
        $this->assertMatchesRegularExpression('#js/email-protection\.js\?v=[0-9.]+#', $html);
        // Copyright and minification still run afterwards
        $this->assertStringContainsString('<!-- Copyright ©', $html);
    }

    public function test_signed_in_users_see_the_original(): void
    {
        $this->contactPage();

        $html = $this->actingAs($this->createAdmin())->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="mailto:anna@example.org"', $html);
        $this->assertStringNotContainsString('email-protection.js', $html);
    }

    public function test_it_can_be_switched_off(): void
    {
        $this->contactPage();
        Setting::updateOrCreate(['key' => 'html.protect_emails'], ['value' => '0']);
        app(EloquentSettingRepository::class)->clearCache();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('info@example.com', $html);
        $this->assertStringNotContainsString('email-protection.js', $html);
    }

    public function test_setting_is_offered_under_general(): void
    {
        $this->assertTrue(GeneralSettings::isEditable('html.protect_emails'));
        $this->assertSame(['1', '0'], array_map('strval', array_keys(GeneralSettings::options('html.protect_emails'))));
    }

    public function test_script_is_delivered(): void
    {
        $this->assertFileExists(public_path('js/email-protection.js'));
    }
}
