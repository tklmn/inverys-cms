#!/usr/bin/env node
/**
 * Checks every backend page in a real browser — what the HTML checkers
 * cannot see because it only appears during rendering or through JavaScript.
 *
 * Per page, on desktop (1440 px) and at phone width (390 px):
 *   - JavaScript errors on the page
 *   - page scrolls as a whole (instead of just the content area) — e.g. due to
 *     absolutely positioned input fields without a positioning context
 *   - content overflows sideways
 *   - clipped: a field, button or text starts inside the window and ends outside
 *     on the right — even if an overflow:hidden silently hides it (that is how
 *     the "Forgot password" card was wider than the window on phones)
 *   - text overflows buttons, badges and tabs
 *   - raw translation keys (translation.…, blog::…) and JS leftovers
 *     ("undefined", "[object Object]", "NaN") in text, title, aria-label, placeholders
 *   - <i class="bi …">: the Bootstrap font is not loaded, the icon is invisible
 *   - raw <x-…> tags: uncompiled Blade component
 *
 * Page list from `php artisan route:list` (via bin/check-widths.py).
 * Requirement: a logged-in session in $COOKIE_JAR (as with the other checkers).
 * Additionally without login: auth pages, home page, search and the
 * 404 page — also with a language prefix if that language exists (GUEST_LOCALES).
 *
 *   node bin/check-browser.cjs                 # all pages
 *   node bin/check-browser.cjs /backend/pages  # individual pages (no guest pages)
 *   SHOTS=/tmp/shots node bin/check-browser.cjs  # plus screenshots
 *
 * Pages without <main> are skipped (downloads, JSON endpoints).
 */

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.resolve(__dirname, '..');
const BASE = process.env.BASE_URL || 'http://localhost/inverys/cms/public';
const JAR = process.env.COOKIE_JAR || '/tmp/inverys-cookies.txt';
const SHOTS = process.env.SHOTS || '';

const VIEWPORTS = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'mobile', width: 390, height: 844 },
];

// Pages without login; with a prefix only if the language is installed
const GUEST_PAGES = ['/', '/login', '/register', '/forgot-password', '/reset-password/check', '/search?q=zzqq', '/this-page-does-not-exist'];
const GUEST_LOCALES = (process.env.GUEST_LOCALES || 'de').split(',').filter(Boolean);

function routes() {
    const script = [
        'import importlib.util, sys',
        'sys.dont_write_bytecode = True',
        "spec = importlib.util.spec_from_file_location('cw', 'bin/check-widths.py')",
        'cw = importlib.util.module_from_spec(spec)',
        'spec.loader.exec_module(cw)',
        'for r in cw.routes(): print(r)',
    ].join('\n');

    return execFileSync('python3', ['-c', script], { cwd: ROOT, env: { ...process.env, PYTHONDONTWRITEBYTECODE: '1' } })
        .toString().split('\n').filter(Boolean);
}

// Convert a Netscape cookie file (curl -c) into Playwright cookies
function cookies() {
    return fs.readFileSync(JAR, 'utf8').split('\n')
        .filter((line) => line.trim() && (!line.startsWith('#') || line.startsWith('#HttpOnly_')))
        .map((line) => {
            const httpOnly = line.startsWith('#HttpOnly_');
            const [domain, , cookiePath, secure, expires, name, value] = line.replace('#HttpOnly_', '').split('\t');
            return { name, value: (value || '').trim(), domain: domain.replace(/^\./, ''), path: cookiePath, secure: secure === 'TRUE', httpOnly, expires: Number(expires) || -1 };
        })
        .filter((cookie) => cookie.name);
}

