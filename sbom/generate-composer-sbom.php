<?php

/**
 * Generate a CycloneDX 1.5 SBOM for the PHP side of Inverys CMS:
 *
 *  - every Composer package from composer.lock (runtime = scope "required",
 *    dev = scope "optional") with purl, licenses, authors, VCS/distribution
 *    references and the pinned commit,
 *  - the plugins and themes bundled in this repository (plugins/, themes/),
 *  - the dependency graph (root → direct requirements → transitive ones).
 *
 * No external tooling required. The document itself is built by
 * app/Support/Sbom/CycloneDx.php — the same builder the backend uses for the
 * installation SBOM (System info → Components), loaded directly so this
 * script also runs without Composer's autoloader.
 *
 * Usage:  php sbom/generate-composer-sbom.php > sbom/composer.cdx.json
 *
 * Set SOURCE_DATE_EPOCH to get a reproducible timestamp (e.g. the release
 * commit time); otherwise the current time is used.
 */

use App\Support\Sbom\CycloneDx;

$root = dirname(__DIR__);
require_once $root.'/app/Support/Sbom/CycloneDx.php';

$lock = json_decode((string) @file_get_contents($root.'/composer.lock'), true);
if (! is_array($lock)) {
    fwrite(STDERR, "composer.lock not readable\n");
    exit(1);
}

// Root project version from config/cms.php
$version = '0.0.0';
if (preg_match("/'version'\\s*=>\\s*'([^']+)'/", (string) @file_get_contents($root.'/config/cms.php'), $m)) {
    $version = $m[1];
}
$composerJson = json_decode((string) @file_get_contents($root.'/composer.json'), true) ?: [];
$rootLicenses = (array) ($composerJson['license'] ?? []);

$bom = new CycloneDx(
    $composerJson['name'] ?? 'inverys/cms',
    $version,
    $rootLicenses,
    $composerJson['description'] ?? null,
);

// ── Composer packages ────────────────────────────────────────
foreach ($lock['packages'] ?? [] as $pkg) {
    $bom->addComposerPackage($pkg, 'required');
}
foreach ($lock['packages-dev'] ?? [] as $pkg) {
    $bom->addComposerPackage($pkg, 'optional');
}

// ── Bundled plugins and themes ───────────────────────────────
// Only what is tracked in git (a locally uploaded plugin is not part of the
// release); falls back to the file system outside a git checkout.
$manifests = [];
$gitList = @shell_exec('cd '.escapeshellarg($root).' && git ls-files -- "plugins/*/plugin.json" "themes/*/theme.json" 2>/dev/null');
if (is_string($gitList) && trim($gitList) !== '') {
    $manifests = array_filter(array_map('trim', explode("\n", $gitList)));
} else {
    $manifests = array_map(
        fn ($path) => substr($path, strlen($root) + 1),
        array_merge(glob($root.'/plugins/*/plugin.json') ?: [], glob($root.'/themes/*/theme.json') ?: [])
    );
}

foreach ($manifests as $relative) {
    $manifest = json_decode((string) @file_get_contents($root.'/'.$relative), true);
    if (! is_array($manifest)) {
        continue;
    }

    // Bundled code is covered by the repository licence unless the manifest says otherwise
    $bom->addExtension(
        str_starts_with($relative, 'themes/') ? 'theme' : 'plugin',
        $manifest,
        dirname($relative),
        'bundled',
        $rootLicenses,
    );
}

// ── Document ─────────────────────────────────────────────────
$epoch = getenv('SOURCE_DATE_EPOCH');

echo CycloneDx::encode($bom->build(array_keys((array) ($composerJson['require'] ?? [])), [
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z', is_string($epoch) && ctype_digit($epoch) ? (int) $epoch : time()),
]));
