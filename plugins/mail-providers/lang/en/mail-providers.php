<?php

return [
    // Navigation
    'mail_providers' => 'Mail Providers',
    'settings' => 'Mail Provider Settings',

    // Global
    'active_provider' => 'Mail Provider',
    'active_provider_hint' => 'Select the mail transport to use for sending emails.',
    'from_address' => 'From Address',
    'from_address_hint' => 'Leave empty to use the sender from the server configuration (.env: MAIL_FROM_ADDRESS). Forms can set their own sender.',
    'from_name' => 'From Name',
    'from_name_hint' => 'Leave empty to use the name from the server configuration (.env: MAIL_FROM_NAME).',

    // Providers
    'provider_smtp' => 'SMTP',
    'provider_microsoft' => 'Microsoft 365',
    'provider_gmail' => 'Google / Gmail',

    // SMTP
    'smtp_settings' => 'SMTP Settings',
    'smtp_host' => 'SMTP Host',
    'smtp_host_hint' => 'Leave empty to send with the mail settings of the server configuration (.env: MAIL_*).',
    'smtp_port' => 'SMTP Port',
    'smtp_username' => 'Username',
    'smtp_password' => 'Password',
    'smtp_encryption' => 'Encryption',
    'smtp_encryption_tls' => 'TLS',
    'smtp_encryption_ssl' => 'SSL',
    'smtp_encryption_none' => 'None',

    // Microsoft Graph
    'microsoft_settings' => 'Microsoft Graph Settings',
    'microsoft_tenant_id' => 'Tenant ID',
    'microsoft_tenant_id_hint' => '"common" for multi-tenant, or your Azure AD tenant ID.',
    'microsoft_client_id' => 'Client ID (Application ID)',
    'microsoft_client_secret' => 'Client Secret',

    // Gmail
    'gmail_settings' => 'Gmail API Settings',
    'gmail_client_id' => 'Client ID',
    'gmail_client_secret' => 'Client Secret',

    // OAuth
    'oauth_connect' => 'Connect',
    'oauth_disconnect' => 'Disconnect',
    'oauth_reconnect' => 'Reconnect',
    'oauth_connected' => ':provider connected successfully.',
    'oauth_disconnected' => ':provider disconnected.',
    'oauth_status_connected' => 'Connected',
    'oauth_status_disconnected' => 'Not connected',
    'oauth_status_expired' => 'Token expired',
    'oauth_connected_as' => 'Connected as :email',
    'oauth_invalid_provider' => 'Invalid OAuth provider.',
    'oauth_state_mismatch' => 'OAuth state mismatch. Please try again.',
    'oauth_denied' => 'Authorization was denied.',
    'oauth_no_code' => 'No authorization code received.',
    'oauth_failed' => 'OAuth authorization failed.',

    // Package warnings

    // Test mail
    'test_section' => 'Send Test Email',
    'test_email' => 'Recipient Email',
    'test_send' => 'Send Test',
    'test_subject' => ':app — Test Email',
    'test_body' => 'This is a test email from :app to verify mail configuration.',
    'test_sent' => 'Test email sent to :email.',
    'test_failed' => 'Failed to send test email.',

    // Messages
    'settings_saved' => 'Mail provider settings saved.',
    'page_lead' => 'Choose how the CMS sends email — form notifications, password links, system messages.',
    'provider_smtp_desc' => 'Any mail server with credentials — including Microsoft 365, Google or a sending service via SMTP relay.',
    'provider_microsoft_desc' => 'Send through a Microsoft 365 mailbox via sign-in (OAuth). No password stored in the CMS.',
    'provider_gmail_desc' => 'Send through a Google Workspace or Gmail account via sign-in (OAuth).',
    'active_badge' => 'Active',
    'sender_heading' => 'Sender',
    'test_hint' => 'Sends a test message using the saved settings. Save first if you changed anything.',
    'password_stored' => 'A password is stored. Leave the field empty to keep the current password.',
    'secret_stored' => 'A client secret is stored. Leave the field empty to keep it.',

    // Search keywords for the quick search (Ctrl/⌘ + K)
    'search_keywords' => 'SMTP, email, mail server, sender, test mail, Microsoft 365, Gmail, OAuth',
];
