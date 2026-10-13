<?php

namespace Plugins\Captcha\Services\Providers;

use Illuminate\Http\Request;
use Plugins\Captcha\Services\CaptchaProviderInterface;
use Plugins\Captcha\Services\CaptchaResult;

class MathProvider implements CaptchaProviderInterface
{
    public function slug(): string
    {
        return 'math';
    }

    public function verify(Request $request): CaptchaResult
    {
        $token = $request->input('_captcha_token');
        $answer = $request->input('_captcha_answer');

        if (empty($token) || $answer === null || $answer === '') {
            return CaptchaResult::fail(__('captcha::captcha.answer_required'));
        }

        $stored = session()->pull("captcha.{$token}");

        if ($stored === null) {
            return CaptchaResult::fail(__('captcha::captcha.expired'));
        }

        if ((int) $answer !== (int) $stored) {
            return CaptchaResult::fail(__('captcha::captcha.wrong_answer'));
        }

        return CaptchaResult::pass();
    }

    public function excludeFields(): array
    {
        return ['_captcha_token', '_captcha_answer'];
    }

    public static function generateChallenge(): array
    {
        $difficulty = cms()->settings()->get('captcha.math_difficulty', 'easy');

        [$a, $b, $op] = match ($difficulty) {
            'hard' => self::hardChallenge(),
            'medium' => self::mediumChallenge(),
            default => self::easyChallenge(),
        };

        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
        };

        $token = bin2hex(random_bytes(16));

        // Limit open challenges — otherwise every request would add another
        // entry and the session would grow without bound
        $open = session()->get('captcha', []);
        $open = is_array($open) ? array_slice($open, -9, null, true) : [];
        $open[$token] = $answer;
        session()->put('captcha', $open);

        return [
            'token' => $token,
            'challenge' => "{$a} {$op} {$b} = ?",
        ];
    }

    protected static function easyChallenge(): array
    {
        $a = random_int(1, 10);
        $b = random_int(1, 10);

        return [$a, $b, '+'];
    }

    protected static function mediumChallenge(): array
    {
        $ops = ['+', '-', '*'];
        $op = $ops[array_rand($ops)];

        if ($op === '*') {
            $a = random_int(2, 9);
            $b = random_int(2, 5);
        } elseif ($op === '-') {
            $a = random_int(5, 20);
            $b = random_int(1, $a);
        } else {
            $a = random_int(1, 20);
            $b = random_int(1, 20);
        }

        return [$a, $b, $op];
    }

    protected static function hardChallenge(): array
    {
        $ops = ['+', '-', '*'];
        $op = $ops[array_rand($ops)];

        if ($op === '*') {
            $a = random_int(3, 12);
            $b = random_int(3, 9);
        } elseif ($op === '-') {
            $a = random_int(10, 50);
            $b = random_int(1, $a);
        } else {
            $a = random_int(10, 50);
            $b = random_int(10, 50);
        }

        return [$a, $b, $op];
    }
}
