<?php

return [
    // Navigation
    'mail_providers' => 'Mail-Anbieter',
    'settings' => 'Mail-Anbieter-Einstellungen',

    // Global
    'active_provider' => 'Mail-Anbieter',
    'active_provider_hint' => 'Wählen Sie den Mail-Transport für den E-Mail-Versand.',
    'from_address' => 'Absender-Adresse',
    'from_address_hint' => 'Leer lassen, um den Absender aus der Server-Konfiguration zu verwenden (.env: MAIL_FROM_ADDRESS). Formulare können einen eigenen Absender festlegen.',
    'from_name' => 'Absender-Name',
    'from_name_hint' => 'Leer lassen, um den Namen aus der Server-Konfiguration zu verwenden (.env: MAIL_FROM_NAME).',

    // Anbieter
    'provider_smtp' => 'SMTP',
    'provider_microsoft' => 'Microsoft 365',
    'provider_gmail' => 'Google / Gmail',

    // SMTP
    'smtp_settings' => 'SMTP-Einstellungen',
    'smtp_host' => 'SMTP-Host',
    'smtp_host_hint' => 'Leer lassen, um mit den Mail-Einstellungen der Server-Konfiguration zu versenden (.env: MAIL_*).',
    'smtp_port' => 'SMTP-Port',
    'smtp_username' => 'Benutzername',
    'smtp_password' => 'Passwort',
    'smtp_encryption' => 'Verschlüsselung',
    'smtp_encryption_tls' => 'TLS',
    'smtp_encryption_ssl' => 'SSL',
    'smtp_encryption_none' => 'Keine',

    // Microsoft Graph
    'microsoft_settings' => 'Microsoft-Graph-Einstellungen',
    'microsoft_tenant_id' => 'Tenant-ID',
    'microsoft_tenant_id_hint' => '"common" für Multi-Tenant oder Ihre Azure-AD-Tenant-ID.',
    'microsoft_client_id' => 'Client-ID (Application ID)',
    'microsoft_client_secret' => 'Client Secret',

    // Gmail
    'gmail_settings' => 'Gmail-API-Einstellungen',
    'gmail_client_id' => 'Client-ID',
    'gmail_client_secret' => 'Client Secret',

    // OAuth
    'oauth_connect' => 'Verbinden',
    'oauth_disconnect' => 'Trennen',
    'oauth_reconnect' => 'Erneut verbinden',
    'oauth_connected' => ':provider erfolgreich verbunden.',
    'oauth_disconnected' => ':provider getrennt.',
    'oauth_status_connected' => 'Verbunden',
    'oauth_status_disconnected' => 'Nicht verbunden',
    'oauth_status_expired' => 'Token abgelaufen',
    'oauth_connected_as' => 'Verbunden als :email',
    'oauth_invalid_provider' => 'Ungültiger OAuth-Anbieter.',
    'oauth_state_mismatch' => 'OAuth-State stimmt nicht überein. Bitte erneut versuchen.',
    'oauth_denied' => 'Autorisierung wurde verweigert.',
    'oauth_no_code' => 'Kein Autorisierungscode empfangen.',
    'oauth_failed' => 'OAuth-Autorisierung fehlgeschlagen.',

    // Paket-Warnungen

    // Test-Mail
    'test_section' => 'Test-E-Mail senden',
    'test_email' => 'Empfaenger-E-Mail',
    'test_send' => 'Test senden',
    'test_subject' => ':app — Test-E-Mail',
    'test_body' => 'Dies ist eine Test-E-Mail von :app zur Überprüfung der Mail-Konfiguration.',
    'test_sent' => 'Test-E-Mail an :email gesendet.',
    'test_failed' => 'Test-E-Mail konnte nicht gesendet werden.',

    // Meldungen
    'settings_saved' => 'Mail-Anbieter-Einstellungen gespeichert.',
    'page_lead' => 'Legen Sie fest, über welchen Weg das CMS E-Mails verschickt — Formular-Benachrichtigungen, Passwort-Links, Systemmeldungen.',
    'provider_smtp_desc' => 'Jeder Mailserver mit Zugangsdaten — auch Microsoft 365, Google oder ein Versanddienst per SMTP-Relay.',
    'provider_microsoft_desc' => 'Versand über ein Microsoft-365-Postfach per Anmeldung (OAuth). Kein Passwort im CMS.',
    'provider_gmail_desc' => 'Versand über ein Google-Workspace- oder Gmail-Konto per Anmeldung (OAuth).',
    'active_badge' => 'Aktiv',
    'sender_heading' => 'Absender',
    'test_hint' => 'Schickt eine Testnachricht mit den gespeicherten Einstellungen. Vorher speichern, wenn Sie etwas geändert haben.',
    'password_stored' => 'Ein Passwort ist gespeichert. Lassen Sie das Feld leer, um das aktuelle Passwort zu behalten.',
    'secret_stored' => 'Ein Client-Secret ist gespeichert. Lassen Sie das Feld leer, um es zu behalten.',

    // Suchbegriffe fuer die Schnellsuche (Strg/⌘ + K)
    'search_keywords' => 'SMTP, E-Mail, Mailserver, Absender, Testmail, Microsoft 365, Gmail, OAuth',
];
