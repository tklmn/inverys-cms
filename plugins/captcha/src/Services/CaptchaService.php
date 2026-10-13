<?php

namespace Plugins\Captcha\Services;

use Illuminate\Http\Request;
use Plugins\Captcha\Contracts\CaptchaVerifier;
use Plugins\Captcha\Services\Providers\CaptchaFoxProvider;
use Plugins\Captcha\Services\Providers\FriendlyCaptchaProvider;
use Plugins\Captcha\Services\Providers\HCaptchaProvider;
use Plugins\Captcha\Services\Providers\MathProvider;
use Plugins\Captcha\Services\Providers\RecaptchaV3Provider;
use Plugins\Captcha\Services\Providers\TurnstileProvider;

class CaptchaService implements CaptchaVerifier
{
    protected ?CaptchaProviderInterface $provider = null;

    public function __construct()
    {
        $this->provider = $this->resolveProvider();
    }

    public function isRequiredForForm($form): bool
    {
        if (! cms()->settings()->get('captcha.enabled', '1')) {
            return false;
        }

        $fields = is_array($form->fields) ? $form->fields : [];

        return $this->hasCaptchaField($fields);
    }

    public function verify(Request $request, $form = null): CaptchaResult
    {
        // Determine which provider to use: per-field override or global default
        $provider = $this->resolveProviderForForm($form);

        if (! $provider) {
            return CaptchaResult::fail(__('captcha::captcha.no_provider'));
        }

        return $provider->verify($request);
    }

    public function excludeFields(?string $providerSlug = null): array
    {
        $provider = $providerSlug ? $this->resolveProvider($providerSlug) : $this->provider;

        if (! $provider) {
            return [];
        }

        return $provider->excludeFields();
    }

    public function getActiveProvider(): ?CaptchaProviderInterface
    {
        return $this->provider;
    }

    /**
     * Get the list of available providers (for form builder dropdown).
     */
    public static function availableProviders(): array
    {
        return [
            'math' => 'captcha::captcha.provider_math',
            'recaptcha-v3' => 'captcha::captcha.provider_recaptcha_v3',
            'turnstile' => 'captcha::captcha.provider_turnstile',
            'hcaptcha' => 'captcha::captcha.provider_hcaptcha',
            'captchafox' => 'captcha::captcha.provider_captchafox',
            'friendly-captcha' => 'captcha::captcha.provider_friendly_captcha',
        ];
    }

    protected function resolveProviderForForm($form): ?CaptchaProviderInterface
    {
        if (! $form) {
            return $this->provider;
        }

        // Find the captcha field in the form and check for a per-field provider
        $fields = is_array($form->fields) ? $form->fields : [];
        $captchaField = $this->findCaptchaField($fields);

        if ($captchaField && ! empty($captchaField['provider'])) {
            return $this->resolveProvider($captchaField['provider']);
        }

        return $this->provider;
    }

    protected function resolveProvider(?string $slug = null): ?CaptchaProviderInterface
    {
        $slug = $slug ?? cms()->settings()->get('captcha.provider', 'math');

        return match ($slug) {
            'math' => new MathProvider,
            'recaptcha-v3' => new RecaptchaV3Provider,
            'turnstile' => new TurnstileProvider,
            'hcaptcha' => new HCaptchaProvider,
            'captchafox' => new CaptchaFoxProvider,
            'friendly-captcha' => new FriendlyCaptchaProvider,
            default => new MathProvider,
        };
    }

    protected function hasCaptchaField(array $fields): bool
    {
        return $this->findCaptchaField($fields) !== null;
    }

    protected function findCaptchaField(array $fields): ?array
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'captcha') {
                return $field;
            }
            if (($field['type'] ?? '') === 'row' && ! empty($field['children'])) {
                foreach ($field['children'] as $column) {
                    if (! empty($column['children'])) {
                        $found = $this->findCaptchaField($column['children']);
                        if ($found) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }
}
