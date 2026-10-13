#!/bin/bash
#
# Checks the inline scripts of the rendered backend pages for syntax errors.
#
# Why a dedicated tool: a broken JS string literal in a Blade file is
# invisible to `php -l`. The script block then aborts in the browser and
# takes everything in the same block down with it — that is how the theme
# switcher once broke even though the page seemed to be perfectly
# fine.
#
# Requirement: logged-in session in $COOKIE_JAR.
#
#   BASE_URL=http://localhost/inverys/cms/public bin/lint-inline-js.sh
#
set -u

cd "$(dirname "$0")/.." || exit 1

BASE="${BASE_URL:-http://localhost/inverys/cms/public}"
JAR="${COOKIE_JAR:-/tmp/inverys-cookies.txt}"

# Page list from `php artisan route:list` (via check-widths.py), not maintained
# by hand: a fixed list would miss plugin pages, so a broken form builder on a
# plugin page would go unnoticed.
mapfile -t PAGES < <(PYTHONDONTWRITEBYTECODE=1 python3 - <<'PY_ROUTES'
import importlib.util
spec = importlib.util.spec_from_file_location('cw', 'bin/check-widths.py')
cw = importlib.util.module_from_spec(spec)
spec.loader.exec_module(cw)
for route in cw.routes():
    print(route)
PY_ROUTES
)

if [ "${#PAGES[@]}" -eq 0 ]; then
    echo "No pages found." >&2
    exit 2
fi

if [ ! -f "$JAR" ]; then
    echo "No session in $JAR — please log in first." >&2
    exit 2
fi

fail=0

for page in "${PAGES[@]}"; do
    body=$(curl -s -b "$JAR" "$BASE$page")

    out=$(printf '%s' "$body" | python3 - "$page" <<'PY'
import os
import re
import subprocess
import sys
import tempfile

page = sys.argv[1]
html = sys.stdin.read()
problems = 0

# Data blocks (type="application/json") are not JavaScript
for index, block in enumerate(re.findall(r'<script(?![^>]*\b(?:src=|type="application/(?:ld\+)?json"))[^>]*>(.*?)</script>', html, re.S)):
    if not block.strip():
        continue

    with tempfile.NamedTemporaryFile('w', suffix='.js', delete=False, encoding='utf-8') as handle:
        handle.write(block)
        path = handle.name

    result = subprocess.run(['node', '--check', path], capture_output=True, text=True)
    os.unlink(path)

    if result.returncode != 0:
        problems += 1
        message = next((l for l in result.stderr.splitlines() if 'Error' in l), '')
        context = next((l for l in result.stderr.splitlines() if l.strip() and 'Error' not in l), '')
        print(f'{page} (Block {index}): {message}')
        print(f'    {context.strip()[:160]}')

sys.exit(1 if problems else 0)
PY
)

    if [ -n "$out" ]; then
        echo "$out"
        fail=$((fail + 1))
    fi
done

echo '----'
echo "${#PAGES[@]} pages checked."
if [ "$fail" -eq 0 ]; then
    echo 'All inline scripts are error-free.'
else
    echo "$fail page(s) with broken inline scripts."
fi

exit "$fail"
