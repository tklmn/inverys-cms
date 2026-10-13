#!/usr/bin/env bash
#
# Cut a release: bump the version everywhere, regenerate the SBOM, run the test
# suite and the dependency audits, then commit + tag + create the GitHub release
# from the CHANGELOG — with the source archive, both SBOMs and SHA256SUMS
# attached as release assets.
#
# Usage:
#   1. Add a "## [X.Y.Z] - YYYY-MM-DD" section to the top of CHANGELOG.md.
#   2. bash bin/release.sh X.Y.Z
#
# Preconditions: run on master; the working tree must be clean except for the
# CHANGELOG.md entry you just wrote. Tags/releases use NO "v" prefix.
set -euo pipefail

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
    echo "usage: bin/release.sh X.Y.Z" >&2
    exit 1
fi
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "error: version must be semver X.Y.Z (no 'v' prefix), got: $VERSION" >&2
    exit 1
fi

cd "$(dirname "$0")/.."

# ── Preconditions ────────────────────────────────────────────
branch=$(git rev-parse --abbrev-ref HEAD)
if [[ "$branch" != "master" ]]; then
    echo "error: releases are cut from master (on '$branch')" >&2
    exit 1
fi

# Only CHANGELOG.md may be dirty (the entry you just wrote).
dirty=$(git status --porcelain | awk '{print $2}' | grep -v '^CHANGELOG.md$' || true)
if [[ -n "$dirty" ]]; then
    echo "error: working tree has uncommitted changes besides CHANGELOG.md:" >&2
    echo "$dirty" >&2
    exit 1
fi

if ! grep -q "^## \[${VERSION}\]" CHANGELOG.md; then
    echo "error: CHANGELOG.md has no '## [${VERSION}]' section — add it first" >&2
    exit 1
fi

if git rev-parse -q --verify "refs/tags/${VERSION}" >/dev/null; then
    echo "error: tag ${VERSION} already exists" >&2
    exit 1
fi

echo "==> Releasing ${VERSION}"

# ── Bump version (surgical, no reformatting) ─────────────────
bump() { # $1=file  $2=php|json
    php -r '
        [$_, $file, $type, $version] = $argv;
        $s = file_get_contents($file);
        $re = $type === "php"
            ? "/(\047version\047\s*=>\s*\047)[^\047]*(\047)/"
            : "/(\"version\"\s*:\s*\")[^\"]*(\")/";
        $s = preg_replace($re, "\${1}".$version."\${2}", $s, 1, $n);
        if ($n !== 1) { fwrite(STDERR, "  version bump failed in $file\n"); exit(1); }
        file_put_contents($file, $s);
    ' "$1" "$2" "$VERSION"
    echo "    bumped $1"
}
bump config/cms.php php
bump package.json json

# ── Regenerate SBOM (picks up the new root version) ──────────
echo "==> Regenerating SBOM"
bash sbom/generate.sh

sbom_ver=$(php -r '$d=json_decode(file_get_contents("sbom/composer.cdx.json"),true); echo $d["metadata"]["component"]["version"];')
if [[ "$sbom_ver" != "$VERSION" ]]; then
    echo "error: SBOM root version ($sbom_ver) != $VERSION" >&2
    exit 1
fi

# ── Gate: English only outside translation files ─────────────
echo "==> Checking for German text"
python3 bin/check-german.py

# ── Gate: full test suite ────────────────────────────────────
echo "==> Running test suite"
php artisan test

# ── Gate: no known vulnerabilities in the dependency tree ────
# CRA Annex I: products are placed on the market without known exploitable
# vulnerabilities — a release with an open advisory needs a conscious decision.
echo "==> Checking dependency advisories"
composer audit --no-interaction
npm audit --audit-level=high

# ── Commit, tag, release ─────────────────────────────────────
notes=$(awk -v v="$VERSION" '
    $0 ~ "^## \\[" v "\\]" {f=1; next}
    /^## \[/ {f=0}
    f {print}
' CHANGELOG.md | sed '1{/^$/d}')

git add CHANGELOG.md config/cms.php package.json sbom/composer.cdx.json sbom/npm.cdx.json
git commit -m "chore(release): v${VERSION}"
git push origin master

# ── Release assets: source archive, SBOMs, checksums ─────────
# Attached to the GitHub release so users can verify what they install
# (sha256sum -c SHA256SUMS) and feed the SBOM into their own tooling.
echo "==> Building release assets"
dist=$(mktemp -d)
trap 'rm -rf "$dist"' EXIT
git archive --format=tar.gz --prefix="inverys-cms-${VERSION}/" -o "${dist}/inverys-cms-${VERSION}.tar.gz" HEAD
cp sbom/composer.cdx.json "${dist}/inverys-cms-${VERSION}.composer.cdx.json"
cp sbom/npm.cdx.json "${dist}/inverys-cms-${VERSION}.npm.cdx.json"
(
    cd "$dist"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum inverys-cms-* > SHA256SUMS
    else
        shasum -a 256 inverys-cms-* > SHA256SUMS
    fi
)

# Tag has NO 'v' prefix (repo convention); release notes = CHANGELOG section.
gh release create "$VERSION" --title "$VERSION" --target master --notes "$notes" "$dist"/*

echo "==> Done: released ${VERSION}"
