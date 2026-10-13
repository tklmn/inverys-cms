#!/usr/bin/env python3
"""
Requests EVERY reachable backend page and checks in the delivered HTML
whether the content area uses the full width.

The page list comes from `php artisan route:list`, not from a maintained
list — otherwise exactly the pages nobody thought of would be missing.
Placeholders are filled with a real ID from the database.

Why a separate test: a 200 status code says nothing about whether a page
looks right. Views can render without errors and still sit at half
width.

    python3 bin/check-widths.py              # report
    python3 bin/check-widths.py --verbose    # also the checked pages
"""

from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from html.parser import HTMLParser

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASE = os.environ.get('BASE_URL', 'http://localhost/inverys/cms/public')
JAR = os.environ.get('COOKIE_JAR', '/tmp/inverys-cookies.txt')

# Width limits that have no business in the content area
LIMIT = re.compile(r'\b(mx-auto|max-w-(?:sm|md|lg|xl|2xl|3xl|4xl|5xl|6xl|7xl|prose))\b')

# Blade component that was not compiled and sits raw in the HTML — e.g.
# because an @if was inside the component tag. No PHP error, no status code
# reveals it; you only notice that a toggle or button is missing.
RAW_COMPONENT = re.compile(r'<x-[a-z][\w.:-]*')

# Dialogs may be limited — their subtrees are skipped.
# A regex is not enough for that: it stops at the first </div> and leaves the
# rest of a nested dialog behind. Hence a real parser.
DIALOG_MARKERS = ('ui-panel', 'fixed inset-0')

# Routes that trigger or download something instead of showing a page —
# and the editor preview (canvas): it shows the website with its own widths
SKIP = re.compile(
    r'(?:^|/)(?:download|export|clear|destroy|restore|toggle|preview|canvas|data|api|'
    r'feed|logout|impersonate|duplicate|activate|deactivate)(?:/|$)'
)


class ContentOnly(HTMLParser):
    """Collects the class attributes of the content, excluding dialog subtrees."""

    VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr'}

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.classes: list[str] = []
        self._depth = 0
        self._skip_until: int | None = None

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag in self.VOID:
            if self._skip_until is None:
                self._collect(attrs)
            return

        self._depth += 1

        if self._skip_until is not None:
            return

        values = dict(attrs)
        css = values.get('class') or ''

        is_dialog = (
            values.get('role') == 'dialog'
            or values.get('aria-modal') == 'true'
            or any(marker in css for marker in DIALOG_MARKERS)
        )

        if is_dialog:
            self._skip_until = self._depth
            return

        self._collect(attrs)

    def handle_endtag(self, tag: str) -> None:
        if tag in self.VOID:
            return

        if self._skip_until is not None and self._depth == self._skip_until:
            self._skip_until = None

        self._depth = max(0, self._depth - 1)

    def _collect(self, attrs: list[tuple[str, str | None]]) -> None:
        css = dict(attrs).get('class')
        if css:
            self.classes.append(css)


def artisan(*args: str) -> str:
    return subprocess.run(
        ['php', 'artisan', *args],
        cwd=ROOT,
        capture_output=True,
        text=True,
    ).stdout


def sample_ids() -> dict[str, str]:
    """One real ID per placeholder so detail pages do not come up empty."""
    php = (
        'echo json_encode(['
        '"page" => optional(App\\Models\\Page::first())->id,'
        '"user" => optional(App\\Models\\User::first())->id,'
        '"role" => optional(App\\Models\\Role::first())->id,'
        '"permission" => optional(App\\Models\\Permission::first())->id,'
        '"media" => optional(App\\Models\\Media::first())->id,'
        '"setting" => optional(App\\Models\\Setting::first())->id,'
        ']);'
    )
    out = artisan('tinker', '--execute', php).strip().splitlines()

    for line in reversed(out):
        line = line.strip()
        if line.startswith('{'):
            return {k: str(v) for k, v in json.loads(line).items() if v}

    return {}


def routes() -> list[str]:
    raw = artisan('route:list', '--json')

    try:
        data = json.loads(raw[raw.index('['):])
    except (ValueError, json.JSONDecodeError):
        print('route:list could not be read.', file=sys.stderr)
        return []

    ids = sample_ids()
    fallback = '1'
    found = []

    for entry in data:
        if 'GET' not in (entry.get('method') or ''):
            continue

        uri = entry.get('uri') or ''

        if not uri.startswith('backend'):
            continue
        if SKIP.search(uri):
            continue
        if '?' in uri:
            continue

        # Fill placeholders; simply drop optional ones
        uri = re.sub(r'\{(\w+)\?\}', '', uri)

        def fill(match: re.Match) -> str:
            name = match.group(1).lower()

            # Themes are addressed by their slug, not by an ID
            if name in ('slug', 'theme'):
                return 'default'

            for key, value in ids.items():
                if key in name:
                    return value

            return fallback

        uri = re.sub(r'\{(\w+)\}', fill, uri)
        uri = re.sub(r'/+$', '', uri)

        found.append('/' + uri)

    return sorted(set(found))


def fetch(path: str) -> tuple[str, str]:
    result = subprocess.run(
        ['curl', '-s', '-b', JAR, '-w', '\n%{http_code}', BASE + path],
        capture_output=True,
        text=True,
    )
    body, _, code = result.stdout.rpartition('\n')

    return body, code.strip()


def main() -> int:
    verbose = '--verbose' in sys.argv

    if not os.path.exists(JAR):
        print(f'No session in {JAR} — please log in first.', file=sys.stderr)
        return 2

    pages = routes()

    if not pages:
        print('No pages found.', file=sys.stderr)
        return 2

    checked = 0
    skipped: list[tuple[str, str]] = []
    problems: list[tuple[str, list[str]]] = []
    raw: list[tuple[str, list[str]]] = []

    for page in pages:
        html, code = fetch(page)

        if code != '200':
            skipped.append((page, code))
            continue

        part = re.search(r'<main\b.*?</main>', html, re.S)

        if not part:
            skipped.append((page, 'no <main>'))
            continue

        checked += 1

        leftovers = RAW_COMPONENT.findall(html)
        if leftovers:
            raw.append((page, sorted(set(leftovers))))

        parser = ContentOnly()
        parser.feed(part.group(0))
        hits = LIMIT.findall(' '.join(parser.classes))

        if hits:
            problems.append((page, sorted(set(hits))))
        elif verbose:
            print(f'  ok  {page}')

    for page, hits in problems:
        print(f'NARROW  {page} — {", ".join(hits)}')

    for page, tags in raw:
        print(f'RAW  {page} — not compiled: {", ".join(tags)}')

    if skipped and verbose:
        print()
        for page, why in skipped:
            print(f'  skipped {page} ({why})')

    print('----')
    print(f'{len(pages)} routes found, {checked} pages measured, '
          f'{len(problems)} with limited content, {len(raw)} with raw <x-…> tags, '
          f'{len(skipped)} unreachable')

    return 1 if problems or raw else 0


if __name__ == '__main__':
    raise SystemExit(main())