async function inspect(page, guest) {
    return page.evaluate((guest) => {
        // Guest pages (login) have no #main-content
        const main = document.getElementById('main-content') || (guest ? document.querySelector('main') || document.body : null);
        if (!main) return null;

        const problems = [];
        const doc = document.scrollingElement;
        // Only in the backend does the content area scroll alone; the website scrolls as a whole
        const isBackend = !!document.querySelector('.app-frame');
        const visible = (el) => (el.checkVisibility ? el.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true }) : el.getClientRects().length > 0);
        const describe = (el) => {
            const cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
            return el.tagName.toLowerCase() + (cls.length ? '.' + cls.join('.') : '');
        };
        const short = (text) => text.replace(/\s+/g, ' ').trim().slice(0, 60);

        if (isBackend && doc.scrollHeight > window.innerHeight + 1) {
            problems.push(`page scrolls as a whole (${doc.scrollHeight}px instead of ${window.innerHeight}px)`);
        }
        if (main.scrollWidth > main.clientWidth + 1 || doc.scrollWidth > window.innerWidth + 1) {
            problems.push('content overflows sideways');
        }

        // Clipped: starts inside the window, ends outside on the right. Areas that
        // scroll sideways on purpose (tabs, code, tables, sliders) do not count.
        const cut = [];
        document.querySelectorAll('input, select, textarea, button, label, h1, h2, h3, p, .ui-btn, .ui-badge, .site-btn').forEach((el) => {
            if (!visible(el)) return;
            const rect = el.getBoundingClientRect();
            if (!(rect.width > 0 && rect.left < window.innerWidth - 1 && rect.right > window.innerWidth + 1)) return;
            for (let parent = el.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
                if (['auto', 'scroll'].includes(getComputedStyle(parent).overflowX)) return;
                if (/slider|carousel|marquee|swiper/.test(parent.getAttribute('class') || '')) return;
            }
            cut.push(describe(el));
        });
        if (cut.length) problems.push(`clipped (outside on the right): ${[...new Set(cut)].slice(0, 4).join(', ')}`);

        // Text overflows buttons, badges, tabs
        document.querySelectorAll('.ui-btn, .ui-badge, .ui-tab, [role=tab], .site-btn').forEach((el) => {
            if (visible(el) && el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 1) {
                problems.push(`text overflows ${describe(el)}: "${short(el.textContent)}"`);
            }
        });

        // Raw keys and JS leftovers in visible text and attributes
        const key = /\b(?:translation|validation|auth|passwords|pagination)\.[a-z0-9_]+\.?[a-z0-9_.]*|\b[a-z][a-z0-9-]*::[a-z0-9_]+(?:\.[a-z0-9_.-]+)*/;
        const junk = /\bundefined\b|\[object Object\]|\bNaN\b/;
        const skip = 'script, style, noscript, template, code, pre, kbd, textarea, [translate="no"]';
        const texts = [];
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (node.nodeValue.trim() && node.parentElement && !node.parentElement.closest(skip) && visible(node.parentElement)) {
                texts.push([node.nodeValue, node.parentElement]);
            }
        }
        document.querySelectorAll('[title], [aria-label], [placeholder]').forEach((el) => {
            if (el.closest(skip)) return;
            ['title', 'aria-label', 'placeholder'].forEach((name) => el.getAttribute(name) && texts.push([el.getAttribute(name), el]));
        });
        const found = new Set();
        for (const [text, el] of texts) {
            const hit = text.match(key) || text.match(junk);
            if (hit) found.add(`"${hit[0]}" in ${describe(el)}`);
        }
        if (found.size) problems.push(`raw text: ${[...found].slice(0, 4).join(', ')}`);

        const legacy = document.querySelectorAll('i.bi, i[class*="bi-"]').length;
        if (legacy && isBackend) problems.push(`${legacy} invisible Bootstrap icons`);

        const raw = document.documentElement.innerHTML.match(/<x-[a-z][\w.:-]*/g);
        if (raw) problems.push(`raw Blade tags: ${[...new Set(raw)].join(', ')}`);

        return problems;
    }, guest);
}

(async () => {
    const onlyGiven = process.argv.slice(2).length > 0;

    if (!fs.existsSync(JAR)) {
        console.error(`No session in ${JAR} — please log in first.`);
        process.exit(2);
    }

    const pages = onlyGiven ? process.argv.slice(2) : routes();
    const guestPages = onlyGiven ? [] : GUEST_PAGES.concat(...GUEST_LOCALES.map((locale) => GUEST_PAGES.map((url) => `/${locale}${url === '/' ? '' : url}`)));
    const browser = await chromium.launch();
    const found = [];
    let checked = 0;

    const run = async (viewport, urls, guest) => {
        const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } });
        if (!guest) await context.addCookies(cookies());
        const page = await context.newPage();
        let errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        // Lazily loaded scripts and styles that are missing (wrong base path)
        // throw no JS error — the feature is simply absent. So report them explicitly.
        page.on('response', (response) => {
            const type = response.request().resourceType();
            if (response.status() >= 400 && ['script', 'stylesheet'].includes(type)) {
                errors.push(`${type} ${response.status()}: ${response.url()}`);
            }
        });

        for (const url of urls) {
            errors = [];
            let response;
            try {
                response = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 30000 });
            } catch (error) {
                found.push([viewport.name, url, `does not load: ${error.message.split('\n')[0]}`]);
                continue;
            }

            // Language prefix without an installed language: not a finding, just not checkable
            if (guest && /^\/[a-z]{2}(\/|$)/.test(url) && response && response.status() === 404 && !url.includes('does-not-exist')) continue;

            const problems = await inspect(page, guest);
            if (problems === null) continue;

            checked++;
            errors.forEach((message) => problems.push(`JS error: ${message}`));
            problems.forEach((problem) => found.push([viewport.name + (guest ? ' guest' : ''), url, problem]));

            if (SHOTS) {
                fs.mkdirSync(SHOTS, { recursive: true });
                await page.screenshot({ path: path.join(SHOTS, `${viewport.name}${guest ? '-guest' : ''}${url.replace(/[^a-z0-9]+/gi, '_')}.png`) });
            }
        }

        await context.close();
    };

    for (const viewport of VIEWPORTS) {
        await run(viewport, pages, false);
        if (guestPages.length) await run(viewport, guestPages, true);
    }

    await browser.close();

    for (const [viewport, url, problem] of found) console.log(`${viewport.padEnd(8)} ${url} — ${problem}`);
    console.log('----');
    console.log(`${pages.length} routes + ${guestPages.length} guest pages, ${checked} page loads (desktop + mobile), ${found.length} problems`);
    process.exit(found.length ? 1 : 0);
})();
