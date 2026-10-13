<?php

return [
    // Navigation
    'captcha' => 'Captcha',
    'settings' => 'Captcha Settings',
    'page_lead' => 'Protect forms against spam bots — choose the default method and store the keys of the external services you use.',

    // Settings
    'enabled' => 'Enable Captcha',
    'enabled_hint' => 'Globally enable or disable captcha verification on forms.',
    'provider' => 'Captcha Provider',
    'provider_hint' => 'Applies to every captcha field that does not choose its own provider.',
    'default_provider' => 'Default Provider',
    'default_badge' => 'Default',

    // Providers
    'provider_math' => 'Math Captcha (Built-in)',
    'provider_recaptcha_v3' => 'Google reCAPTCHA v3',
    'provider_turnstile' => 'Cloudflare Turnstile',
    'provider_hcaptcha' => 'hCaptcha',
    'provider_captchafox' => 'CaptchaFox',
    'provider_friendly_captcha' => 'Friendly Captcha',
    'provider_math_desc' => 'Built in, no external service: visitors solve a small arithmetic problem. No keys needed.',
    'provider_recaptcha_v3_desc' => 'Invisible score from Google without a puzzle. Needs a key pair and sends data to Google.',
    'provider_turnstile_desc' => 'Mostly invisible check by Cloudflare without image puzzles. Needs a key pair.',
    'provider_hcaptcha_desc' => 'Privacy-focused check that shows an image puzzle when in doubt. Needs a key pair.',
    'provider_captchafox_desc' => 'GDPR-focused service from Germany with an interactive challenge. Needs a key pair.',
    'provider_friendly_captcha_desc' => 'Puzzle the browser solves on its own — no interaction for visitors. Needs a key pair.',

    // Math settings
    'math_settings' => 'Math Captcha Settings',
    'math_difficulty' => 'Difficulty',
    'math_difficulty_hint' => 'Controls the complexity of the math problem.',
    'difficulty_easy' => 'Easy (addition, 1-10)',
    'difficulty_medium' => 'Medium (add/subtract/multiply)',
    'difficulty_hard' => 'Hard (larger numbers)',

    // API key fields
    'api_keys' => 'API Keys',
    'general' => 'General',
    'keys_hint' => 'Keys are only needed for the providers you use — as the default or in a captcha field of a form.',
    'keys_stored' => 'Keys stored',
    'keys_incomplete' => 'Incomplete',
    'keys_missing' => 'No keys',
    'secret_stored' => 'A secret key is stored. Leave the field empty to keep it.',
    'site_key' => 'Site Key',
    'secret_key' => 'Secret Key',
    'threshold' => 'Score Threshold',
    'threshold_hint' => 'Minimum score (0.0 = likely bot, 1.0 = likely human). Recommended: 0.5',

    // Frontend
    'solve_challenge' => 'Solve the challenge',
    'your_answer' => 'Your answer',
    'new_challenge' => 'New challenge',
    'loading' => 'Loading...',

    // Messages
    'settings_saved' => 'Captcha settings saved.',
    'answer_required' => 'Please solve the captcha challenge.',
    'expired' => 'The captcha has expired. Please try again.',
    'wrong_answer' => 'Incorrect answer. Please try again.',
    'verification_failed' => 'Captcha verification failed. Please try again.',
    'not_configured' => 'Captcha is not properly configured.',
    'no_provider' => 'No captcha provider configured.',
    'score_too_low' => 'Captcha verification failed — suspected bot activity.',

    // Form builder
    'field_label' => 'Captcha',
    'field_info' => 'Captcha type and settings are configured in the Captcha Settings page.',
    'configured_in_settings' => 'Configured in Captcha Settings',
    'select_provider' => 'Captcha Provider',
    'use_global_default' => 'Use global default',
    'provider_not_configured' => 'This provider is not fully configured. Check Captcha Settings.',

    // Search keywords for the quick search (Ctrl/⌘ + K)
    'search_keywords' => 'captcha, spam protection, bots, forms, login',
];
