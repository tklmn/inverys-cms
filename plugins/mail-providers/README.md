# Mail Providers Plugin

Extended mail transport support for the CMS. Adds three ways of sending mail as a system plugin — when disabled, core SMTP continues to work.

## Providers

| Provider | Auth Method | Transport |
|----------|------------|-----------|
| SMTP | Username/Password | Laravel built-in |
| Microsoft 365 | OAuth2 Authorization Code | Custom Symfony Transport (Graph API) |
| Google / Gmail | OAuth2 Authorization Code | Custom Symfony Transport (Gmail API) |

API services such as Amazon SES, Mailgun, Postmark or Resend are used through
their SMTP access, so no extra Composer package is needed.

## Dependencies

No additional Composer packages required.

## Settings Page

**System > Mail Providers** in the sidebar — the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>) finds it as well, e.g. with *smtp*, *sender* or *test mail*. Needs the `mail-providers-settings` permission (admin only).

The page has two columns:

- **Left:** the credentials of the selected provider and the default sender (*From Address*, *From Name*), saved together with one button. Empty sender fields fall back to `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` in the `.env`; forms can set their own sender. An empty SMTP host sends with the `MAIL_*` settings of the `.env`.
- **Right:** the provider choice (the saved one is marked *Active*) and **Send Test Email**, which sends a message with the *saved* settings to any address (save first if you changed anything; at most 5 tests per minute).

Passwords, client secrets and OAuth tokens are stored encrypted and never shown again; leave a secret field empty to keep the stored value. Settings use the `mailproviders.` prefix.

## OAuth Setup

### Microsoft Graph
1. Register an app in Azure Portal > App registrations
2. Add redirect URI: `https://yourdomain.com/mail-providers/oauth/microsoft/callback`
3. Add API permissions: `Mail.Send`, `User.Read` (the plugin also requests `offline_access` for the refresh token)
4. Create a client secret
5. Enter Tenant ID, Client ID, Client Secret in plugin settings
6. Click "Connect" to authorize

### Gmail API
1. Create a project in Google Cloud Console
2. Enable the Gmail API
3. Create OAuth 2.0 credentials (Web application)
4. Add redirect URI: `https://yourdomain.com/mail-providers/oauth/gmail/callback`
5. Enter Client ID and Client Secret in plugin settings
6. Click "Connect" to authorize (the plugin requests the scope `gmail.send`)

For both providers the callback requires a signed-in user; access tokens are refreshed automatically.

## File Structure

```
plugins/mail-providers/
  plugin.json
  composer.json
  lang/{en,de}/mail-providers.php
  views/
    settings.blade.php
    partials/
      provider-*.blade.php
      oauth-status.blade.php
  src/
    MailProvidersServiceProvider.php
    Contracts/
      MailProviderInterface.php
      OAuthProviderInterface.php
    Services/
      MailProviderService.php
      OAuthTokenManager.php
      Providers/
        SmtpProvider.php
        MicrosoftGraphProvider.php
        GmailProvider.php
    Transport/
      MicrosoftGraphTransport.php
      GmailApiTransport.php
    Http/
      Controllers/
        SettingsController.php
        OAuthController.php
```
