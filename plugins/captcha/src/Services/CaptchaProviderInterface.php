<?php

namespace Plugins\Captcha\Services;

use Illuminate\Http\Request;

interface CaptchaProviderInterface
{
    public function slug(): string;

    public function verify(Request $request): CaptchaResult;

    public function excludeFields(): array;
}
