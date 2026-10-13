<?php

namespace Plugins\Captcha\Services\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Plugins\Captcha\Services\CaptchaProviderInterface;
use Plugins\Captcha\Services\CaptchaResult;
use Plugins\Captcha\Support\CaptchaSecrets;

class TurnstileProvider implements CaptchaProviderInterface
{
    public function slug(): string
    {
        return 'turnstile';
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = $request->input('cf-turnstile-response');
        $secret = CaptchaSecrets::get('captcha.turnstile_secret_key');

        if (empty($token)) {
            return CaptchaResult::fail(__('captcha::captcha.verification_failed'));
        }

        if (empty($secret)) {
            return CaptchaResult::fail(__('captcha::captcha.not_configured'));
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
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

        return CaptchaResult::pass();
    }

    public function excludeFields(): array
    {
        return ['cf-turnstile-response'];
    }
}
