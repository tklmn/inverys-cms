<?php

namespace Tests\Feature\Plugins\Captcha;

use App\Models\Plugin;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Plugins\Captcha\Services\CaptchaService;
use Tests\TestCase;

/**
 * Captcha settings page: the default provider is chosen in the side column
 * (outside the form, bound via form=) and every key card shows its key status.
 */
class CaptchaSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();

        $manager = app(PluginManager::class);
        $manager->discover();
        Plugin::create(['slug' => 'captcha', 'name' => 'captcha', 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
        $manager->registerOnly('captcha');
        $manager->getLoadedProviders()['captcha']->install();

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    public function test_provider_choice_is_bound_to_the_form_and_marks_the_saved_default(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->put(route('backend.captcha.settings.update'), [
            'enabled' => '1',
            'provider' => 'turnstile',
            'math_difficulty' => 'hard',
            'recaptcha_v3_threshold' => '0.7',
        ])->assertRedirect(route('backend.captcha.settings'));

        $response = $this->actingAs($admin)->get(route('backend.captcha.settings'))->assertOk();

        foreach (array_keys(CaptchaService::availableProviders()) as $slug) {
            $response->assertSee('name="provider" value="'.$slug.'"', false);
        }

        // The radios live outside the form and must still be submitted with it
        $this->assertSame(count(CaptchaService::availableProviders()), preg_match_all('/<input type="radio" name="provider"[^>]*form="captcha-settings-form"/', $response->getContent()));
        $this->assertMatchesRegularExpression('/name="provider" value="turnstile"[^>]*checked/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="provider" value="math"[^>]*checked/', $response->getContent());
        $response->assertSee(__('captcha::captcha.default_badge'));
        $response->assertSee(__('captcha::captcha.provider_turnstile_desc'));
        $response->assertSee('data-requires="provider=math"', false);
    }

    public function test_key_cards_show_whether_keys_are_stored(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->put(route('backend.captcha.settings.update'), [
            'enabled' => '1',
            'provider' => 'math',
            'math_difficulty' => 'easy',
            'recaptcha_v3_threshold' => '0.5',
            'turnstile_site_key' => 'site',
            'turnstile_secret_key' => 'secret',
            'hcaptcha_site_key' => 'only-site',
        ]);

        $this->actingAs($admin)->get(route('backend.captcha.settings'))
            ->assertOk()
            ->assertSee(__('captcha::captcha.keys_stored'))
            ->assertSee(__('captcha::captcha.keys_incomplete'))
            ->assertSee(__('captcha::captcha.keys_missing'))
            ->assertDontSee('value="secret"', false);
    }
}
