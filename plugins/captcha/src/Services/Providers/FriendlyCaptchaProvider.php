<?php

namespace Plugins\Captcha\Services\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Plugins\Captcha\Services\CaptchaProviderInterface;
use Plugins\Captcha\Services\CaptchaResult;
use Plugins\Captcha\Support\CaptchaSecrets;

class FriendlyCaptchaProvider implements CaptchaProviderInterface
{
    public function slug(): string
    {
        return 'friendly-captcha';
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = $request->input('frc-captcha-solution');
        $secret = CaptchaSecrets::get('captcha.friendly_captcha_secret_key');
        $siteKey = cms()->settings()->get('captcha.friendly_captcha_site_key', '');

        if (empty($token)) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($secret)) {
            return CaptchaResult::fail(__('captcha::captcha.not_configured'));
        }

        try {
            $response = Http::asForm()->post('https://api.friendlycaptcha.com/api/v1/siteverify', [
                'secret' => $secret,
                'solution' => $token,
                'sitekey' => $siteKey,
            ])->json();
        } catch (\Exception $e) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($response['success'])) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        return CaptchaResult::pass();
    }

    public function excludeFields(): array
    {
        return ['frc-captcha-solution'];
    }
}
