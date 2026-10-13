<?php

namespace Plugins\Captcha\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\Captcha\Support\CaptchaSecrets;

#[Middleware('permission:captcha-settings')]
class SettingsController extends Controller
{
    protected const DEFAULTS = [
        'enabled' => '1',
        'provider' => 'math',
        'math_difficulty' => 'easy',
        'recaptcha_v3_site_key' => '',
        'recaptcha_v3_secret_key' => '',
        'recaptcha_v3_threshold' => '0.5',
        'turnstile_site_key' => '',
        'turnstile_secret_key' => '',
        'hcaptcha_site_key' => '',
        'hcaptcha_secret_key' => '',
        'captchafox_site_key' => '',
        'captchafox_secret_key' => '',
        'friendly_captcha_site_key' => '',
        'friendly_captcha_secret_key' => '',
    ];

    public function edit(): View
    {
        $rawSettings = cms()->settings()->getByPrefix('captcha.');
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings["captcha.{$key}"] = $value;
        }

        // The view only learns WHETHER a secret key is stored —
        // neither plain text nor ciphertext goes into the page
        foreach (CaptchaSecrets::KEYS as $secretKey) {
            if (array_key_exists($secretKey, $settings)) {
                $settings[$secretKey] = filled($settings[$secretKey]) ? '1' : '';
            }
        }

        return view('captcha-admin::settings', [
            'settings' => $settings,
            'defaults' => self::DEFAULTS,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'enabled' => 'boolean',
            'provider' => 'required|in:math,recaptcha-v3,turnstile,hcaptcha,captchafox,friendly-captcha',
            'math_difficulty' => 'required|in:easy,medium,hard',
            'recaptcha_v3_site_key' => 'nullable|string|max:255',
            'recaptcha_v3_secret_key' => 'nullable|string|max:255',
            'recaptcha_v3_threshold' => 'required|numeric|min:0|max:1',
            'turnstile_site_key' => 'nullable|string|max:255',
            'turnstile_secret_key' => 'nullable|string|max:255',
            'hcaptcha_site_key' => 'nullable|string|max:255',
            'hcaptcha_secret_key' => 'nullable|string|max:255',
            'captchafox_site_key' => 'nullable|string|max:255',
            'captchafox_secret_key' => 'nullable|string|max:255',
            'friendly_captcha_site_key' => 'nullable|string|max:255',
            'friendly_captcha_secret_key' => 'nullable|string|max:255',
        ]);

        cms()->settings()->set('captcha.enabled', $request->boolean('enabled') ? '1' : '0', 'boolean');
        cms()->settings()->set('captcha.provider', $request->input('provider'), 'string');
        cms()->settings()->set('captcha.math_difficulty', $request->input('math_difficulty'), 'string');
        cms()->settings()->set('captcha.recaptcha_v3_site_key', $request->input('recaptcha_v3_site_key') ?? '', 'string');
        $this->setSecret('recaptcha_v3_secret_key', $request);
        cms()->settings()->set('captcha.recaptcha_v3_threshold', $request->input('recaptcha_v3_threshold'), 'string');
        cms()->settings()->set('captcha.turnstile_site_key', $request->input('turnstile_site_key') ?? '', 'string');
        $this->setSecret('turnstile_secret_key', $request);
        cms()->settings()->set('captcha.hcaptcha_site_key', $request->input('hcaptcha_site_key') ?? '', 'string');
        $this->setSecret('hcaptcha_secret_key', $request);
        cms()->settings()->set('captcha.captchafox_site_key', $request->input('captchafox_site_key') ?? '', 'string');
        $this->setSecret('captchafox_secret_key', $request);
        cms()->settings()->set('captcha.friendly_captcha_site_key', $request->input('friendly_captcha_site_key') ?? '', 'string');
        $this->setSecret('friendly_captcha_secret_key', $request);

        return redirect()
            ->route('backend.captcha.settings')
            ->with('success', __('captcha::captcha.settings_saved'));
    }

    /**
     * Secret keys are never sent back to the form. An empty field therefore
     * means "leave unchanged", not "delete". Values are stored
     * encrypted.
     */
    protected function setSecret(string $key, Request $request): void
    {
        if ($request->filled($key)) {
            CaptchaSecrets::store('captcha.'.$key, (string) $request->input($key));
        }
    }
}
