<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Password rules from the security settings — for the hint list below
 * password fields (auth.partials.password-hints).
 *
 * Registration, password reset and profile show the same rules.
 */
final class PasswordPolicy
{
    /**
     * @return array{min_length: int, require_uppercase: bool, require_numbers: bool, require_symbols: bool}
     */
    public static function policy(): array
    {
        return [
            'min_length' => max(8, (int) setting('security.password_min_length', '10')),
            'require_uppercase' => setting('security.password_require_uppercase', 'on') === 'on',
            'require_numbers' => setting('security.password_require_numbers', 'on') === 'on',
            'require_symbols' => setting('security.password_require_symbols', 'on') === 'on',
        ];
    }

    /**
     * The rules as readable sentences; the first is always the minimum length.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        $policy = self::policy();

        $rules = [__('translation.auth.password_rule_min', ['min' => $policy['min_length']])];

        if ($policy['require_uppercase']) {
            $rules[] = __('translation.auth.password_rule_uppercase');
        }
        if ($policy['require_numbers']) {
            $rules[] = __('translation.auth.password_rule_numbers');
        }
        if ($policy['require_symbols']) {
            $rules[] = __('translation.auth.password_rule_symbols');
        }

        return $rules;
    }
}
