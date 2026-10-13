# EU Cyber Resilience Act — Readiness Checklist

Status of Inverys CMS against the essential requirements of the
[Cyber Resilience Act](https://eur-lex.europa.eu/eli/reg/2024/2847/oj)
(Regulation (EU) 2024/2847, "CRA"). Written for the maintainer as a working
document: what the project already does, where the evidence is, and what is
still open.

> **Not legal advice.** This checklist describes technical and organisational
> measures. Whether and in which role the CRA applies to a particular
> distribution of Inverys CMS depends on how it is made available and has to be
> assessed for each case.

*Assessed version: 1.0.0, 2026-10-13.*

## 1. Scope and roles (overview)

| Role | When it typically applies | What it means |
|------|---------------------------|---------------|
| No CRA obligations | Free and open-source software developed or supplied **outside a commercial activity** — e.g. published on GitHub without monetisation (see the recitals on free and open-source software, in particular Recital 18) | Voluntary good practice; this checklist is still useful for users and integrators |
| Open-source software steward (Art. 3(14), Art. 24) | A **legal person** (not an individual) that systematically provides sustained support for the development of FOSS that is intended for commercial activities | Light regime: documented cybersecurity policy, vulnerability handling and cooperation with market surveillance authorities; reporting duties of Art. 14 only to the extent it is involved in the development |
| Manufacturer (Art. 3(13), Art. 13) | The software is **placed on the EU market in the course of a commercial activity** — e.g. sold, licensed for a fee, part of a paid product or offering, monetised | All essential requirements (Annex I), conformity assessment (for a CMS usually module A, internal control — it is not listed in Annexes III/IV), technical documentation, EU declaration of conformity, CE marking, support period, reporting (Art. 14) |
| Integrator | A company that ships Inverys CMS as a component of its own commercial product | Is the manufacturer of **its** product and must exercise due diligence on this component (Art. 13(5)); the SBOM, advisories and this document support that |

Operating a website with Inverys CMS is a service, not placing a product on the
market; it is covered by other rules (e.g. NIS2, GDPR), not by the CRA.

**Timeline:** entry into force 10 December 2024 · reporting obligations
(Art. 14) apply from **11 September 2026** · all other obligations from
**11 December 2027**.

Legend: ✅ met · 🟡 partly met · ❌ open · ➖ not applicable / operator's task

## 2. Annex I Part I — security properties of the product

| # | Requirement | Status | Evidence | Gaps / next steps |
|---|-------------|--------|----------|-------------------|
| (1) | Appropriate level of cybersecurity based on the risks | 🟡 | Security audits with regression tests (`tests/Feature/Security/`); RBAC, CSP, sanitising | No written risk assessment (Annex VII (3)); write a short threat model (assets, actors, attack surface: frontend, backend, API, installer, plugins) |
| (2)(a) | No known exploitable vulnerabilities when made available | ✅ | CI: `composer audit`, `npm audit --audit-level=high` (`.github/workflows/ci.yml`); release gate in `bin/release.sh`; `.github/dependabot.yml` | Enable Dependabot alerts / security updates in the repository settings |
| (2)(b) | Secure by default configuration, reset to original state | 🟡 | Installer writes `APP_ENV=production`, `APP_DEBUG=false`, encrypted sessions, secure cookies on HTTPS; 2FA on by default; password policy; no default credentials on the installer path; trusted-host check; pre-install `.env` with `APP_DEBUG=false` | No "enforce 2FA for admins" (users can opt out); "reset to original state" = reinstall (documented in [SECURITY-HARDENING.md](SECURITY-HARDENING.md#decommissioning)) |
| (2)(c) | Vulnerabilities addressable through security updates; notification of updates | 🟡 | Patch releases, [SECURITY.md](../SECURITY.md) support table, GitHub Security Advisories, update procedure in [SECURITY-HARDENING.md](SECURITY-HARDENING.md#updates); daily update check (`cms:check-updates`) with a backend notification for `manage-updates`; update from the backend (System → Updates) with SHA-256 check, backup and rollback | No automatic installation of updates — the CRA asks for automatic security updates (on by default, with opt-out) only "where applicable"; decide whether it applies |
| (2)(d) | Protection from unauthorised access; report possible unauthorised access | ✅ | Authentication with rate limiting and lockout, 2FA (email/TOTP + recovery codes), RBAC per route and per plugin, inactive/blocked users, sessions ended on password change/reset, API tokens with scopes and expiry, installer lock; activity log of logins/failed logins | Optional: e-mail to the user on login from a new device / after 2FA was disabled |
| (2)(e) | Confidentiality of stored, transmitted and processed data (encryption) | ✅ | HTTPS + HSTS, `SESSION_ENCRYPT`, secrets in settings encrypted with `APP_KEY` (mail providers, captcha), TOTP secrets and recovery codes encrypted (cast `encrypted`), passwords bcrypt, API tokens hashed (SHA-256); `.env` and its backups (`.env.backup`, `.env.*.backup`) excluded from the repository and the release packages | 2FA e-mail codes are stored in plain text for their short lifetime (low risk; could be hashed) |
| (2)(f) | Integrity of data, programs and configuration; report corruption | 🟡 | CSRF protection, validation, HTMLPurifier, revisions of pages, blog posts, translations and plugin content (restoring first keeps the current state as *Before restore*), upload checks (extension + MIME, ZIP traversal/blocklist), `SHA256SUMS` for release archives, SHA-256 check of the update package before a backend update | No signed releases/tags (proposal: sign tags or use GitHub artifact attestations); no integrity check of installed code |
| (2)(g) | Data minimisation | 🟡 | Only account data needed for the CMS; retention for activity log (90 days default) and notifications; IP addresses only in logs/notifications | Document all personal data (accounts, form submissions, logs, IPs) in one place for operators' privacy notices |
| (2)(h) | Availability of essential functions, resilience against DoS | 🟡 | Rate limits (login 5/min, 2FA 5/min, password checks 6/min, password reset 5/min, forms, captcha, API configurable), sitemap cache, error-log storm protection | No global request throttling for anonymous frontend traffic (usually the web server's / CDN's job — documented) |
| (2)(i) | Minimise negative impact on other devices/networks | ✅ | No outbound traffic except configured mail/captcha/OAuth providers and the update check (GitHub hosts only); no telemetry; HTML minification runs on the server with a bundled library and contacts no service | — |
| (2)(j) | Limit attack surfaces, incl. external interfaces | 🟡 | CSP without `unsafe-inline` scripts, strict host check, API behind token + `api-access` permission, installer locked, public blog API read-only; code on the server (theme file editor, plugin/theme upload, CMS update from the backend) for the `admin` role only and switched off with `CMS_CODE_EDITING=false` (`App\Support\CodeAccess`) | Custom scripts of the cookie-consent plugin are not covered by `CMS_CODE_EDITING` |
| (2)(k) | Reduce impact of incidents (exploitation mitigation) | ✅ | CSP with nonces, `X-Frame-Options`, `nosniff`, sandbox CSP for uploads, no PHP execution in `storage/`, least-privilege roles | — |
| (2)(l) | Security-relevant information by recording/monitoring internal activity, with opt-out | ✅ | Activity-log plugin (logins, failed logins, changes to content/users/roles/permissions/settings, secrets masked; can be disabled), HTTP error notifications, Laravel logs | — |
| (2)(m) | Users can securely remove all data and settings | 🟡 | Users can delete their account (password confirmed); admins can delete users and empty the trash; decommissioning steps documented | Account deletion is a soft delete until the trash is emptied — document this for users or hard-delete personal data on self-deletion |

## 3. Annex I Part II — vulnerability handling

| # | Requirement | Status | Evidence | Gaps / next steps |
|---|-------------|--------|----------|-------------------|
| (1) | Identify and document components and vulnerabilities, incl. an SBOM | ✅ | CycloneDX 1.5 SBOMs in [`sbom/`](../sbom/) for Composer (licences, purls, VCS/commit references, dependency graph, bundled plugins and themes) and npm (with hashes); regenerated by `bin/release.sh` and attached to each GitHub release. Operators can download an **installation SBOM** (CycloneDX 1.5, generated from what is actually installed, incl. uploaded plugins/themes and the PHP platform) under backend → System info → Components (SBOM) | Composer lock files carry no archive hashes for GitHub zipballs — integrity is anchored in the pinned commit references |
| (2) | Remediate without delay; security updates separate from feature updates where feasible | ✅ | Patch releases (`X.Y.Z`) for security fixes; timelines in [SECURITY.md](../SECURITY.md) | — |
| (3) | Regular tests and reviews | 🟡 | Test suite in CI (incl. `tests/Feature/Security/`), Pint, dependency audits; the same tests and audits as release gates in `bin/release.sh`; security audits with regression tests | Define a cadence (e.g. security review before every minor release) and optionally add static analysis (PHPStan/Larastan) and a SAST job (e.g. CodeQL for JavaScript) |
| (4) | Publicly disclose fixed vulnerabilities (description, affected product, impact, severity, remediation) | 🟡 | Process: GitHub Security Advisories + CHANGELOG `### Security` ([SECURITY-PROCESS.md](SECURITY-PROCESS.md)) | Advisories need a **public** repository; the repository is currently private |
| (5) | Coordinated vulnerability disclosure policy | ✅ | [SECURITY.md](../SECURITY.md) (90-day disclosure, credits, timelines) | — |
| (6) | Contact address for reporting; facilitate information sharing | 🟡 | GitHub Private Vulnerability Reporting in SECURITY.md (no personal e-mail published); SBOM `security-contact` reference; `/.well-known/security.txt` for sites (Settings → Security, default empty; `SECURITY_TXT_CONTACT` as fallback) | **Private vulnerability reporting is not enabled** (not available while the repository is private) |
| (7) | Mechanisms to securely distribute updates | 🟡 | GitHub releases over HTTPS, Composer with `composer.lock`, `SHA256SUMS` for release archives; update package `inverys-cms-<version>.zip` with `.sha256`, checked by the backend update, which downloads from GitHub hosts only | No signatures/attestations (see (2)(f)) |
| (8) | Security updates without delay, free of charge, with advisory messages | ✅ | Free patch releases; advisories and release notes | — |

## 4. Annex II — information and instructions for users

| Item | Status | Where |
|------|--------|-------|
| Manufacturer / maintainer name and contact | 🟡 | `composer.json` / `plugin.json` authors, SECURITY.md — add the maintainer (name, postal/e-mail address) to README if acting as manufacturer |
| Single point of contact for vulnerabilities | ✅ | [SECURITY.md](../SECURITY.md) |
| Product identification (name, version) | ✅ | `config/cms.php` (`version`), backend → System info, SBOM |
| Intended purpose, security properties | ✅ | README, [USER-GUIDE.md](USER-GUIDE.md), SECURITY.md "Built-in Security Controls" |
| Known foreseeable circumstances that may lead to risks | ✅ | [SECURITY-HARDENING.md](SECURITY-HARDENING.md) (debug mode, powerful permissions, proxies, host names) |
| EU declaration of conformity | ➖ | Only for manufacturers (Annex V) |
| Type of technical security support and end of the support period | ✅ | SECURITY.md "Supported Versions" / "Support period" |
| Secure commissioning, updates, decommissioning incl. data removal | ✅ | SECURITY-HARDENING.md (checklist, updates, decommissioning), USER-GUIDE "Security After Installation" |
| How to switch off automatic updates | ➖ | There are no automatic updates; the daily update check only notifies |
| SBOM (if provided to users) | ✅ | `sbom/`, release assets; installation SBOM in the backend (System info → Components (SBOM)) |

## 5. Organisational obligations (depending on the role)

| Obligation | Applies to | Status | Notes |
|------------|------------|--------|-------|
| Cybersecurity policy for vulnerability handling (Art. 24(1)) | Steward | ✅ | SECURITY.md + [SECURITY-PROCESS.md](SECURITY-PROCESS.md) |
| Reporting of actively exploited vulnerabilities and severe incidents to CSIRT/ENISA (24 h / 72 h / final report) (Art. 14) | Manufacturer; steward to the extent involved | 🟡 | Described in SECURITY-PROCESS.md; register with the ENISA single reporting platform once it applies to the project |
| Support period of at least 5 years unless the expected use is shorter (Art. 13(8)) | Manufacturer | 🟡 | Current policy: only the latest minor line of the current major — would have to be extended and published for a commercial distribution |
| Technical documentation (Annex VII), conformity assessment (module A), EU declaration of conformity, CE marking | Manufacturer | ❌ | Not prepared; only needed for a commercial distribution |
| Report vulnerabilities in third-party components to their maintainers (Art. 13(6)) | Manufacturer | ✅ | Step in SECURITY-PROCESS.md (triage) |
| Due diligence for third-party components (Art. 13(5)) | Manufacturer / integrator | ✅ | Dependabot, `composer audit` / `npm audit`, SBOM |

## 6. Open items

Repository settings (GitHub, to be changed by the owner):

1. **Private vulnerability reporting** — Settings → Code security → "Private
   vulnerability reporting" → Enable. Requires the repository to be public (it
   is currently private); until then only the e-mail fallback works.
2. **Dependabot alerts and security updates** — Settings → Code security →
   enable both (currently disabled). `.github/dependabot.yml` then also opens
   the weekly version updates.
3. **Branch protection** for `master` (require the CI checks) — requires a
   public repository or a paid plan.
4. Optional: secret scanning and push protection, CodeQL (code scanning).

Product / process:

5. Short threat model / risk assessment (Annex I (1), Annex VII (3)).
6. Security setting "require 2FA" without per-user opt-out (at least for
   admins); admin action to reset a user's second factor.
7. Decide whether automatic security updates are applicable (Annex I (2)(c)).
8. Cover the cookie-consent plugin's custom scripts by `CMS_CODE_EDITING`
   (attack surface, Annex I (2)(j)).
9. Signed tags or GitHub artifact attestations for release archives.
10. Hard-delete personal data when users delete their own account, or document
    the trash behaviour.
11. Decide on the role (no obligations / steward / manufacturer) for every way
    Inverys CMS is distributed, and extend the support period and documentation
    accordingly.
