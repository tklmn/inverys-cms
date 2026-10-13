<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
</p>

# :shield: Captcha

A captcha plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Protect forms with a built-in math captcha or integrate external captcha providers. Works seamlessly with the form-builder plugin.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :1234: | **Math Captcha** | Built-in math challenge with configurable difficulty (easy, medium, hard) |
| :robot: | **reCAPTCHA v3** | Google reCAPTCHA v3 with configurable score threshold |
| :cloud: | **Cloudflare Turnstile** | Privacy-focused Turnstile widget |
| :heavy_check_mark: | **hCaptcha** | Privacy-first hCaptcha integration |
| :fox_face: | **CaptchaFox** | GDPR-compliant CaptchaFox widget |
| :wave: | **Friendly Captcha** | Accessibility-focused Friendly Captcha |
| :jigsaw: | **Form-Builder Integration** | Drag-and-drop captcha field in the form builder |
| :gear: | **Per-Form Provider** | Select a different captcha provider per form |
| :globe_with_meridians: | **Global Default** | Configure one global default, override per form as needed |
| :closed_lock_with_key: | **Encrypted Secret Keys** | Secret keys are stored encrypted and never sent back to the settings page |
| :mag: | **Quick Search** | The settings page is found by the admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>), e.g. *captcha* or *spam protection* |
| :earth_americas: | **i18n** | Full English and German translations |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The captcha plugin is included with the CMS and enabled by default during installation.

### Standalone via Composer

```bash
composer require inverys/captcha
```

Enable the plugin under **Extensions > Plugins**, then configure your preferred provider in the settings.

---

## :wrench: Configuration

Open **Extensions > Plugins** and click **Settings** next to the plugin — or find the page with the quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>). The settings need the `captcha-settings` permission (admin only).

### Global Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Captcha** | Globally enable or disable captcha verification | Enabled |
| **Captcha Provider** | Default provider used when forms don't specify one | Math |

### Math Captcha

| Setting | Description | Default |
|---------|-------------|---------|
| **Difficulty** | Easy (addition, 1-10), Medium (add/subtract/multiply), Hard (larger numbers) | Easy |

### External Providers

Each external provider requires a **Site Key** and **Secret Key** from the provider's dashboard. Secret keys are stored encrypted (setting type `encrypted`) and only decrypted for verification; leave the field empty to keep the stored key:

| Provider | Extra Settings |
|----------|---------------|
| reCAPTCHA v3 | Score threshold (0.0-1.0, default 0.5) |
| Turnstile | — |
| hCaptcha | — |
| CaptchaFox | — |
| Friendly Captcha | — |

---

## :link: Form-Builder Integration

1. Open a form in the form builder
2. Drag a **Captcha** field from the palette onto the canvas
3. In field settings, optionally select a specific provider (or leave as "Use global default")
4. Save — the captcha widget renders automatically on the frontend

If the captcha plugin is disabled or uninstalled, forms continue to work without captcha.

---

## :open_file_folder: File Structure

```
captcha/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/captcha.php
│   └── de/captcha.php
├── src/
│   ├── CaptchaServiceProvider.php
│   ├── Contracts/
│   │   └── CaptchaVerifier.php
│   ├── Support/
│   │   └── CaptchaSecrets.php
│   ├── Services/
│   │   ├── CaptchaService.php
│   │   ├── CaptchaResult.php
│   │   ├── CaptchaProviderInterface.php
│   │   └── Providers/
│   │       ├── MathProvider.php
│   │       ├── RecaptchaV3Provider.php
│   │       ├── TurnstileProvider.php
│   │       ├── HCaptchaProvider.php
│   │       ├── CaptchaFoxProvider.php
│   │       └── FriendlyCaptchaProvider.php
│   └── Http/Controllers/
│       ├── MathCaptchaController.php
│       └── SettingsController.php
└── views/
    ├── settings.blade.php
    ├── providers/
    │   ├── math.blade.php
    │   ├── recaptcha-v3.blade.php
    │   ├── turnstile.blade.php
    │   ├── hcaptcha.blade.php
    │   ├── captchafox.blade.php
    │   └── friendly-captcha.blade.php
    ├── fields/
    │   └── captcha.blade.php
    └── admin/
        └── field-type-script.blade.php
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
