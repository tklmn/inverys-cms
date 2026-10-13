#!/usr/bin/env python3
"""
Checks backend views against the rules in docs/DESIGN-SYSTEM.md.

Only backend views are checked: resources/views/backend/** and the plugins'
admin/settings views. Frontend views and themes are left out — the backend
tokens are not defined there.

Usage:
    python3 bin/audit-views.py            # report
    python3 bin/audit-views.py --strict   # exit code 1 on findings
"""

from __future__ import annotations

import glob
import os
import re
import sys
from collections import defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def backend_views() -> list[str]:
    """All views the backend design system applies to."""
    paths = glob.glob(os.path.join(ROOT, 'resources/views/backend/**/*.blade.php'), recursive=True)

    for p in glob.glob(os.path.join(ROOT, 'plugins/**/*.blade.php'), recursive=True):
        rel = os.path.relpath(p, ROOT).replace('\\', '/')
        # Admin interface only, never public output
        if '/views/frontend/' in rel:
            continue
        if re.search(r'/views/(fields|providers|blocks)/', rel):
            continue
        if re.search(r'/views/(banner|head-scripts|body-scripts)\.blade\.php$', rel):
            continue
        paths.append(p)

    return sorted(paths)


# (key, description, regex, page views only)
CHECKS = [
    ('width-own-cap',
     'Own width limit — views never set max-w-*',
     re.compile(r'class="[^"]*\bmax-w-(?:sm|md|lg|xl|2xl|3xl|4xl|5xl|6xl|7xl|prose)\b'),
     False),

    ('width-column-remainder',
     'lg:col-span-2 / w-1/2 on the card — card does not fill the row',
     re.compile(r'class="[^"]*\b(?:lg:col-span-2|w-1/2|w-2/3|w-1/3)\b[^"]*\bui-card\b|ui-card[^"]*\b(?:lg:col-span-2|w-1/2)\b'),
     False),

    ('width-own-page-spacing',
     'Own page margin (mx-auto / py-12) instead of --spacing-gutter',
     re.compile(r'class="[^"]*\bmx-auto\b[^"]*"|class="[^"]*\bpy-12\b[^"]*"'),
     False),

    ('scaffold-no-ui-page',
     'No <x-ui.page> as root',
     None,  # special check
     True),

    ('scaffold-old-wrapper',
     'Old wrapper (content-inner-wrap or p-6 wrapper around the card)',
     re.compile(r'content-inner-wrap|<div class="p-6[^"]*">\s*\n\s*<div class="flex'),
     False),

    ('grid-fixed-columns',
     'Fixed column grid in the form instead of ui-grid',
     re.compile(r'class="[^"]*grid\b[^"]*\b(?:md|sm|lg):grid-cols-[234]\b[^"]*"'),
     False),

    ('form-raw-field',
     'Input field without a ui class and without an x-ui component',
     re.compile(r'<(?:input|select|textarea)(?![^>]*\b(?:ui-input|ui-select|ui-textarea|ui-toggle-input|permission-checkbox)\b)(?![^>]*type="(?:hidden|checkbox|radio|file|color|range|submit|button)")[^>]*>'),
     False),

    ('form-own-flash',
     'Hand-written session message instead of x-ui.page / x-ui.flash',
     re.compile(r"@if\s*\(\s*session\('(?:success|error|warning|status)'\)"),
     False),

    ('form-old-select-toggle',
     'Select with only on/off instead of x-ui.toggle',
     re.compile(r'<option value="on"[^>]*>.*?<option value="off"', re.S),
     False),

    ('button-old-component',
     'x-btn instead of x-ui.button',
     re.compile(r'<x-btn\b'),
     False),

    ('button-text-in-list',
     'Labelled button in a list row (icons belong there)',
     None,  # special check: only actions INSIDE a list row
     False),

    ('token-raw-color',
     'Raw Tailwind color instead of a token',
     re.compile(r'\b(?:bg|text|border)-(?:gray|slate|zinc|neutral|stone|blue|green|red|yellow|amber|indigo|purple|pink|orange|emerald|rose|teal|cyan)-\d00\b'),
     False),

    ('token-dark-variant',
     'dark: variant in the markup — tokens handle that',
     re.compile(r'\bdark:[a-z]'),
     False),

    ('list-pseudo-table',
     'Div imitation of a table',
     re.compile(r'role="table"'),
     False),

    ('icon-bootstrap',
     'Bootstrap icon instead of x-ui.icon',
     re.compile(r'<i class="bi\b'),
     False),

    ('a11y-icon-without-label',
     'Icon button without :label (no tooltip, no screen reader text)',
     re.compile(r'<x-ui\.button(?![^>]*:label)(?![^>]*\bdisabled\b)[^>]*\bicon="[^"]+"[^>]*/>'),
     False),
]


def is_page_view(path: str, body: str) -> bool:
    """A page view renders the layout; partials and components do not."""
    return '<x-app-layout>' in body


def audit(path: str) -> list[tuple[str, str, int]]:
    body = open(path, encoding='utf-8').read()
    findings = []

    for key, label, pattern, page_only in CHECKS:
        if page_only and not is_page_view(path, body):
            continue

        if key == 'scaffold-no-ui-page':
            if is_page_view(path, body) and '<x-ui.page' not in body:
                findings.append((key, label, 1))
            continue

        if key == 'button-text-in-list':
            # The page header's actions slot carries text on purpose; only the
            # slot inside a list row may contain icons exclusively.
            for row in re.finditer(r'<x-ui\.list-row.*?</x-ui\.list-row>', body, re.S):
                block = row.group(0)
                slot = re.search(r'<x-slot:actions>(.*?)</x-slot:actions>', block, re.S)
                if not slot:
                    continue
                for btn in re.finditer(r'<x-ui\.button[^>]*>(.*?)</x-ui\.button>', slot.group(1), re.S):
                    if btn.group(1).strip():
                        offset = row.start() + slot.start() + btn.start()
                        findings.append((key, label, body.count(chr(10), 0, offset) + 1))
            continue

        for m in pattern.finditer(body):
            line = body.count('\n', 0, m.start()) + 1
            findings.append((key, label, line))

    return findings


def main() -> int:
    strict = '--strict' in sys.argv
    by_check: dict[str, list[tuple[str, int]]] = defaultdict(list)
    labels: dict[str, str] = {}
    total = 0

    views = backend_views()

    for path in views:
        rel = os.path.relpath(path, ROOT).replace('\\', '/')
        for key, label, line in audit(path):
            by_check[key].append((rel, line))
            labels[key] = label
            total += 1

    print(f'{len(views)} backend views checked\n')

    if not total:
        print('No deviations.')
        return 0

    for key in sorted(by_check, key=lambda k: -len(by_check[k])):
        hits = by_check[key]
        files = sorted({f for f, _ in hits})
        print(f'■ {labels[key]}')
        print(f'  {len(hits)} hit(s) in {len(files)} file(s)')
        for f in files[:12]:
            lines = [str(l) for _, l in hits if _ == f][:6]
            print(f'    {f}:{",".join(lines)}')
        if len(files) > 12:
            print(f'    … and {len(files) - 12} more')
        print()

    print(f'Total: {total} hits')

    return 1 if strict else 0


if __name__ == '__main__':
    raise SystemExit(main())
