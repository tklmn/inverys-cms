# Security Hardening Guide

Inverys CMS is secure by default when it is installed with the web installer on
HTTPS. This guide covers what the **operator** of a site should check before
going live and while running it. For reporting vulnerabilities see
[SECURITY.md](../SECURITY.md); for accounts after the installation see
[Security After Installation](USER-GUIDE.md#-security-after-installation).

## Checklist

| Area | Check | Default |
|------|-------|---------|
| Environment | `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error` | Written by the installer |
| HTTPS | Site reachable only via HTTPS, `APP_URL` starts with `https://` | Your web server |
| Cookies | `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` | Installer sets both for an `https://` site URL |
| Hosts | `APP_URL` is the real public address; extra domains in `TRUSTED_HOSTS` | Only the `APP_URL` host and its subdomains |
| Proxy | Behind a TLS-terminating proxy: `TRUSTED_PROXIES` set | No proxy trusted |
| Document root | Web server points to `public/` | — (root `.htaccess` protects Apache setups that do not) |
| Permissions | Code read-only for the web server user; only `storage/`, `bootstrap/cache/` writable | Your server |
| Accounts | No account with the demo password `password`; 2FA enabled | 2FA on for all users |
| Code in the backend | `CMS_CODE_EDITING=false` if changes come via Git/deployment only | On (admins only) |
| Registration | Settings → Security → *Allow registration* off if visitors need no accounts | On |
| Contact | Settings → Security → security.txt (or `SECURITY_TXT_CONTACT` in `.env`) → `/.well-known/security.txt` | Not served |
| Backups | Database, `storage/app/`, `.env` (incl. `APP_KEY`) backed up and restorable | — |
| Updates | Watching releases / security advisories; update process tested | — |

## Environment (`.env`)

```dotenv
APP_ENV=production
APP_DEBUG=false          # never true on a public site: error pages expose paths and configuration
APP_URL=https://www.example.com
LOG_LEVEL=error

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true   # cookies only over HTTPS
SESSION_SAME_SITE=lax        # default; "strict" also works if you do not need cross-site logins

TRUSTED_HOSTS=               # e.g. "cms.example.org" for an additional domain
TRUSTED_PROXIES=             # e.g. "10.0.0.0/8" behind a load balancer

SECURITY_TXT_CONTACT=mailto:security@example.com
SECURITY_TXT_POLICY=https://www.example.com/security
```

After changing `.env` on a server that uses `php artisan config:cache`, run it
again — cached configuration ignores `.env`.

### Host names (`TRUSTED_HOSTS`)

Requests are only accepted for the host in `APP_URL`, its subdomains (and the
bare domain if `APP_URL` starts with `www.`). Any other `Host` header is
rejected with **400**. This prevents *Host header injection*: without it, an
attacker could request a password reset for a victim with `Host: evil.example`
and the e-mail would contain a reset link to the attacker's server.

- More domains: `TRUSTED_HOSTS=cms.example.org,example.net` (comma-separated,
  without scheme and port).
- `TRUSTED_HOSTS=*` disables the check — only do this if your web server
  already rejects unknown hosts (no default/catch-all virtual host).
- Health checks of a load balancer that call the server by IP address
  (`Host: 10.0.0.5`) are rejected too — add that address to `TRUSTED_HOSTS` or
  let the check send the public host name.
- The check is inactive before the installation and with `APP_ENV=local`.

### Reverse proxy / load balancer (`TRUSTED_PROXIES`)

Behind a proxy that terminates TLS, the application sees plain HTTP from the
proxy. Set `TRUSTED_PROXIES` to the proxy's addresses (IPs or CIDR ranges,
comma-separated) so that `X-Forwarded-Proto/Host/For` are honoured: HTTPS links,
secure cookies, HSTS and the real client IP (rate limiting, activity log) then
work. `TRUSTED_PROXIES=*` trusts every client — use it only if the application
cannot be reached except through the proxy.

## HTTPS and headers

- Redirect all HTTP traffic to HTTPS in the web server.
- The application sends `Strict-Transport-Security: max-age=31536000;
  includeSubDomains` on HTTPS requests. Only keep `includeSubDomains` if **all**
  subdomains support HTTPS; otherwise override the header in the web server.
- A strict, nonce-based Content-Security-Policy and `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` are set by
  the application. Do not add a weaker CSP in the web server.
- Files under `public/storage` (uploads) are served by the web server directly.
  `storage/app/public/.htaccess` (Apache) adds `nosniff` and a sandbox for
  script-capable types. On **nginx**, add the equivalent:

```nginx
location ^~ /storage/ {
    add_header X-Content-Type-Options "nosniff" always;
    location ~* \.(svgz?|html?|xhtml|xml|xsl)$ {
        add_header Content-Security-Policy "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox" always;
        add_header X-Content-Type-Options "nosniff" always;
    }
    location ~* \.php$ { deny all; }
}
```

