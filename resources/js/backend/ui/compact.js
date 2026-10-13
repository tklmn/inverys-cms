/**
 * Compaction for narrow screens (< 40rem).
 *
 * 1. Page header: if a page has several actions, they wrap onto three lines
 *    on a phone. In that case the main action stays, the others move
 *    into a "⋯" menu. The elements are moved, not copied —
 *    forms (e.g. delete) keep working inside it unchanged.
 *
 * 2. Filter bars: search stays visible, selects and buttons collapse
 *    behind a filter button. The number on it shows active filters.
 *
 * Enlarging the window restores everything.
 */

import { iconEl } from './icons.js';
import { t } from './i18n.js';

const narrow = window.matchMedia('(width < 40rem)');

/* ── 1. Page header ────────────────────────────────────────── */

function compactActions(bar) {
    if (bar.dataset.compacted) return;

    // Count only visible actions — hidden ones (e.g. "Reset" on the
    // dashboard, which only appears in edit mode) stay where they are
    const visible = (el) => !el.classList.contains('hidden') && getComputedStyle(el).display !== 'none';
    const items = [...bar.children].filter((el) => !el.matches('[data-compact-more]') && visible(el));
    if (items.length < 2) return;

    // Main action: the primary button (also inside a form), otherwise the last one
    const primary = items.find((el) => el.matches('.ui-btn-primary') || el.querySelector('.ui-btn-primary')) || items[items.length - 1];
    const rest = items.filter((el) => el !== primary);
    if (!rest.length) return;

    const wrap = document.createElement('div');
    wrap.className = 'ui-more';
    wrap.dataset.compactMore = '';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'ui-btn ui-btn-md ui-btn-secondary ui-btn-icon';
    toggle.setAttribute('aria-label', bar.dataset.moreLabel || t('more_actions'));
    toggle.setAttribute('aria-expanded', 'false');
    toggle.append(iconEl('ellipsis'));

    const menu = document.createElement('div');
    menu.className = 'ui-more-menu';
    menu.hidden = true;

    rest.forEach((el) => {
        el.dataset.compactHome = String([...bar.children].indexOf(el));
        menu.appendChild(el);
    });

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        menu.hidden = !menu.hidden;
        toggle.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
    });

    wrap.append(toggle, menu);
    bar.insertBefore(wrap, primary);
    bar.dataset.compacted = '';
}

function restoreActions(bar) {
    if (!bar.dataset.compacted) return;

    const wrap = bar.querySelector('[data-compact-more]');
    const moved = [...wrap.querySelector('.ui-more-menu').children]
        .sort((a, b) => Number(a.dataset.compactHome) - Number(b.dataset.compactHome));

    moved.forEach((el) => {
        const home = Number(el.dataset.compactHome);
        delete el.dataset.compactHome;
        bar.insertBefore(el, bar.children[home] || null);
    });

    wrap.remove();
    delete bar.dataset.compacted;
}

document.addEventListener('click', (event) => {
    if (event.target.closest('.ui-more-menu')) return;
    document.querySelectorAll('.ui-more-menu:not([hidden])').forEach((menu) => {
        menu.hidden = true;
        menu.previousElementSibling?.setAttribute('aria-expanded', 'false');
    });
});

/* ── 2. Filter bars ─────────────────────────────────────────── */

function activeFilters(form) {
    return [...form.querySelectorAll('select')].filter((s) => s.value !== '' && s.selectedIndex > 0).length;
}

function prepareToolbar(toolbar) {
    const form = toolbar.querySelector('form');
    if (!form || toolbar.dataset.collapsible !== undefined) return;

    // Only if there is something besides the search to collapse
    const controls = [...form.children];
    if (controls.length < 3) return;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'ui-btn ui-btn-md ui-btn-secondary ui-toolbar-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.append(iconEl('filter'), document.createTextNode(toolbar.dataset.filterLabel || form.querySelector('[type="submit"]')?.textContent.trim() || t('filter')));

    const count = activeFilters(form);
    if (count) {
        const badge = document.createElement('span');
        badge.className = 'ui-badge ui-badge-accent ui-badge-plain';
        badge.textContent = String(count);
        toggle.append(badge);
    }

    toggle.addEventListener('click', () => {
        const open = toolbar.toggleAttribute('data-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Right after the first field (the search)
    form.insertBefore(toggle, controls[1]);
    toolbar.dataset.collapsible = '';
    // If filters are active, show it open right away — otherwise you cannot see why the list is short
    if (count) toolbar.setAttribute('data-open', '');
}

/* ── Toggle by width ────────────────────────────────────────── */

function apply() {
    document.querySelectorAll('.ui-page-head > .ui-page-actions').forEach((bar) => {
        if (narrow.matches) compactActions(bar); else restoreActions(bar);
    });
    // Create the filter button once; it is only visible when narrow (CSS)
    document.querySelectorAll('.ui-toolbar').forEach(prepareToolbar);
}

document.addEventListener('DOMContentLoaded', apply);
narrow.addEventListener('change', apply);
