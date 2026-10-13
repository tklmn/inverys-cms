<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\EmailChange;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The address must not be held for another account's "This wasn't me" link
 * (App\Support\EmailChange): until that window closes, it counts as taken.
 */
final class EmailNotReserved implements ValidationRule
{
    public function __construct(private readonly int|string|null $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $value !== '' && EmailChange::isReserved($value, $this->ignoreUserId)) {
            $fail('validation.unique')->translate();
        }
    }
}
