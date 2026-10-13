# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Cookie Consent plugin for Inverys CMS.

### Added

- **GDPR-compliant consent banner** with an opt-in model: *Accept all*, *Necessary only* and *Options* with a switch per category
- **Four categories:** Necessary (always on), Functional, Statistics, Marketing; visitors see which services belong to each
- **Service management** for external services with their script snippets, injected server-side only after consent
- **Content Security Policy:** every `<script>` of a service gets the per-request nonce; *Allowed addresses* per service (with templates for Google Analytics 4, Google Tag Manager, Matomo Cloud and Plausible) are allowed only on pages where the visitor agreed
- **Three layouts:** bottom bar, centered modal, corner box; custom colours for banner and buttons
- **Consent cookie** with configurable lifetime
- **Re-open button** (left or right) to change the choice at any time
- Links to the privacy policy and imprint pages
- **Translatable texts** with the Multilingual plugin (banner text and service descriptions)
- Settings page found by the admin quick search (e.g. *cookies*, *GDPR*); settings and services need `manage-plugins`
- English and German translations

### Security

- Service scripts run on website pages only — never in the backend and never on the account pages (login, password reset, email change, profile), whose addresses carry tokens that analytics would record