- Static files of themes and plugins are served by the application at
  `/ext-assets/{theme|plugin}/{slug}/{path}`: only from the package's `assets/`
  folder (no hidden files, no way out of it, not even via symlink), only known
  file types (CSS, JavaScript, fonts, images), with `nosniff`; SVGs get a
  sandbox CSP.

## Page output

These run on the finished HTML response, after theme and plugins, so they apply
to every theme and plugin:

- **Protect email addresses** (Settings → General, on by default) hides
  addresses in the page body from spam bots: `mailto:` links carry the address
  only encoded, addresses in text show the `@` through a style, and
  `public/js/email-protection.js` (same origin, allowed by the CSP) restores both
  in the browser. Signed-in users get the page unchanged. This keeps addresses
  away from harvesters; it does not make a published address secret.
- **Minify HTML** (Settings → General, on by default) runs on the server with the
  bundled library `matthiasmullie/minify` (MIT). It processes only the page text —
  it reads no files and contacts no service. The page builder preview is never
  minified.
- Every full HTML page, error pages included, starts with a copyright comment
  right after the doctype; themes cannot change or remove it.

## Web server

- **Document root = `public/`.** If your host cannot do that (shared hosting),
  keep the project's root `.htaccess` — it blocks access to `.env`, logs and
  source files. nginx ignores `.htaccess`, so there the root must be `public/`.
- Deny hidden files but allow `/.well-known/`:

```nginx
location ~ /\.(?!well-known/) { deny all; }
```

- Limit the request body to what you need (`client_max_body_size` /
  `LimitRequestBody`); the CMS accepts uploads of up to 10 MB (plugins/themes
  20 MB).
- PHP: `expose_php = Off`, `display_errors = Off`, `allow_url_include = Off`.

## File permissions

- The code (`app/`, `config/`, `plugins/`, `themes/`, `vendor/`, `public/`) should
  be owned by a deploy user and **read-only** for the web server user.
- Writable for the web server: `storage/` and `bootstrap/cache/`.
- `.env` readable only by the deploy user and the web server user (`chmod 640`).
- Exception: installing plugins/themes through the backend and the theme file
  editor need write access to `plugins/` and `themes/`; updating the CMS from
  the backend (System → Updates) replaces the code and needs write access to the
  project directory. If you deploy code via git/Composer only, keep these
  directories read-only (and set `CMS_CODE_EDITING=false`) — uploads, updates and
  the editor then fail safely.
- `public/storage` is a **relative** symlink to `storage/app/public` (created by
  the installer), so a moved installation keeps its media. If the link or the
  storage folders are missing — FTP clients often leave out symlinks and empty
  folders — the CMS recreates them on the next request, provided it may write
  there; an existing `public/storage` folder is left alone. With a read-only
  `public/`, create the link during deployment
  (`ln -s ../storage/app/public public/storage` in the project root).

## Installer

- After a successful installation the marker file `storage/installed` locks the
  web installer. If the file is lost (e.g. `storage/` is not persisted in a
  container) while the database still has users, the installer stays locked and
  the site answers with an error until you restore the file — the installer is
  never re-opened for anonymous visitors.
- Keep `storage/` on persistent storage and include `storage/installed` in your
  backups.

## Accounts and access

- Two-factor authentication is enabled for everybody by default (Settings →
  Security). Users can only opt out after entering a valid code; ask
  administrators to use TOTP (authenticator app) and to store their recovery
  codes safely.
- Review the password policy (Settings → Security; defaults: 10 characters,
  mixed case, numbers, symbols) and the login lockout.
- Give people the smallest role that works. The `editor` role manages content
  only.
- Code on the server is reserved for administrators (`App\Support\CodeAccess`):
  - `edit-theme-files` (the theme editor edits Blade templates, i.e. PHP),
    `manage-plugins` (plugin packages, cookie-consent scripts) and
    `manage-updates` (System → Updates) work only for the `admin` role and can
    only be granted to it,
  - uploading and deleting themes/plugins and updating the CMS from the backend
    need the `admin` role,
  - the theme editor and the plugin page ask for the password again,
  - `CMS_CODE_EDITING=false` switches all of it off on a server.
- Also powerful: `manage-themes` (activate/customize themes), `edit-settings`,
  `manage-security-settings`, `mail-providers-settings`.
- Users with `edit-user` cannot change or delete accounts that have permissions
  they do not have themselves.
- Changing one's own email address needs the current password and a
  confirmation link sent to the new address; the old address gets a notice with
  an undo link (7 days) that also locks the password.
- Remove demo accounts (`editor@example.com`, `user@example.com`,
  `blocked@example.com`) if they were ever seeded.
- New registrations are created **inactive** and need to be activated by an
  administrator.
- API tokens: create them with the least ability (read-only where possible) and
  an expiry date; revoke unused tokens under API Tokens.

## `APP_KEY` and encrypted data

`APP_KEY` encrypts sessions and cookies **and data stored in the database**:

