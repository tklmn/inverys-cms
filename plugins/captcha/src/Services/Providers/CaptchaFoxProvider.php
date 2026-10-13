<?php

namespace Plugins\Captcha\Services\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Plugins\Captcha\Services\CaptchaProviderInterface;
use Plugins\Captcha\Services\CaptchaResult;
use Plugins\Captcha\Support\CaptchaSecrets;

class CaptchaFoxProvider implements CaptchaProviderInterface
{
    public function slug(): string
    {
        return 'captchafox';
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = $request->input('cf-cptch-token');
        $secret = CaptchaSecrets::get('captcha.captchafox_secret_key');

        if (empty($token)) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($secret)) {
            return CaptchaResult::fail(__('captcha::captcha.not_configured'));
        }

        try {
            $response = Http::asForm()->post('https://api.captchafox.com/siteverify', [
                'secret' => $secret,
                'response' => $token,
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
        return ['cf-cptch-token'];
    }
}
