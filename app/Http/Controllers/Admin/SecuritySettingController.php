<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Support\SecurityTxt;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Controller for managing security settings in the admin backend.
 */
#[Middleware('auth')]
#[Middleware('permission:manage-security-settings', only: ['security', 'saveSecurity'])]
class SecuritySettingController extends Controller
{
    /**
     * Show the security settings form.
     */
    public function security(): View
    {
        $securitySettings = [
            'password_min_length' => setting('security.password_min_length', '10'),
            'password_require_uppercase' => setting('security.password_require_uppercase', 'on'),
            'password_require_numbers' => setting('security.password_require_numbers', 'on'),
            'password_require_symbols' => setting('security.password_require_symbols', 'on'),
            'registration_enabled' => setting('security.registration_enabled', 'on'),
            'login_max_attempts' => setting('security.login_max_attempts', '5'),
            'login_lockout_duration' => setting('security.login_lockout_duration', '1'),
            'two_factor_enabled' => setting('security.two_factor_enabled', 'on'),
            'two_factor_code_lifetime' => setting('security.two_factor_code_lifetime', '10'),
            'api_rate_limit' => setting('security.api_rate_limit', '60'),
            'session_lifetime' => setting('security.session_lifetime', '120'),
            'notification_retention_days' => setting('security.notification_retention_days', '30'),
            // security.txt: only the values maintained here (the .env stays the fallback)
            'txt_contact' => setting('security.txt_contact', ''),
            'txt_policy' => setting('security.txt_policy', ''),
            'txt_encryption' => setting('security.txt_encryption', ''),
            'txt_languages' => setting('security.txt_languages', ''),
            'txt_expires_days' => setting('security.txt_expires_days', '180'),
        ];

        $securityTxt = SecurityTxt::build();

        return view('backend.settings.security', compact('securitySettings', 'securityTxt'));
    }

    /**
     * Save security settings.
     */
    public function saveSecurity(Request $request): RedirectResponse
    {
        $request->validate([
            'password_min_length' => 'required|integer|min:8|max:128',
            'password_require_uppercase' => 'required|in:on,off',
            'password_require_numbers' => 'required|in:on,off',
            'password_require_symbols' => 'required|in:on,off',
            'registration_enabled' => 'sometimes|in:on,off',
            'login_max_attempts' => 'required|integer|min:3|max:20',
            'login_lockout_duration' => 'required|integer|min:1|max:60',
            'two_factor_enabled' => 'required|in:on,off',
            'two_factor_code_lifetime' => 'required|integer|min:5|max:30',
            'api_rate_limit' => 'required|integer|min:10|max:1000',
            'session_lifetime' => 'required|integer|min:5|max:1440',
            'notification_retention_days' => 'required|integer|min:7|max:365',
            'txt_contact' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, Closure $fail) {
                foreach (SecurityTxt::splitContacts((string) $value) as $line) {
                    if (SecurityTxt::contactUri($line) === null) {
                        $fail(__('translation.settings.security_txt_contact_invalid', ['value' => $line]));

                        return;
                    }
                }
            }],
            'txt_policy' => ['nullable', 'string', 'max:255', $this->httpsRule()],
            'txt_encryption' => ['nullable', 'string', 'max:255', $this->httpsRule()],
            'txt_languages' => ['nullable', 'string', 'max:100', 'regex:/^\s*[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*(\s*,\s*[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*)*\s*$/'],
            'txt_expires_days' => ['nullable', 'integer', 'in:'.implode(',', SecurityTxt::EXPIRES_OPTIONS)],
        ]);

        $fields = [
            'security.password_min_length' => $request->input('password_min_length'),
            'security.password_require_uppercase' => $request->input('password_require_uppercase'),
            'security.password_require_numbers' => $request->input('password_require_numbers'),
            'security.password_require_symbols' => $request->input('password_require_symbols'),
            'security.registration_enabled' => $request->input('registration_enabled', (string) setting('security.registration_enabled', 'on')),
            'security.login_max_attempts' => $request->input('login_max_attempts'),
            'security.login_lockout_duration' => $request->input('login_lockout_duration'),
            'security.two_factor_enabled' => $request->input('two_factor_enabled'),
            'security.two_factor_code_lifetime' => $request->input('two_factor_code_lifetime'),
            'security.api_rate_limit' => $request->input('api_rate_limit'),
            'security.session_lifetime' => $request->input('session_lifetime'),
            'security.notification_retention_days' => $request->input('notification_retention_days'),
            'security.txt_contact' => implode("\n", SecurityTxt::splitContacts((string) $request->input('txt_contact'))),
            'security.txt_policy' => trim((string) $request->input('txt_policy')),
            'security.txt_encryption' => trim((string) $request->input('txt_encryption')),
            'security.txt_languages' => SecurityTxt::languages((string) $request->input('txt_languages')),
            // When locked (fixed date in the .env) nothing is sent — then keep the previous choice
            'security.txt_expires_days' => $request->input('txt_expires_days') ?: (string) setting('security.txt_expires_days', '180'),
        ];

        $upsertData = array_map(fn ($key, $value) => [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
            'description' => 'Security setting',
        ], array_keys($fields), array_values($fields));

        Setting::upsert($upsertData, ['key'], ['value', 'type', 'description']);

        // upsert fires no model events — clear the settings cache ourselves
        EloquentSettingRepository::clearCache();

        return redirect()->route('backend.settings.security')->with('success', __('translation.saved'));
    }

    /** security.txt requires https addresses (RFC 9116) */
    private function httpsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (filled($value) && SecurityTxt::httpsUri((string) $value) === null) {
                $fail(__('translation.settings.security_txt_https'));
            }
        };
    }
}
