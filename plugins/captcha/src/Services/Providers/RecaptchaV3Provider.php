<?php

namespace Plugins\Captcha\Services\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Plugins\Captcha\Services\CaptchaProviderInterface;
use Plugins\Captcha\Services\CaptchaResult;
use Plugins\Captcha\Support\CaptchaSecrets;

class RecaptchaV3Provider implements CaptchaProviderInterface
{
    public function slug(): string
    {
        return 'recaptcha-v3';
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = $request->input('g-recaptcha-response');
        $secret = CaptchaSecrets::get('captcha.recaptcha_v3_secret_key');
        $threshold = (float) cms()->settings()->get('captcha.recaptcha_v3_threshold', '0.5');

        if (empty($token)) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($secret)) {
            return CaptchaResult::fail(__('captcha::captcha.not_configured'));
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ])->json();
        } catch (\Exception $e) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($response['success'])) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (($response['score'] ?? 0) < $threshold) {
            return CaptchaResult::fail(__('captcha::captcha.score_too_low'));
        }

        return CaptchaResult::pass();
    }

    public function excludeFields(): array
    {
        return ['g-recaptcha-response'];
    }
}
