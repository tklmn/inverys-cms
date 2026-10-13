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

    'failed' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.',
    'password' => 'Das eingegebene Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',
    'inactive' => 'Ihr Konto ist noch nicht aktiviert. Bitte wenden Sie sich an einen Administrator.',
    'registration_pending' => 'Ihr Konto wurde erstellt und wartet auf die Freischaltung durch einen Administrator.',

    'two_factor' => [
        'challenge_title' => 'Zwei-Faktor-Authentifizierung',
        'totp_prompt' => 'Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein.',
        'email_prompt' => 'Ein Bestätigungscode wurde an Ihre E-Mail-Adresse gesendet.',
        'code_label' => 'Bestätigungscode',
        'code_placeholder' => '000000',
        'recovery_label' => 'Wiederherstellungscode',
        'recovery_placeholder' => 'XXXX-XXXX-XXXX',
        'verify_button' => 'Bestätigen',
        'resend_code' => 'Code erneut senden',
        'use_recovery' => 'Wiederherstellungscode verwenden',
        'use_auth_code' => 'Authentifizierungscode verwenden',
        'invalid_code' => 'Der eingegebene Code ist ungültig oder abgelaufen.',
        'code_resent' => 'Ein neuer Bestätigungscode wurde gesendet.',
        'resend_throttle' => 'Bitte warten Sie :seconds Sekunden, bevor Sie einen neuen Code anfordern.',
        'too_many_attempts' => 'Zu viele ungültige Codes. Bitte versuchen Sie es in :seconds Sekunden erneut.',
        'recovery_used' => 'Wiederherstellungscode akzeptiert. :remaining Codes verbleibend.',
        'all_recovery_used' => 'Alle Wiederherstellungscodes wurden verwendet. Die Zwei-Faktor-Authentifizierung wurde auf E-Mail-Verifizierung zurückgesetzt.',

        'profile_title' => 'Zwei-Faktor-Authentifizierung',
        'globally_disabled' => 'Die Zwei-Faktor-Authentifizierung ist derzeit von einem Administrator deaktiviert.',
        'status_disabled' => 'Deaktiviert',
        'user_disabled_desc' => 'Die Zwei-Faktor-Authentifizierung ist für Ihr Konto deaktiviert.',
        'enable_2fa' => '2FA aktivieren',
        'disable_2fa' => '2FA deaktivieren',
        'disable_2fa_confirm' => 'Die Zwei-Faktor-Authentifizierung für Ihr Konto wird deaktiviert. Fortfahren?',
        'user_enabled' => 'Die Zwei-Faktor-Authentifizierung wurde für Ihr Konto aktiviert.',
        'user_disabled' => 'Die Zwei-Faktor-Authentifizierung wurde für Ihr Konto deaktiviert.',
        'method_totp' => 'Authenticator-App',
        'method_email' => 'E-Mail-Code',
        'totp_active_desc' => 'Ihr Konto ist mit einer Authenticator-App gesichert.',
        'email_active_desc' => 'Bei jeder Anmeldung wird ein Bestätigungscode an Ihre E-Mail gesendet.',
        'enable_totp' => 'Authenticator-App aktivieren',
        'disable_totp' => 'Authenticator deaktivieren',
        'upgrade_hint' => 'Wechseln Sie zu einer Authenticator-App für schnellere, sicherere Anmeldung.',
        'setup_title' => 'Authenticator-App einrichten',
        'setup_instructions' => 'Scannen Sie den QR-Code mit Ihrer Authenticator-App (z.B. Google Authenticator, Authy) und geben Sie den 6-stelligen Code zur Bestätigung ein.',
        'setup_not_started' => 'TOTP-Einrichtung wurde nicht gestartet. Bitte aktivieren Sie sie zuerst.',
        'totp_switch_unavailable' => 'Eine Authenticator-App lässt sich nur einrichten, solange die Zwei-Faktor-Authentifizierung aktiv und noch keine App verbunden ist. Um die App zu wechseln, deaktivieren Sie sie zuerst.',
        'manual_entry' => 'Oder geben Sie diesen Schlüssel manuell ein:',
        'enter_code' => 'Authenticator-Code',
        'confirm_button' => 'Bestätigen',
        'totp_enabled' => 'Authenticator-App wurde erfolgreich aktiviert.',
        'totp_disabled' => 'Authenticator-App wurde deaktiviert. Sie erhalten bei der Anmeldung E-Mail-Codes.',
        'recovery_codes_title' => 'Wiederherstellungscodes',
        'recovery_codes_warning' => 'Speichern Sie diese Codes an einem sicheren Ort. Jeder Code kann nur einmal verwendet werden. Sie werden nicht erneut angezeigt.',
        'regenerate_codes' => 'Codes neu generieren',
        'regenerate_confirm' => 'Dies macht Ihre bestehenden Wiederherstellungscodes ungültig. Fortfahren?',
        'codes_regenerated' => 'Neue Wiederherstellungscodes wurden generiert.',
        'remaining_codes' => ':count Wiederherstellungscodes verbleibend.',
        'disable_confirm' => 'Dies deaktiviert Ihre Authenticator-App und wechselt zu E-Mail-Codes. Fortfahren?',
        'disable_hint' => 'Wechselt zur E-Mail-Verifizierung.',
        'send_code' => 'Code senden',
        'verify_code' => 'Bestätigungscode',
        'verify_code_email' => 'E-Mail-Code',

        'email_subject' => 'Ihr Bestätigungscode',
        'email_greeting' => 'Bestätigungscode',
        'email_line1' => 'Verwenden Sie den folgenden Code, um Ihre Anmeldung abzuschließen:',
        'email_line2' => 'Dieser Code läuft in :minutes Minuten ab.',
        'email_warning' => 'Wenn Sie sich nicht angemeldet haben, ändern Sie bitte sofort Ihr Passwort.',
        'email_regards' => 'Mit freundlichen Grüßen',
    ],

];
