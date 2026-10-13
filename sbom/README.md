# Software Bill of Materials (SBOM)

Machine-readable dependency inventories in [CycloneDX](https://cyclonedx.org/) 1.5 JSON,
provided to support supply-chain transparency and EU Cyber Resilience Act (CRA)
expectations.

| File | Ecosystem | Scope |
|------|-----------|-------|
| `composer.cdx.json` | PHP / Composer | Runtime dependencies (`scope: required`) + dev/build dependencies (`scope: optional`), the plugins and themes bundled in `plugins/` and `themes/`, and the dependency graph |
| `npm.cdx.json` | Node / npm | Build-time toolchain (Vite, Tailwind, etc.) — not shipped at runtime |

Each Composer component carries its purl, licences, authors, the VCS URL with
the pinned commit and the distribution URL. Composer does not record archive
hashes for GitHub zipballs, so integrity is anchored in the commit reference;
the npm SBOM contains the registry's SHA-512 integrity hashes.

Both files are attached to every [GitHub release](https://github.com/tklmn/inverys-cms/releases)
together with the source archive and `SHA256SUMS` (created by `bin/release.sh`).

The PHP/Composer packages are what runs in production; the npm packages are used
only to build front-end assets and are not part of the deployed artifact.

## Release SBOM vs. installation SBOM

The files in this folder describe the **release**: what `composer.lock` pins and the
plugins/themes tracked in git (`metadata.lifecycles` = `build`).

An installed site can differ — `composer install --no-dev`, uploaded plugins and
themes, Composer-installed extensions, a different PHP version. For that, the
backend offers an **installation SBOM**: *System → System Info → Components (SBOM)*
(permission `show-system`) lists what is actually installed and downloads a
CycloneDX 1.5 JSON file generated on the fly
(`inverys-cms-{version}-{host}-sbom.cdx.json`, `metadata.lifecycles` = `operations`).

| | Release SBOM (`sbom/composer.cdx.json`) | Installation SBOM (backend) |
|---|---|---|
| Composer source | `composer.lock` | `vendor/composer/installed.json` |
| Dev packages | always (`scope: optional`) | only if installed (`scope: optional`) |
| Plugins / themes | tracked in git, `inverys:type` / `inverys:path` | everything found on disk or via Composer, plus `inverys:enabled` / `inverys:active` |
| PHP | — | `type: platform` component with CPE and loaded extensions |
| npm | `npm.cdx.json` | not included (build tooling only; the release file can be downloaded from the same page) |

Both are built by the same class, `app/Support/Sbom/CycloneDx.php`;
`generate-composer-sbom.php` loads it directly, so it runs without Composer's
autoloader.

## Regenerating

Run after any dependency change (`composer update` / `npm install`) or release:

```bash
bash sbom/generate.sh
```

This runs `php sbom/generate-composer-sbom.php` (reads `composer.lock`,
`composer.json` and the bundled `plugin.json` / `theme.json` manifests) and
`npm sbom --sbom-format cyclonedx` (reads `package-lock.json`). Set
`SOURCE_DATE_EPOCH` for a reproducible timestamp.

## Verifying there are no known vulnerabilities

```bash
composer audit      # PHP advisories
npm audit           # npm advisories
```
