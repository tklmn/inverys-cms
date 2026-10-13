<?php

return [
    // Navigation
    'captcha' => 'Captcha',
    'settings' => 'Captcha-Einstellungen',
    'page_lead' => 'Schützen Sie Formulare vor Spam-Bots — wählen Sie die Standard-Methode und hinterlegen Sie die Schlüssel der externen Dienste, die Sie nutzen.',

    // Einstellungen
    'enabled' => 'Captcha aktivieren',
    'enabled_hint' => 'Captcha-Verifizierung für Formulare global aktivieren oder deaktivieren.',
    'provider' => 'Captcha-Anbieter',
    'provider_hint' => 'Gilt für jedes Captcha-Feld, das keinen eigenen Anbieter wählt.',
    'default_provider' => 'Standard-Anbieter',
    'default_badge' => 'Standard',

    // Anbieter
    'provider_math' => 'Mathe-Captcha (integriert)',
    'provider_recaptcha_v3' => 'Google reCAPTCHA v3',
    'provider_turnstile' => 'Cloudflare Turnstile',
    'provider_hcaptcha' => 'hCaptcha',
    'provider_captchafox' => 'CaptchaFox',
    'provider_friendly_captcha' => 'Friendly Captcha',
    'provider_math_desc' => 'Integriert, ohne externen Dienst: Besucher lösen eine kleine Rechenaufgabe. Keine Schlüssel nötig.',
    'provider_recaptcha_v3_desc' => 'Unsichtbare Bewertung durch Google ohne Rätsel. Benötigt ein Schlüsselpaar und überträgt Daten an Google.',
    'provider_turnstile_desc' => 'Meist unsichtbare Prüfung durch Cloudflare ohne Bilderrätsel. Benötigt ein Schlüsselpaar.',
    'provider_hcaptcha_desc' => 'Datenschutzorientierte Prüfung, die im Zweifel ein Bilderrätsel zeigt. Benötigt ein Schlüsselpaar.',
    'provider_captchafox_desc' => 'DSGVO-orientierter Dienst aus Deutschland mit interaktiver Aufgabe. Benötigt ein Schlüsselpaar.',
    'provider_friendly_captcha_desc' => 'Rätsel, das der Browser selbst löst — ohne Interaktion für Besucher. Benötigt ein Schlüsselpaar.',

    // Mathe-Einstellungen
    'math_settings' => 'Mathe-Captcha-Einstellungen',
    'math_difficulty' => 'Schwierigkeit',
    'math_difficulty_hint' => 'Bestimmt die Komplexität der Rechenaufgabe.',
    'difficulty_easy' => 'Einfach (Addition, 1-10)',
    'difficulty_medium' => 'Mittel (Addition/Subtraktion/Multiplikation)',
    'difficulty_hard' => 'Schwer (größere Zahlen)',

    // API-Schlüssel
    'api_keys' => 'API-Schlüssel',
    'general' => 'Allgemein',
    'keys_hint' => 'Schlüssel brauchen Sie nur für die Anbieter, die Sie verwenden — als Standard oder in einem Captcha-Feld eines Formulars.',
    'keys_stored' => 'Schlüssel gespeichert',
    'keys_incomplete' => 'Unvollständig',
    'keys_missing' => 'Keine Schlüssel',
    'secret_stored' => 'Ein geheimer Schlüssel ist gespeichert. Lassen Sie das Feld leer, um ihn zu behalten.',
    'site_key' => 'Site Key',
    'secret_key' => 'Secret Key',
    'threshold' => 'Score-Schwellenwert',
    'threshold_hint' => 'Mindest-Score (0.0 = wahrscheinlich Bot, 1.0 = wahrscheinlich Mensch). Empfohlen: 0,5',

    // Frontend
    'solve_challenge' => 'Löse die Aufgabe',
    'your_answer' => 'Ihre Antwort',
    'new_challenge' => 'Neue Aufgabe',
    'loading' => 'Laden...',

    // Meldungen
    'settings_saved' => 'Captcha-Einstellungen gespeichert.',
    'answer_required' => 'Bitte löse die Captcha-Aufgabe.',
    'expired' => 'Das Captcha ist abgelaufen. Bitte versuche es erneut.',
    'wrong_answer' => 'Falsche Antwort. Bitte versuche es erneut.',
    'verification_failed' => 'Captcha-Verifizierung fehlgeschlagen. Bitte versuche es erneut.',
    'not_configured' => 'Captcha ist nicht korrekt konfiguriert.',
    'no_provider' => 'Kein Captcha-Anbieter konfiguriert.',
    'score_too_low' => 'Captcha-Verifizierung fehlgeschlagen — Bot-Aktivität vermutet.',

    // Formular-Editor
    'field_label' => 'Captcha',
    'field_info' => 'Captcha-Typ und Einstellungen werden auf der Captcha-Einstellungsseite konfiguriert.',
    'configured_in_settings' => 'In den Captcha-Einstellungen konfiguriert',
    'select_provider' => 'Captcha-Anbieter',
    'use_global_default' => 'Globalen Standard verwenden',
    'provider_not_configured' => 'Dieser Anbieter ist nicht vollständig konfiguriert. Captcha-Einstellungen prüfen.',

    // Suchbegriffe fuer die Schnellsuche (Strg/⌘ + K)
    'search_keywords' => 'Captcha, Spamschutz, Bots, Formulare, Anmeldung',
];