- mail provider credentials (SMTP password, OAuth client secrets and tokens),
- captcha secret keys,
- TOTP secrets and recovery codes of every user with an authenticator app.

Consequences:

- **Back up `APP_KEY` together with the database.** A database backup without
  the matching key cannot decrypt these values.
- **Rotating the key** (e.g. after a leak of `.env`):
  1. Put the old key into `APP_PREVIOUS_KEYS` and generate a new `APP_KEY`
     (`php artisan key:generate --show`, then edit `.env`). Laravel keeps
     decrypting old values with the previous key.
  2. All sessions and "remember me" cookies become invalid — everybody has to
     log in again.
  3. Re-save the mail provider and captcha settings (so they are encrypted with
     the new key). TOTP secrets and recovery codes stay readable through
     `APP_PREVIOUS_KEYS`; ask TOTP users to set up their authenticator again
     (profile → Two-factor) so their data is encrypted with the new key.
  4. Remove `APP_PREVIOUS_KEYS` once nothing depends on the old key.
- If you change `APP_KEY` **without** `APP_PREVIOUS_KEYS`, the encrypted
  settings are treated as "not configured" (mail sending and captcha stop
  working until you re-enter them), and users with an authenticator app can no
  longer complete the login — neither their TOTP secret nor their recovery
  codes can be decrypted. Reset their second factor on the server (set
  `two_factor_method`, `two_factor_secret`, `two_factor_recovery_codes` and
  `two_factor_confirmed_at` to `NULL`, e.g. with `php artisan tinker`); they
  then receive e-mail codes and can set up TOTP again.
- After a suspected leak also change the database password, the mail provider
  credentials and the captcha secrets at their providers.

## Backups

Back up regularly and test a restore:

- the database,
- `storage/app/` (uploads, form submission files) and `storage/installed`,
- `.env` (contains `APP_KEY` and credentials — store it encrypted and
  separately from the database dump; copies such as `.env.backup` or
  `.env.*.backup` are ignored by Git and never part of a release package, but
  keep them out of the project directory anyway),
- your own plugins/themes if they are not in version control.

## Updates

- Watch the repository's **releases** and **security advisories** (GitHub:
  Watch → Custom → Releases, Security alerts). Security fixes are released as
  patch versions; see the support table in [SECURITY.md](../SECURITY.md).
- The CMS checks for new versions of itself, its plugins and themes daily
  (`cms:check-updates`, needs the Laravel scheduler) and notifies users with
  `manage-updates`. It contacts only GitHub (`CMS_UPDATE_REPOSITORY`, default
  `tklmn/inverys-cms`; `CMS_UPDATE_TOKEN` for a private repository).
- Verify a downloaded release with the attached `SHA256SUMS`
  (`sha256sum -c SHA256SUMS`); the update package `inverys-cms-<version>.zip`
  has its own `.sha256`.
- **Update from the backend** (System → Updates, admins only, off with
  `CMS_CODE_EDITING=false`): *Update to …* downloads the update package and
  checks its SHA-256 checksum; *Upload CMS package* installs the same ZIP by hand.
  Before the files are swapped, the database and the replaced files are backed
  up and the site goes into maintenance mode; the next request runs the
  migrations — a failing migration is rolled back and the old files return.
  `.env`, `storage/`, themes and plugins that do not ship with the CMS, SQLite
  files and `public/storage` are never touched. Git checkouts are updated by
  hand as below.
- Update process by hand:
  1. Back up (see above) and read the release notes in the
     [CHANGELOG](../CHANGELOG.md).
  2. `php artisan down`
  3. Update the code (`git pull` / new release archive), then
     `composer install --no-dev --optimize-autoloader`,
     `npm ci && npm run build` (if you build assets on the server).
  4. `php artisan migrate --force`
  5. `php artisan optimize:clear` (and `php artisan config:cache` if you use it)
  6. `php artisan up`
- Keep PHP, the database server and the web server on supported versions with
  security updates.
- Run `composer audit` periodically on the server's `composer.lock` if you added
  your own packages.

## Logging, monitoring and privacy

- The activity-log plugin records logins, failed logins and changes to content,
  users, roles, permissions and settings (secrets are masked). Retention:
  Activity Log → Settings (default 90 days).
- HTTP errors (configurable status codes) appear as system notifications
  (Settings → Notifications) with URL, IP address and user agent; they are
  deleted after the notification retention period (Settings → Security).
- Application errors go to `storage/logs/`. Rotate or ship these logs; they may
  contain IP addresses and e-mail addresses.
- These records contain personal data (IP addresses). Keep the retention
  periods as short as your needs allow and mention them in your privacy policy.

## Decommissioning

To take a site down permanently and remove all data: delete the database (or
all its tables), `storage/` (uploads, logs, sessions, form submission files),
`.env` and all backups; revoke the mail provider and captcha credentials at the
providers and remove API tokens that external systems still use.
