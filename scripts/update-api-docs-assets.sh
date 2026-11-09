#!/usr/bin/env bash
# Update the self-hosted assets of the API docs page (/docs/api, #1217).
# Usage: scripts/update-api-docs-assets.sh <stoplight-elements version> <fontawesome-svg-core version>
#
# Scramble's view loads Stoplight Elements from unpkg.com, which the strict CSP blocks (ADR 0007), so
# resources/views/vendor/scramble/docs.blade.php loads these copies from public/vendor instead.
# The Font Awesome version must be the one bundled in Stoplight Elements (see web-components.min.js.LICENSE.txt).
# Update the versions in docs.blade.php after a run, and delete the folders of the old versions.

set -euo pipefail

stoplight_version="${1:?Stoplight Elements version, for example 8.4.2}"
fontawesome_version="${2:?Font Awesome svg-core version, for example 6.1.1}"
root="$(git rev-parse --show-toplevel)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

cd "$work"
npm pack "@stoplight/elements@${stoplight_version}" "@fortawesome/fontawesome-svg-core@${fontawesome_version}" --json >packs.json
mkdir stoplight fontawesome
tar xzf "stoplight-elements-${stoplight_version}.tgz" -C stoplight
tar xzf "fortawesome-fontawesome-svg-core-${fontawesome_version}.tgz" -C fontawesome

stoplight_target="${root}/public/vendor/stoplight-elements/${stoplight_version}"
fontawesome_target="${root}/public/vendor/fontawesome-svg-core/${fontawesome_version}"
mkdir -p "$stoplight_target" "$fontawesome_target"
cp stoplight/package/{web-components.min.js,web-components.min.js.LICENSE.txt,styles.min.css,LICENSE} "$stoplight_target/"
cp fontawesome/package/{styles.css,LICENSE.txt} "$fontawesome_target/"

echo "Copied. npm integrity of the packages:"
node -e 'for (const p of JSON.parse(require("fs").readFileSync("packs.json","utf8"))) console.log(`  ${p.name}@${p.version}: ${p.integrity}`)'
echo "Bundled Font Awesome: $(grep -o 'Font Awesome Free [0-9.]*' "$stoplight_target/web-components.min.js.LICENSE.txt" | head -1)"
