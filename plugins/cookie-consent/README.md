<p align="center">
  <img src="https://img.shields.io/badge/Inverys_CMS-Plugin-6366f1?style=for-the-badge" alt="Inverys CMS Plugin">
  <img src="https://img.shields.io/badge/Version-1.0.0-22c55e?style=for-the-badge" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/GDPR-Compliant-16a34a?style=for-the-badge" alt="GDPR Compliant">
</p>

# :shield: Cookie Consent

A GDPR-compliant cookie consent plugin for [Inverys CMS](https://github.com/tklmn/inverys-cms). Category-based opt-in with external service management and conditional script injection.

---

## :sparkles: Features

| | Feature | Description |
|---|---|---|
| :shield: | **GDPR Compliant** | Opt-in model — no scripts without consent |
| :four_leaf_clover: | **4 Categories** | Necessary (always on), Functional, Statistics, Marketing |
| :gear: | **Service Management** | CRUD for external services (Google Analytics, etc.) with script snippets |
| :syringe: | **Script Injection** | Server-side conditional rendering based on consent cookie |
| :lock: | **Content Security Policy** | Every `<script>` of a service gets the CSP nonce; its *Allowed addresses* are allowed only on pages where the visitor agreed |
| :art: | **3 Layouts** | Bottom bar, centered modal, corner box |
| :paintbrush: | **Custom Colors** | Banner BG, text, button BG, button text — all customizable |
| :cookie: | **Real Cookie** | Consent stored as cookie (not localStorage) with configurable lifetime |
| :mag: | **Transparent** | Visitors see which services are in each category |
| :repeat: | **Re-open** | Floating button (left or right) to change consent preferences anytime |
| :link: | **Page Links** | Link to privacy policy and imprint pages |
| :earth_africa: | **Translatable Texts** | With the [Multilingual](../multilingual/) plugin, the banner text and the service descriptions are translated under *Languages > Translate texts* |
| :mag_right: | **Quick Search** | The settings page is found by the admin quick search (<kbd>Ctrl</kbd>+<kbd>K</kbd>), e.g. *cookies* or *GDPR* |
| :globe_with_meridians: | **i18n** | Full English and German translations |

---

## :zap: Quick Start

### Bundled with Inverys CMS

The plugin ships in `plugins/` but is not enabled by the installer. Enable it under **Extensions > Plugins**.

### ZIP Upload

1. Download the latest release as ZIP
2. Go to **Extensions > Plugins > Upload Package** (admin role, `CMS_CODE_EDITING=true`)
3. Select the ZIP file and click **Install**
4. Enable the plugin under **Extensions > Plugins**

### Composer

```bash
composer require inverys/cookie-consent
```

---

## :wrench: Configuration

### General Settings

Go to **Extensions > Cookie Consent** in the sidebar. Settings and services need the `manage-plugins` permission, which only the admin role can have.

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable** | Show/hide the consent banner | Enabled |
| **Banner Text** | Custom text (leave empty for default) | Default i18n text |
| **Privacy Policy Page** | Select from CMS pages | - |
| **Imprint Page** | Select from CMS pages | - |
| **Position** | Bottom Bar / Modal / Corner Box | Bottom Bar |
| **Colors** | Banner BG, text, button BG, button text | Dark slate/indigo |
| **Cookie Lifetime** | Days until consent expires | 365 |
| **Re-open Button** | Show floating button to reopen banner | Enabled |
| **Re-open Position** | Left or right corner | Left |

### External Services

Go to **Cookie Consent > Manage External Services**:

| Field | Description |
|-------|-------------|
| **Name** | Display name (e.g. "Google Analytics") |
| **Category** | Functional / Statistics / Marketing |
| **Description** | Shown to visitors in the consent details |
| **Script Code** | Full script tag to inject; every `<script>` gets the CSP nonce |
| **Position** | Head / Body Start / Body End |
| **Allowed Addresses** | Hosts the service loads from or sends data to, one per line, `https://` only (`https://*.example.com` allowed). Allowed in the Content Security Policy (scripts, connections, images, frames) only on pages where the visitor agreed. *Template* fills in the addresses of Google Analytics 4, Google Tag Manager, Matomo Cloud or Plausible |

---

## :closed_lock_with_key: How It Works

1. **No consent** — Only the banner is shown, no optional scripts are loaded
2. **Visitor chooses** — Accept All / Save Selection / Necessary Only
3. **Cookie is set** — `cc_consent` cookie with category preferences as JSON
4. **Page reloads** — PHP reads the cookie server-side and conditionally renders scripts
5. **Re-open** — Visitor can change preferences anytime via floating button

### Cookie Format

```json
{
  "ts": 1711018800,
  "necessary": true,
  "functional": true,
  "statistics": false,
  "marketing": false
}
```

---

## :open_file_folder: File Structure

```
cookie-consent/
├── plugin.json
├── composer.json
├── lang/
│   ├── en/cc.php
│   └── de/cc.php
├── src/
│   ├── CookieConsentServiceProvider.php
│   ├── SettingsController.php
│   ├── ServiceController.php
│   └── ServiceScript.php         (CSP nonce and allowed addresses)
└── views/
    ├── banner.blade.php          (frontend consent UI)
    ├── head-scripts.blade.php    (conditional head script injection)
    ├── body-scripts.blade.php    (conditional body script injection)
    ├── settings.blade.php        (admin settings)
    ├── services.blade.php        (admin service list)
    └── service-form.blade.php    (admin service create/edit)
```

---

## :page_facing_up: Requirements

- **Inverys CMS** 1.0 or newer
- **PHP** >= 8.4

## :handshake: Author

**Tom Kilimann** — [tkilimann@icloud.com](mailto:tkilimann@icloud.com)

## :scroll: License

MIT
