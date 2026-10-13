/**
 * Icons for markup that is only created in the browser.
 *
 * Counterpart to App\Support\Icon: the same Lucide files, the same
 * alias table for Bootstrap Icons names. Vite inlines the SVGs at build
 * time, so there is no extra request.
 *
 *   import { iconEl, iconHtml } from './icons.js';
 *   button.append(iconEl('trash-2'));
 *   el.innerHTML = iconHtml('bi-check-circle') + ' Saved';
 *
 * For inline scripts in Blade and for third-party plugins the same is
 * available as window.uiIcon(name, className).
 */

import aliases from '../../../icons/aliases.json';

const files = import.meta.glob('../../../icons/lucide/*.svg', {
    query: '?raw',
    import: 'default',
    eager: true,
});

const svgs = {};

for (const [path, markup] of Object.entries(files)) {
    const name = path.split('/').pop().replace('.svg', '');
    // Strip the license comment, set the header as in App\Support\Icon
    svgs[name] = markup.replace(/^<!--[\s\S]*?-->\s*/, '').trim();
}

function resolve(name) {
    const clean = String(name || '').replace(/^bi\s+/, '').trim();
    return aliases[clean] || clean;
}

/**
 * SVG markup as a string — for innerHTML.
 */
export function iconHtml(name, className = '') {
    const markup = svgs[resolve(name)];

    if (!markup) {
        // Unknown name: icon-sized placeholder instead of a layout shift
        return `<span class="ui-icon ${className}" style="width:1em;height:1em" aria-hidden="true"></span>`;
    }

    const classes = `ui-icon ${className}`.trim();

    return markup.replace(
        /^<svg\b[^>]*/,
        `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="1em" height="1em" aria-hidden="true" focusable="false" class="${classes}"`,
    );
}

/**
 * SVG as an element — for appendChild and the builders' h() helpers.
 */
export function iconEl(name, className = '') {
    const template = document.createElement('template');
    template.innerHTML = iconHtml(name, className);
    return template.content.firstElementChild;
}

/**
 * Splits a Bootstrap Icons class list ("bi bi-trash text-xs mr-1") into
 * icon name and remaining classes.
 */
export function fromBootstrapClass(classList) {
    const parts = String(classList || '').split(/\s+/).filter(Boolean);
    const name = parts.find((part) => part.startsWith('bi-')) || '';
    const rest = parts.filter((part) => part !== 'bi' && part !== name).join(' ');

    return iconEl(name, rest);
}

/*
 * Safety net for third-party code: plugins outside this repo may
 * create <i class="bi bi-…">. The Bootstrap font is not
 * loaded, so such elements would be invisible. They are replaced with the
 * matching SVG on insertion. Our own code uses iconEl() directly.
 */
function upgrade(root) {
    const found = root.matches?.('i.bi') ? [root] : [...(root.querySelectorAll?.('i.bi') || [])];

    found.forEach((legacy) => {
        const svg = fromBootstrapClass(legacy.className);
        if (svg) legacy.replaceWith(svg);
    });
}

window.uiIcon = iconEl;
window.uiIconHtml = iconHtml;

document.addEventListener('DOMContentLoaded', () => {
    upgrade(document.body);

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) upgrade(node);
            });
        });
    }).observe(document.body, { childList: true, subtree: true });
});
