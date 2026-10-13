<?php

namespace Plugins\Captcha\Contracts;

use Illuminate\Http\Request;
use Plugins\Captcha\Services\CaptchaResult;

interface CaptchaVerifier
{
    public function isRequiredForForm($form): bool;

    public function verify(Request $request, $form = null): CaptchaResult;

    public function excludeFields(?string $providerSlug = null): array;
}
