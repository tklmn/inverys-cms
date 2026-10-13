# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Mail Providers plugin for Inverys CMS.

### Added

- **Three ways of sending mail:** SMTP, Microsoft 365 (Graph API) and Google / Gmail (Gmail API); API services such as Amazon SES, Mailgun, Postmark or Resend are used through SMTP
- **OAuth2** authorization code flow for Microsoft 365 and Gmail with custom Symfony transports, a connection status and automatic token refresh
- **Settings page** in two columns: credentials and the default sender on the left, provider choice and test mail on the right; found by the admin quick search (e.g. *smtp*)
- **`.env` fallbacks:** empty sender fields use `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`, an empty SMTP host the `MAIL_*` settings
- **Test email** with the saved settings (at most 5 per minute)
- **Encrypted secrets:** passwords, client secrets and OAuth tokens are stored encrypted and never sent back to the form; an empty field keeps the stored value
- Permission `mail-providers-settings` (admin only), also required to connect or disconnect OAuth accounts
- English and German translations
