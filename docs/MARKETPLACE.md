# 🏪 Marketplace

The marketplace installs themes and plugins from **catalogs**: JSON files that list packages with their version, download address and checksum. Anyone can run a marketplace by publishing such a file — on GitHub, GitLab, a web server or a company network. An Inverys installation shows the packages of all its **sources** under **Extensions > Themes > Marketplace** and **Extensions > Plugins > Marketplace**.

## 📑 Table of Contents

1. [Sources](#1--sources)
2. [Catalog Format](#2--catalog-format)
3. [Running Your Own Marketplace](#3--running-your-own-marketplace)
4. [Permissions](#4--permissions)
5. [Security](#5--security)

---

## 1. 🔗 Sources

A source is the https address of a catalog.

| Where | Setting | Notes |
|:------|:--------|:------|
| Server | `CMS_MARKETPLACE_SOURCES` | Comma-separated. Default: the catalog of the [Inverys marketplace](https://github.com/tklmn/inverys-marketplace). Shown as fixed in the backend. |
| Backend | **Marketplace > Sources** | More catalog addresses, one per line (administrators only). |
| Switch | `CMS_MARKETPLACE=false` | No marketplace at all: no tab, no sources, no requests. |

Catalogs are kept for six hours; **Refresh** fetches them again. If a catalog cannot be loaded, the last list stays and the page names the error. When two catalogs list the same slug, the source listed first wins.

---

## 2. 📄 Catalog Format

```json
{
    "schema": 1,
    "name": "Acme Marketplace",
    "packages": [
        {
            "type": "theme",
            "slug": "ember",
            "name": "Ember",
            "description": "A dark theme with warm amber accents.",
            "author": "Tom Kilimann",
            "version": "1.0.0",
            "requires": "^1.0",
            "license": "MIT",
            "download": "https://example.com/packages/ember-1.0.0.zip",
            "sha256": "5f2b…(64 hex characters)",
            "screenshot": "https://example.com/packages/ember.png",
            "homepage": "https://example.com/ember"
        }
    ]
}
```

| Field | Required | Description |
|:------|:--------:|:------------|
| `schema` | Yes | Always `1`. |
| `name` | No | Name of the marketplace, shown on every package ("from Acme Marketplace"). Default: the host name. |
| `packages[].type` | Yes | `theme` or `plugin`. |
| `packages[].slug` | Yes | Lowercase letters, digits and hyphens. Must match the `slug` in the package's `theme.json` / `plugin.json`. |
| `packages[].name` | Yes | Display name. |
| `packages[].version` | Yes | Semantic version (`1.2.0`). A higher version than the installed one offers an **Update**. |
| `packages[].download` | Yes | https address of the package ZIP. |
| `packages[].sha256` | Yes | SHA-256 checksum of exactly that ZIP (`sha256sum file.zip`). Without a match nothing is installed. |
| `packages[].requires` | No | Inverys CMS version: `^1.2` or `1.2` (same major version, at least 1.2), `>=1.2` (at least 1.2). Not met: shown, but not installable. |
| `packages[].description`, `author`, `license` | No | Plain text (markup is removed). |
| `packages[].screenshot` | No | https address of a PNG, JPEG or WebP preview (at most 2 MB; 800 × 450 recommended). The CMS stores it locally. |
| `packages[].homepage` | No | https address of the project page. |

Entries that do not follow the format are left out; the rest of the catalog still works. A catalog may be at most 1 MB.

### The package ZIP

The same ZIP as for an upload: `theme.json` or `plugin.json` at the root or one folder deep (e.g. `ember/theme.json`). It goes through the same checks — blocked file types, path traversal, valid slug — see [THEME-DEVELOPMENT.md](THEME-DEVELOPMENT.md#12--distribution) and [PLUGIN-DEVELOPMENT.md](PLUGIN-DEVELOPMENT.md#20--distribution).

> **⚠️ Warning:** Do not point `download` at GitHub's automatic "Source code" archives: they are generated on request and their checksum is not guaranteed to stay the same. Attach a built ZIP to the release instead (the Ember and Announcement Bar repositories do this with a workflow).

---

## 3. 🛠️ Running Your Own Marketplace

1. Build a ZIP per package version and upload it somewhere reachable over https (a release asset, a web server, object storage).
2. Note its checksum: `sha256sum my-theme-1.0.0.zip`.
3. Publish a `marketplace.json` in the format above — on any web server, or as a file in a public Git repository (raw address).
4. Add its address under **Marketplace > Sources**, or for every installation in `CMS_MARKETPLACE_SOURCES`.

For a new version, upload the new ZIP and change `version`, `download` and `sha256` in the catalog. The [Inverys marketplace](https://github.com/tklmn/inverys-marketplace) is an example: its catalog is generated from the releases of the listed repositories.

---

## 4. 🔐 Permissions

| Permission | Who | Allows |
|:-----------|:----|:-------|
| `install-themes` | any role | See the theme marketplace, install and update themes from it |
| `install-plugins` | any role | See the plugin marketplace, install and update plugins from it |
| `manage-marketplace` | administrators only | Add and remove sources |
| `manage-themes` | any role | Activate themes (also on the marketplace card) |
| `manage-plugins` | administrators only | Switch installed plugins on and off |

Installing and updating also need code changes to be allowed on the server (`CMS_CODE_EDITING`, on by default) and ask for the password again. A plugin installed by a role without `manage-plugins` stays switched off until an administrator switches it on.

> **💡 Tip:** The install permissions let e.g. a moderator pick from vetted sources. Choosing the sources stays with administrators, because a source decides which code can be installed at all.

---

## 5. 🛡️ Security

- **Checksum:** every download is compared with the `sha256` of the catalog before it is unpacked.
- **Package checks:** like an upload (`PackageInstaller`): blocked file types and names, no path traversal, manifest and slug must match the catalog.
- **Only the catalog:** a request can only name a slug; only packages listed in a source can be installed.
- **Public hosts only:** catalogs, packages and previews are fetched over https from public addresses only. The host is resolved once, the connection pinned to that address and every redirect checked again — the CMS cannot be used to reach its own network (`127.0.0.1`, `10.x`, `192.168.x`, cloud metadata). For a marketplace inside a company network, set `CMS_MARKETPLACE_ALLOW_PRIVATE_HOSTS=true`.
- **Previews:** PNG, JPEG or WebP only (no SVG), stored locally — the backend never loads images from foreign hosts.
- **Backups:** an update keeps the previous version (`storage/app/package-backups`) and can be restored from the list of installed packages.
