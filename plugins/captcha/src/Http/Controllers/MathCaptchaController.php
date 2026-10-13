<?php

namespace Plugins\Captcha\Http\Controllers;

use Illuminate\Routing\Controller;
use Plugins\Captcha\Services\Providers\MathProvider;

class MathCaptchaController extends Controller
{
    public function challenge()
    {
        $data = MathProvider::generateChallenge();

        return response()->json($data);
    }
}
