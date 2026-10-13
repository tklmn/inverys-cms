<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installer-Übersetzungen
    |--------------------------------------------------------------------------
    |
    | Verwendet vom Pre-Boot-Installer (public/index.php) und
    | dem Installations-Assistenten (resources/views/install/wizard.blade.php).
    |
    */

    // Pre-boot (Abhängigkeiten-Installer)
    'setup_title' => 'Einrichtung',
    'setup_subtitle' => 'Abhängigkeiten müssen installiert werden, bevor das CMS starten kann.',
    'setup_info' => 'Dies ist eine neue Installation. Klicken Sie auf die Schaltfläche, um PHP- und Frontend-Abhängigkeiten automatisch zu installieren. Dies erfordert <strong>Composer</strong> und optional <strong>Node.js / npm</strong> auf dem Server.',
    'setup_btn' => 'Abhängigkeiten installieren',
    'setup_btn_loading' => 'Wird installiert...',
    'setup_btn_retry' => 'Erneut versuchen',
    'setup_step_composer' => 'PHP-Pakete installieren (Composer)',
    'setup_step_npm' => 'Frontend-Assets erstellen (npm)',
    'setup_err_composer' => 'Composer nicht gefunden. Bitte installieren Sie Composer oder laden Sie composer.phar in das Projektverzeichnis hoch.',
    'setup_err_composer_failed' => 'Composer-Installation fehlgeschlagen.',
    'setup_err_npm' => 'npm-Build fehlgeschlagen.',
    'setup_warn_npm' => 'npm/Node.js nicht auf dem Server gefunden. Frontend-Assets wurden nicht erstellt. Führen Sie "npm install && npm run build" manuell aus oder laden Sie die Assets hoch.',
    'setup_warn_npm_manual' => 'npm/Node.js nicht auf dem Server gefunden. Frontend-Assets konnten nicht automatisch erstellt werden.<br><br>Bitte führen Sie folgende Befehle lokal aus und laden Sie die Dateien erneut hoch:<br><code style="display:block;margin-top:.5rem;padding:.5rem .75rem;background:rgba(0,0,0,.3);border-radius:6px;">npm install && npm run build</code>',
    'setup_subtitle_assets' => 'Frontend-Assets müssen erstellt werden, bevor das CMS starten kann.',
    'setup_info_assets' => 'PHP-Abhängigkeiten sind bereits installiert, aber die Frontend-Assets fehlen. Klicken Sie auf die Schaltfläche, um sie automatisch zu erstellen. Dies erfordert <strong>Node.js / npm</strong> auf dem Server.',
    'setup_btn_assets' => 'Frontend-Assets erstellen',
    'setup_err_network' => 'Anfrage fehlgeschlagen',

    // Wizard — Allgemein
    'title' => 'Installations-Assistent',
    'subtitle' => 'Richten Sie Ihr CMS in wenigen Schritten ein',

    // Wizard — Schritt 1: Voraussetzungen
    'requirements' => 'Systemvoraussetzungen',
    'requirements_desc' => 'Prüfung, ob Ihr Server alle Anforderungen erfüllt.',
    'php_extensions' => 'PHP-Erweiterungen',
    'dir_permissions' => 'Verzeichnisberechtigungen',
    'writable' => 'Beschreibbar',
    'not_writable' => 'Nicht beschreibbar',
    'extension' => ':name-Erweiterung',
    'extension_gd' => 'GD-Erweiterung (Vorschaubilder)',
    'loaded' => 'Geladen',
    'missing' => 'Fehlt',
    'env_file' => '.env-Datei',

    // Wizard — Schritt 2: Datenbank
    'database' => 'Datenbank-Konfiguration',
    'database_desc' => 'Wählen Sie den Datenbanktreiber und geben Sie die Verbindungsdaten ein.',
    'db_driver' => 'Datenbanktreiber',
    'sqlite_label' => 'SQLite (am einfachsten — keine Konfiguration nötig)',
    'host' => 'Host',
    'port' => 'Port',
    'username' => 'Benutzername',
    'password' => 'Passwort',
    'connect_btn' => 'Verbinden & Datenbanken laden',
    'test_btn' => 'Verbindung testen',
    'testing' => 'Teste...',
    'connecting' => 'Verbinde...',
    'connected' => 'Verbunden!',
    'conn_failed' => 'Verbindung fehlgeschlagen',
    'select_db' => 'Datenbank',
    'select_db_placeholder' => '— Datenbank auswählen —',
    'select_db_connect_first' => '-- Erst mit Server verbinden --',
    'no_empty_db' => 'Keine leeren Datenbanken gefunden',
    'no_empty_db_msg' => 'Verbunden — aber keine leeren Datenbanken gefunden. Bitte erstellen Sie zuerst eine leere Datenbank auf Ihrem Server.',
    'db_hint' => 'Es werden nur leere Datenbanken (ohne Tabellen) angezeigt. Erstellen Sie eine neue Datenbank, falls keine erscheint.',
    'select_db_error' => 'Bitte wählen Sie eine Datenbank aus.',

    // Wizard — Schritt 3: Einstellungen
    'settings' => 'Website-Einstellungen',
    'settings_desc' => 'Konfigurieren Sie den Namen, die URL und die Sprache Ihrer Website.',
    'site_name' => 'Website-Name',
    'site_url' => 'Website-URL',
    'site_url_hint' => 'Die vollständige URL, unter der Ihre Website erreichbar ist (ohne Schrägstrich am Ende).',
    'language' => 'Sprache',
    'demo_content' => 'Demo-Inhalte',
    'demo_desc' => 'Kernsystem (Rollen, Berechtigungen, Blog) wird immer installiert. Wählen Sie zusätzliche Demo-Inhalte.',
    'seed_pages' => 'Beispielseiten',
    'seed_pages_desc' => 'Home, Über uns, Kontakt, Services und mehr',
    'seed_media' => 'Demo-Medien',
    'seed_media_desc' => 'SVG-Bilder und Assets für die Medienbibliothek',
    'seed_categories' => 'Seitenkategorien',
    'seed_categories_desc' => 'Allgemein, Blog, Dokumentation, etc.',
    'seed_blog' => 'Blog-Demobeiträge',
    'seed_blog_desc' => 'Beispiel-Blogartikel mit Beitragsbildern',

    // Wizard — Schritt 4: Admin
    'admin' => 'Admin-Konto',
    'admin_desc' => 'Erstellen Sie das erste Administratorkonto.',
    'first_name' => 'Vorname',
    'last_name' => 'Nachname',
    'username' => 'Benutzername',
    'email' => 'E-Mail-Adresse',
    'password_label' => 'Passwort',
    'password_confirm' => 'Passwort bestätigen',
    'password_placeholder' => 'Mind. 10 Zeichen',
    'password_confirm_placeholder' => 'Passwort wiederholen',
    'err_required' => 'Alle Felder sind erforderlich.',
    'err_email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
    'err_password_short' => 'Mindestens 10 Zeichen mit Groß- und Kleinbuchstaben, einer Zahl und einem Sonderzeichen.',
    'err_password_match' => 'Die Passwörter stimmen nicht überein.',

    // Wizard — Schritt 5: Installation
    'installing' => 'Inverys CMS wird installiert',
    'installing_desc' => 'Bitte warten Sie, während alles eingerichtet wird...',
    'all_done' => 'Fertig!',
    'paused' => 'Installation pausiert.',
    'paused_resume' => 'Installation pausiert — klicken Sie auf Wiederholen.',
    'retry' => 'Wiederholen',
    'complete_title' => 'Installation abgeschlossen!',
    'complete_desc' => 'Ihr Inverys CMS ist bereit. Melden Sie sich mit dem erstellten Admin-Konto an.',
    'go_login' => 'Zum Login',

    // Wizard — Navigation
    'back' => '← Zurück',
    'next' => 'Weiter →',
    'install_now' => 'Jetzt installieren',
    'err_save' => 'Konfiguration konnte nicht gespeichert werden.',
    'err_generic' => 'Ein Fehler ist aufgetreten.',
    'err_network' => 'Netzwerkfehler',
    'err_plugin' => 'Das System-Plugin „:slug“ konnte nicht aktiviert werden. Details finden Sie in storage/logs/laravel.log.',

    // Standard-Homepage-Inhalte (database/seeders/StarterContent.php)
    'home_title' => 'Startseite',
    'home_eyebrow' => 'Ihre neue Website',
    'home_heading' => 'Willkommen bei :name',
    'home_text' => 'Ihre Website ist bereit. Bearbeiten Sie diese Seite im Admin-Bereich, um eigene Inhalte hinzuzufügen.',
    'home_cta' => 'Zum Admin-Bereich',
    'home_cta_blog' => 'Zum Blog',
    'home_welcome_eyebrow' => 'Erste Schritte',
    'home_welcome_title' => 'Ihre nächsten Schritte',
    'home_welcome_text' => 'Melden Sie sich im Admin-Bereich an, um Ihre Website anzupassen, neue Seiten zu erstellen, Medien zu verwalten und Ihr Theme zu konfigurieren.',
    'home_step_pages_title' => 'Seiten gestalten',
    'home_step_pages_text' => 'Erstellen Sie Seiten mit dem visuellen Page-Builder. Blöcke, Hintergründe und Icons wählen Sie aus Listen, ganz ohne Code.',
    'home_step_design_title' => 'Design anpassen',
    'home_step_design_text' => 'Legen Sie Logo, Farben und Schrift in den Theme-Einstellungen fest und bearbeiten Sie den Fußbereich mit denselben Blöcken wie Ihre Seiten.',
    'home_step_blog_title' => 'Den ersten Beitrag schreiben',
    'home_step_blog_text' => 'Veröffentlichen Sie Neuigkeiten und Artikel im Blog. Beiträge lassen sich planen, in Kategorien ordnen und über die Suche finden.',

    // Standard-Fußbereich (Installation ohne Beispielseiten)
    'footer_about' => 'Schön, dass Sie hier sind. Weitere Inhalte folgen in Kürze.',
    'footer_links' => 'Schnellzugriff',
    'footer_blog' => 'Blog',
    'footer_copyright' => '© :year :name. Alle Rechte vorbehalten.',

    // Schritt-Labels (Installationsfortschritt)
    'step_env' => 'Umgebungskonfiguration schreiben',
    'step_key' => 'Anwendungsschlüssel generieren',
    'step_database' => 'Datenbank einrichten',
    'step_pages' => 'Beispielseiten installieren',
    'step_media' => 'Demo-Medien installieren',
    'step_categories' => 'Seitenkategorien installieren',
    'step_blog' => 'Blog-Demoinhalte installieren',
    'step_admin' => 'Administratorkonto erstellen',
    'step_storage' => 'Speicherverzeichnis verknüpfen',
    'step_finalize' => 'Installation abschließen',
];
