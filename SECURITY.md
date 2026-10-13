# Security Policy

## Supported Versions

Security fixes are released as patch versions (`X.Y.Z`) of the latest minor line.
Updating within a major version is a drop-in upgrade (`composer install`,
`php artisan migrate`), so there are no back-ports to older minor lines.

| Version | Security fixes | Notes |
|---------|----------------|-------|
| 1.x     | ✅ Yes | Current release line |

### Support period

- Only the **latest minor version** of the current major version receives
  security fixes. There are no back-ports to older lines.
- Changes to this policy are announced in the [CHANGELOG](CHANGELOG.md).

## Reporting a Vulnerability

**Please do not open a public issue, pull request or discussion for security
vulnerabilities.**

Report privately through GitHub's coordinated disclosure workflow:

1. Go to the repository's **Security** tab → **Report a vulnerability**
   (GitHub Private Vulnerability Reporting).
2. Describe the issue with enough detail to reproduce it:
   - affected version / commit and configuration (plugins, theme, PHP version),
   - impact and attack scenario (who can exploit it, what they gain),
   - reproduction steps or a proof of concept,
   - any suggested remediation.

If private reporting is not available to you, contact the repository owner
privately through GitHub and ask for a secure channel — do not include details
of the vulnerability in the first message.

This policy covers the Inverys CMS software. To report a vulnerability in a
**website** that runs Inverys CMS, contact its operator — many sites publish a
contact in `/.well-known/security.txt`.

### What to expect

| Step | Target |
|------|--------|
| Acknowledgement of your report | within **5 business days** |
| Initial assessment (valid? severity? affected versions?) | within **10 business days** |
| Fix released — critical | within **14 days** of the assessment |
| Fix released — high | within **30 days** |
| Fix released — medium | within **90 days** |
| Fix released — low | with the next regular release |

Severity is rated with [CVSS v3.1/v4.0](https://www.first.org/cvss/). We keep
you informed about the progress, agree on a disclosure date with you and credit
you in the advisory unless you prefer to stay anonymous.

### Coordinated disclosure

- We ask you to keep the issue confidential until a fix is released, and at
  most **90 days** after your report unless we agree otherwise.
- Fixed vulnerabilities are published as
  [GitHub Security Advisories](https://github.com/tklmn/inverys-cms/security/advisories)
  with affected and fixed versions; a CVE ID is requested through GitHub for
  every vulnerability of medium severity or higher.
- The CHANGELOG lists security fixes in a `### Security` section.
- If a vulnerability is actively exploited, we release a fix or mitigation as
  fast as possible and may publish the advisory before the 90 days are up.

The internal handling process is documented in
[docs/SECURITY-PROCESS.md](docs/SECURITY-PROCESS.md).

## Scope

In scope: the CMS core (`app/`, `routes/`, `config/`, `bootstrap/`, `resources/`),
the web installer, the bundled plugins (`plugins/`) and themes (`themes/`), the
REST API, the marketplace, and the first-party packages it offers
(`inverys/ember-theme`, `inverys/announcement-bar`).

Out of scope:

- vulnerabilities in third-party dependencies that already have a public
  advisory (run `composer audit` / `npm audit`) — unless Inverys CMS uses the
  dependency in a way that makes it exploitable,
- issues that require physical or privileged server access,
- features that are powerful by design for trusted administrators (installing
  plugins/themes, the theme file editor, custom scripts in the cookie-consent
  plugin) — see [docs/SECURITY-HARDENING.md](docs/SECURITY-HARDENING.md),
- self-inflicted misconfiguration (e.g. `APP_DEBUG=true` or a default-password
  demo account on a public site).

## Built-in Security Controls

Inverys CMS ships with secure-by-default controls, most of which are
admin-configurable:

- **Content-Security-Policy** — strict, nonce-based (`script-src 'self' 'nonce-…'`,
  no `unsafe-inline`/`unsafe-eval`); applies to every served page including the
  pre-boot installer.
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy`, and HSTS on HTTPS.
- **Host header validation** — only the `APP_URL` host (plus `TRUSTED_HOSTS`) is
  accepted, so absolute links (password reset, sitemap) cannot be poisoned.
- XSS and CSS sanitisation (HTMLPurifier), CSRF protection, permission middleware
  (RBAC), configurable password policy, rate limiting (login, 2FA, password
  checks, password reset, forms, API), session timeout, encrypted sessions.
- Two-factor authentication (email codes or TOTP, with recovery codes), enabled
  for all users by default (a user can opt out in the profile only after
  entering a valid code).
- Secrets in settings (mail provider credentials, captcha secret keys) are stored
  encrypted with `APP_KEY` and never rendered back into forms.
- No hard-coded credentials on the web-installer path; demo users are skipped on
  production installs. The installer writes `APP_DEBUG=false` /
  `APP_ENV=production` and stays locked once the database is installed. See the
  [Security After Installation](docs/USER-GUIDE.md#-security-after-installation)
  guide and the [hardening guide](docs/SECURITY-HARDENING.md).
- `/.well-known/security.txt` (RFC 9116) for the site operator's contact
  (Settings → Security → security.txt, or `SECURITY_TXT_CONTACT`).

## Supply Chain / SBOM

A CycloneDX Software Bill of Materials for both dependency ecosystems is published
under [`sbom/`](sbom/) and attached to every
[GitHub release](https://github.com/tklmn/inverys-cms/releases), together with a
source archive and `SHA256SUMS`. It is regenerated on every release
(`bash sbom/generate.sh`). CI runs `composer audit` and `npm audit` on every push
and pull request, releases are blocked while an advisory is open, and Dependabot
watches Composer, npm and GitHub Actions dependencies.

For the EU Cyber Resilience Act status of the project see
[docs/CRA-READINESS.md](docs/CRA-READINESS.md).
