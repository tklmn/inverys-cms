#!/usr/bin/env python3
"""
Finds visible text that is not translated.

Scans all Blade views (backend, frontend, themes, plugins, mails,
login, installer) for
  - text nodes between tags that are not inside {{ }} / @lang
  - attributes with text (placeholder, title, aria-label, alt, label …)
  - JS literals that end up as text on the page (textContent, confirm, alert …)

    python3 bin/check-hardcoded-text.py            all findings
    python3 bin/check-hardcoded-text.py --summary  count per file only

Heuristic — it also reports harmless things (brand names, units).
Mark intentionally untranslated spots with {{-- i18n-ignore --}} on the line.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DIRS = ['resources/views', 'plugins', 'themes', 'vendor/inverys']
SKIP = re.compile(r'/(lang|node_modules|sbom|vendor/[^n])/|/views/vendor/')

LETTERS = re.compile(r'[A-Za-zÄÖÜäöüß]{2,}')  # german-ok: matches German UI text
# Not text: code, units, placeholders, brands
HARMLESS = re.compile(
    r'^(?:[\W\d_]*|&[a-z]+;|[A-Z]{2,5}|px|em|rem|ms|KB|MB|GB|URL|ID|OK|API|CSS|JS|HTML|SMTP|OAuth2?|'
    r'Inverys(?: CMS)?|Laravel|PHP|Tailwind|Lucide|GitHub|Google|Microsoft(?: 365)?|Gmail|reCAPTCHA|hCaptcha|Turnstile|'
    r'Cloudflare|TinyMCE|CodeMirror|Markdown|JSON|YAML|SVG|PNG|JPG|WebP|GIF|PDF|CSV|XML|RSS|HTTP|HTTPS|IP|UTC|'
    r'MySQL(?: / MariaDB)?|MariaDB|PostgreSQL|SQLite|— Inverys CMS|/\* CSS \*/|'
    r'Lorem ipsum.*|x|×|—|–|·|…|\||/)$'
)

TEXT_ATTRS = r'(?:placeholder|title|aria-label|alt|label|lead|description|hint|help|confirm|confirm-text|empty|heading|subtitle|caption|tooltip|data-confirm|data-title|data-label|data-empty|data-tooltip)'
ATTR = re.compile(r'(?<![:\w-])(' + TEXT_ATTRS + r')\s*=\s*"([^"]*)"')
JS_SINK = re.compile(
    r'(?:textContent|innerText|placeholder|title|ariaLabel)\s*=\s*([\'"`])((?:(?!\1).)*[A-Za-z]{3,}(?:(?!\1).)*)\1'
    r'|(?:confirm|alert|prompt|uiToast)\(\s*([\'"`])((?:(?!\3).)*[A-Za-z]{3,}(?:(?!\3).)*)\3'
)

JS_HTML_TEXT = re.compile(r">\s*([A-Za-zÄÖÜäöü][^<>'\"`+${}]*?)\s*</")  # german-ok: matches German UI text
JS_LABEL = re.compile(r"\b(label|title|placeholder|text|tooltip|name)\s*:\s*(['\"])([^'\"]*[A-Za-z]{3,}[^'\"]*)\2")


def blank(match):
    """Replaces a match with spaces of equal length — lines are preserved."""
    return re.sub(r'[^\n]', ' ', match.group(0))


def strip_blade(src):
    src = re.sub(r'\{\{--.*?--\}\}', blank, src, flags=re.S)
    src = re.sub(r'<!--.*?-->', blank, src, flags=re.S)
    src = re.sub(r'@php\b(?!\s*\().*?@endphp', blank, src, flags=re.S)
    src = re.sub(r'@verbatim.*?@endverbatim', blank, src, flags=re.S)
    src = re.sub(r'\{\{.*?\}\}|\{!!.*?!!\}', lambda m: re.sub(r'[^\n]', '\x01', m.group(0)), src, flags=re.S)
    return src


def directive_spans(src):
    """Blank out @if(...), @php(...), @foreach(...) … including nested parentheses."""
    out = list(src)
    for m in re.finditer(r'@[a-zA-Z]+\s*\(', src):
        depth, i = 0, m.end() - 1
        while i < len(src):
            if src[i] == '(':
                depth += 1
            elif src[i] == ')':
                depth -= 1
                if depth == 0:
                    break
            i += 1
        for j in range(m.start(), min(i + 1, len(src))):
            if out[j] != '\n':
                out[j] = ' '
    src = ''.join(out)
    return re.sub(r'@[a-zA-Z]+', lambda m: ' ' * len(m.group(0)), src)


def line_of(src, pos):
    return src.count('\n', 0, pos) + 1


def check_blade(path, rel, findings):
    raw = open(path, encoding='utf-8').read()
    if 'i18n-ignore-file' in raw:
        return
    lines = raw.split('\n')
    src = strip_blade(raw)

    # Check scripts separately, ignore styles
    scripts = []
    def take_script(m):
        scripts.append((m.start(), m.group(1)))
        return blank(m)
    src = re.sub(r'<script\b[^>]*>(.*?)</script>', take_script, src, flags=re.S | re.I)
    src = re.sub(r'<style\b[^>]*>.*?</style>', blank, src, flags=re.S | re.I)

    # Attributes (before stripping directives, values may contain @)
    for m in ATTR.finditer(src):
        value = m.group(2)
        if '\x01' in value or not LETTERS.search(value) or HARMLESS.match(value.strip()):
            continue
        if m.group(1) in ('label', 'title', 'description') and re.match(r'^[a-z0-9_.:-]+$', value):
            continue  # key or identifier
        add(findings, rel, lines, line_of(src, m.start()), f'{m.group(1)}="{value.strip()}"')

    text_src = directive_spans(src)
    # Remove tags, check the text in between
    for m in re.finditer(r'>([^<>]+)<', text_src):
        text = re.sub(r'&[a-zA-Z]+;|&#\d+;', ' ', m.group(1).replace('\x01', ' ')).strip()
        if not text or not LETTERS.search(text) or HARMLESS.match(text):
            continue
        if re.fullmatch(r'[\w.-]+\s*=\s*.*', text) or text.startswith(('=', '{', '}', '$', '->', '//')):
            continue
        # PHP leftovers and class lists
        if re.search(r'\$\w|=>|::|\)\s*;|\bfn\b', text):
            continue
        add(findings, rel, lines, line_of(text_src, m.start(1)), text[:90])

    for offset, body in scripts:
        check_js_text(body, rel, lines, findings, line_of(raw, offset) - 1)


def check_js_text(body, rel, lines, findings, base=0):
    for m in JS_SINK.finditer(body):
        text = m.group(2) or m.group(4) or ''
        if '${' in text and not LETTERS.search(re.sub(r'\$\{[^}]*\}', '', text)):
            continue
        if '@json' in text or '{{' in text or HARMLESS.match(text.strip()):
            continue
        if re.search(r"['\"`]\s*\+|\+\s*['\"`]", text):
            continue
        add(findings, rel, lines, base + body.count('\n', 0, m.start()) + 1, 'JS: ' + text[:80])

    # Text in HTML snippets: '<p>Error</p>', `<span>Spacer</span>`
    for m in JS_HTML_TEXT.finditer(body):
        text = m.group(1).strip()
        if not LETTERS.search(text) or HARMLESS.match(text):
            continue
        add(findings, rel, lines, base + body.count('\n', 0, m.start()) + 1, 'JS-HTML: ' + text[:80])

    # Labels in objects: { label: 'Text' }
    for m in JS_LABEL.finditer(body):
        text = m.group(3)
        if HARMLESS.match(text.strip()) or re.match(r'^[a-z0-9_.:-]+$', text):
            continue
        add(findings, rel, lines, base + body.count('\n', 0, m.start()) + 1, f'JS-{m.group(1)}: ' + text[:80])


def add(findings, rel, lines, line, text):
    source = lines[line - 1] if 0 < line <= len(lines) else ''
    if 'i18n-ignore' in source:
        return
    findings.setdefault(rel, []).append((line, text))


def main():
    findings = {}
    for d in DIRS:
        for dirpath, _, files in os.walk(os.path.join(ROOT, d)):
            for name in files:
                path = os.path.join(dirpath, name)
                rel = os.path.relpath(path, ROOT)
                if SKIP.search('/' + rel.replace(os.sep, '/') + '/'):
                    continue
                if name.endswith('.blade.php'):
                    check_blade(path, rel, findings)
    for dirpath, _, files in os.walk(os.path.join(ROOT, 'resources/js')):
        for name in files:
            if name.endswith('.js'):
                path = os.path.join(dirpath, name)
                src = open(path, encoding='utf-8').read()
                if 'i18n-ignore-file' not in src:
                    check_js_text(src, os.path.relpath(path, ROOT), src.split('\n'), findings)

    total = sum(len(v) for v in findings.values())
    summary = '--summary' in sys.argv
    for rel in sorted(findings):
        items = findings[rel]
        if summary:
            print(f'{len(items):4d}  {rel}')
            continue
        print(f'■ {rel}')
        for line, text in items:
            print(f'    {line:4d}  {text}')
    print('----')
    print(f'{total} findings in {len(findings)} files')
    return 1 if total else 0


if __name__ == '__main__':
    sys.exit(main())
