# Captcha Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of the Captcha plugin for Inverys CMS.

### Added

- **Built-in math captcha** with configurable difficulty (easy, medium, hard); the challenge endpoint is throttled and a session keeps at most 10 open challenges
- **External providers:** Google reCAPTCHA v3 (with score threshold), Cloudflare Turnstile, hCaptcha, CaptchaFox and Friendly Captcha, verified server-side
- **Form Builder integration:** a captcha field in the drag-and-drop editor, connected through the `CaptchaVerifier` contract
- **Global default provider** with a per-form override
- **Encrypted secret keys:** stored encrypted with `APP_KEY`, only decrypted for verification and never sent back to the settings page; an empty field keeps the stored key
- **Settings page** with general options and a section per provider, found by the admin quick search (e.g. *captcha*, *spam protection*)
- Scripts carry the per-request CSP nonce
- Permission `captcha-settings` (admin only)
- English and German translations
