<?php

namespace Plugins\Captcha\Services;

class CaptchaResult
{
    public function __construct(
        protected bool $passed,
        protected string $message = '',
    ) {}

    public function passed(): bool
    {
        return $this->passed;
    }

    public function failed(): bool
    {
        return ! $this->passed;
    }

    public function message(): string
    {
        return $this->message;
    }

    public static function pass(): static
    {
        return new static(true);
    }

    public static function fail(string $message): static
    {
        return new static(false, $message);
    }
}
