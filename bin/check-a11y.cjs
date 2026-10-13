#!/usr/bin/env node
/**
 * Accessibility check of the website (WCAG 2.2 AA) with axe-core in Chromium
 * (Playwright). Renders representative pages of the active theme — meant for
 * the default theme with the demo content — in light and dark mode, on
 * desktop and at phone width, and fails on every axe violation.
 *
 *   node bin/check-a11y.cjs                    # default page list
 *   node bin/check-a11y.cjs / /about /faq      # individual pages
 *   BASE_URL=http://127.0.0.1:8000 npm run check:a11y
 *
 * Environment:
 *   BASE_URL   site root (default http://localhost/inverys/cms/public)
 *   MODES      light,dark (default both)
 *   VIEWPORTS  desktop,mobile (default both)
 *   SHOTS      folder for a screenshot per page with violations
 *
 * Deterministic on purpose: reduced motion (no count-up or transitions),
 * the colour scheme is switched the way visitors do it (POST /theme, kept
 * in the session) and checked on <html class="dark"> before any page is
 * tested. Exit code 1 on violations, 2 when a page cannot be checked.
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE = (process.env.BASE_URL || 'http://localhost/inverys/cms/public').replace(/\/+$/, '');
const SHOTS = process.env.SHOTS || '';
const AXE_SOURCE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

// Demo content: home (hero, features, stats …), text pages, a page with
// sub pages, the FAQ (accordion), contact, the search page and the 404 page.
const DEFAULT_PAGES = ['/', '/about', '/services', '/features', '/faq', '/contact', '/search?q=demo', '/this-page-does-not-exist'];
const EXPECTED_STATUS = { '/this-page-does-not-exist': 404 };

const ALL_VIEWPORTS = {
    desktop: { width: 1280, height: 900 },
    mobile: { width: 390, height: 844 },
};
const pick = (value, allowed) => (value ? value.split(',').map((v) => v.trim()).filter((v) => allowed.includes(v)) : allowed);
const MODES = pick(process.env.MODES, ['light', 'dark']);
const VIEWPORTS = pick(process.env.VIEWPORTS, Object.keys(ALL_VIEWPORTS));

// WCAG 2.0, 2.1 and 2.2, levels A and AA
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa'];

// Transitions off: colours are measured in their final state
const FREEZE_CSS = '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}';

async function setColorScheme(page, mode) {
    const isDark = () => page.evaluate(() => document.documentElement.classList.contains('dark'));
    if ((await isDark()) === (mode === 'dark')) return;

    // Same request as the theme toggle (a form): stored in the session
    const token = await page.evaluate(() => document.querySelector('meta[name="csrf-token"]')?.content || '');
    const response = await page.request.post(BASE + '/theme', {
        form: { _token: token, theme: mode },
        headers: { Referer: page.url() },
        maxRedirects: 0,
    });
    if (response.status() >= 400) throw new Error(`POST /theme answered ${response.status()}`);

    await page.reload({ waitUntil: 'networkidle' });
    if ((await isDark()) !== (mode === 'dark')) throw new Error(`could not switch to ${mode} mode`);
}

async function audit(page) {
    await page.addStyleTag({ content: FREEZE_CSS });
    await page.evaluate(() => document.fonts && document.fonts.ready);
    if (!(await page.evaluate(() => typeof window.axe !== 'undefined'))) {
        await page.evaluate(AXE_SOURCE);
    }

    return page.evaluate(async (tags) => {
        const result = await window.axe.run(document, {
            runOnly: { type: 'tag', values: tags },
            resultTypes: ['violations'],
        });

        return result.violations.map((violation) => ({
            id: violation.id,
            impact: violation.impact,
            help: violation.help,
            helpUrl: violation.helpUrl,
            nodes: violation.nodes.map((node) => ({
                target: node.target.join(' '),
                summary: (node.failureSummary || '').split('\n').slice(1, 3).join(' ').trim(),
            })),
        }));
    }, TAGS);
}

(async () => {
    const pages = process.argv.slice(2).length ? process.argv.slice(2) : DEFAULT_PAGES;
    const browser = await chromium.launch();
    const found = [];
    const broken = [];
    let checked = 0;

    for (const mode of MODES) {
        for (const viewportName of VIEWPORTS) {
            const context = await browser.newContext({
                viewport: ALL_VIEWPORTS[viewportName],
                colorScheme: mode,
                reducedMotion: 'reduce',
                // axe is injected into the page; the site's CSP would block it
                bypassCSP: true,
                ignoreHTTPSErrors: true,
                locale: 'en-US',
            });
            const page = await context.newPage();
            const label = `${mode}/${viewportName}`;

            try {
                await page.goto(BASE + '/', { waitUntil: 'networkidle', timeout: 30000 });
                await setColorScheme(page, mode);
            } catch (error) {
                broken.push(`${label}: ${error.message.split('\n')[0]}`);
                await context.close();
                continue;
            }

            for (const url of pages) {
                let response;
                try {
                    response = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 30000 });
                } catch (error) {
                    broken.push(`${label} ${url}: does not load (${error.message.split('\n')[0]})`);
                    continue;
                }

                const expected = EXPECTED_STATUS[url] || 200;
                if (!response || response.status() !== expected) {
                    broken.push(`${label} ${url}: HTTP ${response ? response.status() : '—'} instead of ${expected}`);
                    continue;
                }

                const violations = await audit(page);
                checked++;

                for (const violation of violations) {
                    found.push({ label, url, ...violation });
                }

                if (SHOTS && violations.length) {
                    fs.mkdirSync(SHOTS, { recursive: true });
                    await page.screenshot({ path: path.join(SHOTS, `${mode}-${viewportName}${url.replace(/[^a-z0-9]+/gi, '_')}.png`), fullPage: true });
                }
            }

            await context.close();
        }
    }

    await browser.close();

    for (const v of found) {
        console.log(`${v.label.padEnd(14)} ${v.url} — ${v.id} (${v.impact}): ${v.help}`);
        v.nodes.slice(0, 5).forEach((node) => console.log(`    ${node.target}${node.summary ? '  →  ' + node.summary : ''}`));
        if (v.nodes.length > 5) console.log(`    … and ${v.nodes.length - 5} more`);
        console.log(`    ${v.helpUrl}`);
    }
    broken.forEach((message) => console.log(`NOT CHECKED  ${message}`));
    console.log('----');
    console.log(`${pages.length} pages × ${MODES.length} modes × ${VIEWPORTS.length} viewports: ${checked} checked, ${found.length} violations (WCAG 2.2 AA, axe-core)`);

    process.exit(broken.length ? 2 : (found.length ? 1 : 0));
})();
