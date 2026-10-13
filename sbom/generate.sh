#!/usr/bin/env bash
# Regenerate the Software Bill of Materials (CycloneDX 1.5) for both dependency
# ecosystems. Run from the project root: bash sbom/generate.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "Generating Composer SBOM -> sbom/composer.cdx.json"
php sbom/generate-composer-sbom.php > sbom/composer.cdx.json

echo "Generating npm SBOM -> sbom/npm.cdx.json"
npm sbom --sbom-format cyclonedx --sbom-type application > sbom/npm.cdx.json

echo "Done."
