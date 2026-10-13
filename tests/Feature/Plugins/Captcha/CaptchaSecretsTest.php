<?php

namespace Tests\Feature\Plugins\Captcha;

use App\Models\Plugin;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Plugins\ActivityLog\Models\ActivityLog;
use Plugins\Captcha\Services\Providers\HCaptchaProvider;
use Plugins\Captcha\Services\Providers\RecaptchaV3Provider;
use Plugins\Captcha\Services\Providers\TurnstileProvider;
use Plugins\Captcha\Support\CaptchaSecrets;
use Tests\TestCase;

/**
 * Captcha provider secret keys: store them encrypted, decrypt them for
 * verification, tolerate values stored in plain text.
 */
class CaptchaSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_28_100000_encrypt_captcha_secret_settings.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
        $this->enablePlugins('captcha');
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    private function enablePlugins(string ...$slugs): void
    {
        $manager = app(PluginManager::class);
        $manager->discover();

        foreach ($slugs as $slug) {
            Plugin::create(['slug' => $slug, 'name' => $slug, 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
            $manager->registerOnly($slug);
            $manager->getLoadedProviders()[$slug]->install();
        }

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    private function saveSettings(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->createAdmin())
            ->put(route('backend.captcha.settings.update'), array_merge([
                'enabled' => '1',
                'provider' => 'turnstile',
                'math_difficulty' => 'easy',
                'recaptcha_v3_threshold' => '0.5',
            ], $overrides));
    }

    private function rawValue(string $key): string
    {
        return (string) Setting::where('key', $key)->value('value');
    }

    private function storePlain(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
        EloquentSettingRepository::clearCache();
    }

    public function test_secret_is_encrypted_on_save(): void
    {
        $this->saveSettings(['turnstile_secret_key' => 'turnstile-plain-secret'])
            ->assertRedirect(route('backend.captcha.settings'));

        $stored = Setting::where('key', 'captcha.turnstile_secret_key')->first();

        $this->assertSame('encrypted', $stored->type);
        $this->assertNotSame('turnstile-plain-secret', $stored->value);
        $this->assertStringNotContainsString('turnstile-plain-secret', $stored->value);
        $this->assertSame('turnstile-plain-secret', decrypt($stored->value));
    }

    public function test_empty_field_keeps_stored_secret(): void
    {
        $this->saveSettings(['hcaptcha_secret_key' => 'hcaptcha-secret']);
        $before = $this->rawValue('captcha.hcaptcha_secret_key');

        $this->saveSettings(['hcaptcha_secret_key' => '', 'hcaptcha_site_key' => 'site']);

        $this->assertSame($before, $this->rawValue('captcha.hcaptcha_secret_key'));
        $this->assertSame('hcaptcha-secret', CaptchaSecrets::get('captcha.hcaptcha_secret_key'));
    }

    public function test_settings_page_renders_neither_secret_nor_ciphertext(): void
    {
        $this->saveSettings(['turnstile_secret_key' => 'turnstile-plain-secret']);
        $cipher = $this->rawValue('captcha.turnstile_secret_key');

        $response = $this->actingAs($this->createAdmin())
            ->get(route('backend.captcha.settings'))
            ->assertOk()
            ->assertSee(__('captcha::captcha.secret_stored'));

        $response->assertDontSee('turnstile-plain-secret');
        $response->assertDontSee($cipher);
    }

    public function test_verifier_receives_decrypted_secret(): void
    {
        $this->saveSettings(['turnstile_secret_key' => 'turnstile-plain-secret']);
        EloquentSettingRepository::clearCache();

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $result = (new TurnstileProvider)->verify(Request::create('/', 'POST', ['cf-turnstile-response' => 'token']));

        $this->assertTrue($result->passed());
        Http::assertSent(fn (ClientRequest $request) => $request['secret'] === 'turnstile-plain-secret'
            && $request['response'] === 'token');
    }

    public function test_plain_text_secret_works(): void
    {
        $this->storePlain('captcha.hcaptcha_secret_key', 'legacy-plain-secret');

        Http::fake(['api.hcaptcha.com/*' => Http::response(['success' => true])]);

        $result = (new HCaptchaProvider)->verify(Request::create('/', 'POST', ['h-captcha-response' => 'token']));

        $this->assertTrue($result->passed());
        Http::assertSent(fn (ClientRequest $request) => $request['secret'] === 'legacy-plain-secret');
    }

    public function test_undecryptable_ciphertext_counts_as_not_configured(): void
    {
        // Ciphertext with a different key (e.g. after an APP_KEY change)
        $foreign = (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encrypt('old-secret');
        Setting::updateOrCreate(['key' => 'captcha.recaptcha_v3_secret_key'], ['value' => $foreign, 'type' => 'encrypted']);
        EloquentSettingRepository::clearCache();

        Http::fake();

        $result = (new RecaptchaV3Provider)->verify(Request::create('/', 'POST', ['g-recaptcha-response' => 'token']));

        $this->assertFalse($result->passed());
        $this->assertSame(__('captcha::captcha.not_configured'), $result->message());
        Http::assertNothingSent();
    }

    public function test_migration_encrypts_plain_secrets_and_is_idempotent(): void
    {
        $this->saveSettings(['hcaptcha_secret_key' => 'already-encrypted']);
        $alreadyEncrypted = $this->rawValue('captcha.hcaptcha_secret_key');
        $this->storePlain('captcha.turnstile_secret_key', 'plain-turnstile');
        $this->storePlain('captcha.captchafox_secret_key', '');
        $this->storePlain('captcha.turnstile_site_key', 'public-site-key');
        $this->storePlain('mailproviders.smtp.password', 'not-mine');

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $turnstile = Setting::where('key', 'captcha.turnstile_secret_key')->first();
        $this->assertSame('encrypted', $turnstile->type);
        $this->assertSame('plain-turnstile', decrypt($turnstile->value));

        // Already encrypted: unchanged
        $this->assertSame($alreadyEncrypted, $this->rawValue('captcha.hcaptcha_secret_key'));

        // Empty stays empty, other settings stay untouched
        $this->assertSame('', $this->rawValue('captcha.captchafox_secret_key'));
        $this->assertSame('public-site-key', $this->rawValue('captcha.turnstile_site_key'));
        $this->assertSame('not-mine', $this->rawValue('mailproviders.smtp.password'));
        $this->assertSame('string', Setting::where('key', 'captcha.turnstile_site_key')->value('type'));

        // A second run changes nothing more
        $afterFirst = $this->rawValue('captcha.turnstile_secret_key');
        $migration->up();
        $this->assertSame($afterFirst, $this->rawValue('captcha.turnstile_secret_key'));
        $this->assertSame('plain-turnstile', CaptchaSecrets::get('captcha.turnstile_secret_key'));
    }

    public function test_activity_log_contains_neither_secret_nor_ciphertext(): void
    {
        $this->enablePlugins('activity-log');

        $this->saveSettings(['turnstile_secret_key' => 'first-secret']);
        $firstCipher = $this->rawValue('captcha.turnstile_secret_key');
        $this->saveSettings(['turnstile_secret_key' => 'rotated-secret']);
        $secondCipher = $this->rawValue('captcha.turnstile_secret_key');

        $logged = ActivityLog::where('subject_type', Setting::class)->get()
            ->map(fn ($log) => json_encode($log->properties, JSON_UNESCAPED_SLASHES))->implode(' ');

        $this->assertNotEmpty($logged);
        foreach (['first-secret', 'rotated-secret', $firstCipher, $secondCipher] as $needle) {
            $this->assertStringNotContainsString($needle, $logged);
        }
    }
}
