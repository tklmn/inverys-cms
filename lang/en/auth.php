<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'inactive' => 'Your account is not yet activated. Please contact an administrator.',
    'registration_pending' => 'Your account has been created and is pending activation by an administrator.',

    'two_factor' => [
        'challenge_title' => 'Two-Factor Authentication',
        'totp_prompt' => 'Enter the 6-digit code from your authenticator app.',
        'email_prompt' => 'A verification code has been sent to your email address.',
        'code_label' => 'Verification Code',
        'code_placeholder' => '000000',
        'recovery_label' => 'Recovery Code',
        'recovery_placeholder' => 'XXXX-XXXX-XXXX',
        'verify_button' => 'Verify',
        'resend_code' => 'Resend code',
        'use_recovery' => 'Use a recovery code',
        'use_auth_code' => 'Use an authentication code',
        'invalid_code' => 'The provided code is invalid or has expired.',
        'code_resent' => 'A new verification code has been sent.',
        'resend_throttle' => 'Please wait :seconds seconds before requesting a new code.',
        'too_many_attempts' => 'Too many invalid codes. Please try again in :seconds seconds.',
        'recovery_used' => 'Recovery code accepted. :remaining codes remaining.',
        'all_recovery_used' => 'All recovery codes have been used. Two-factor authentication has been reset to email verification.',

        'profile_title' => 'Two-Factor Authentication',
        'globally_disabled' => 'Two-factor authentication is currently disabled by an administrator.',
        'status_disabled' => 'Disabled',
        'user_disabled_desc' => 'Two-factor authentication is disabled for your account.',
        'enable_2fa' => 'Enable 2FA',
        'disable_2fa' => 'Disable 2FA',
        'disable_2fa_confirm' => 'This will disable two-factor authentication for your account. Continue?',
        'user_enabled' => 'Two-factor authentication has been enabled for your account.',
        'user_disabled' => 'Two-factor authentication has been disabled for your account.',
        'method_totp' => 'Authenticator App',
        'method_email' => 'Email Code',
        'totp_active_desc' => 'Your account is secured with an authenticator app.',
        'email_active_desc' => 'A verification code will be sent to your email on each login.',
        'enable_totp' => 'Enable Authenticator App',
        'disable_totp' => 'Disable Authenticator',
        'upgrade_hint' => 'Upgrade to an authenticator app for faster, more secure login.',
        'setup_title' => 'Set Up Authenticator App',
        'setup_instructions' => 'Scan the QR code with your authenticator app (e.g. Google Authenticator, Authy), then enter the 6-digit code to confirm.',
        'setup_not_started' => 'TOTP setup has not been started. Please enable it first.',
        'totp_switch_unavailable' => 'An authenticator app can only be set up while two-factor authentication is active and no app is connected yet. To change the app, disable it first.',
        'manual_entry' => 'Or enter this key manually:',
        'enter_code' => 'Authenticator Code',
        'confirm_button' => 'Confirm',
        'totp_enabled' => 'Authenticator app has been enabled successfully.',
        'totp_disabled' => 'Authenticator app has been disabled. You will receive email codes on login.',
        'recovery_codes_title' => 'Recovery Codes',
        'recovery_codes_warning' => 'Save these codes in a secure place. Each code can only be used once. They will not be shown again.',
        'regenerate_codes' => 'Regenerate Codes',
        'regenerate_confirm' => 'This will invalidate your existing recovery codes. Continue?',
        'codes_regenerated' => 'New recovery codes have been generated.',
        'remaining_codes' => ':count recovery codes remaining.',
        'disable_confirm' => 'This will disable your authenticator app and revert to email codes. Continue?',
        'disable_hint' => 'Reverts to email verification.',
        'send_code' => 'Send Code',
        'verify_code' => 'Verification Code',
        'verify_code_email' => 'Email Code',

        'email_subject' => 'Your verification code',
        'email_greeting' => 'Verification Code',
        'email_line1' => 'Use the following code to complete your login:',
        'email_line2' => 'This code expires in :minutes minutes.',
        'email_warning' => 'If you did not attempt to log in, please change your password immediately.',
        'email_regards' => 'Regards',
    ],

];
