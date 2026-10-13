/**
 * Quick search in the backend header (Ctrl/⌘ + K).
 *
 * Jumps to any navigation item without expanding the sidebar.
 * These entries come from the already rendered navigation and appear
 * instantly. From two characters on, the search also queries the server
 * (App\Services\AdminSearch): pages, media, users, settings and
 * everything plugins contribute via an AdminSearchProvider — grouped,
 * with the user's permissions.
 *
 *   <button data-command-open data-command-search="/backend/search">
 */

import { t } from './i18n.js';
import { iconEl } from './icons.js';

const trigger = document.querySelector('[data-command-open]');

// Ignore case, umlauts and accents: "settings" finds "Settings", "cafe" finds "Café"
const fold = (text) => String(text || '').toLowerCase().normalize('NFD').replace(/\p{M}/gu, '').trim();

function navEntries() {
    const entries = [];

    document.querySelectorAll('#nav a.nav-item[href]').forEach((link) => {
        const label = link.querySelector('.nav-label')?.textContent.trim() || link.textContent.trim();
        if (!label) return;

        // Sub-entries carry the name of their parent item as context ("Settings › Security")
        const sub = link.closest('.nav-sub');
        const parent = sub ? document.querySelector(`[aria-controls="${sub.id}"] .nav-label`)?.textContent.trim() : '';
        const iconSource = link.querySelector('.nav-item-icon') || (sub && document.querySelector(`[aria-controls="${sub.id}"] .nav-item-icon`));

        entries.push({
            label,
            context: parent || '',
            href: link.href,
            icon: iconSource ? iconSource.innerHTML : '',
            key: fold(`${parent} ${label}`),
            current: link.hasAttribute('data-current'),
        });
    });

    return entries;
}

function build() {
    const scrim = document.createElement('div');
    scrim.className = 'ui-cmd-scrim';
    scrim.hidden = true;

    const dialog = document.createElement('div');
    dialog.className = 'ui-cmd';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-label', t('search', 'Search'));

    const head = document.createElement('div');
    head.className = 'ui-cmd-head';
    head.append(iconEl('search', 'ui-cmd-head-icon'));

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'ui-cmd-input';
    input.placeholder = t('search_placeholder', 'Go to page or setting …');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'true');
    input.setAttribute('aria-controls', 'ui-cmd-list');
    input.setAttribute('aria-autocomplete', 'list');
    input.autocomplete = 'off';
    input.spellcheck = false;

    const esc = document.createElement('kbd');
    esc.className = 'ui-kbd';
    esc.textContent = t('search_esc', 'Esc');

    head.append(input, esc);

    const list = document.createElement('div');
    list.className = 'ui-cmd-list';
    list.id = 'ui-cmd-list';
    list.setAttribute('role', 'listbox');

    const foot = document.createElement('div');
    foot.className = 'ui-cmd-foot';
    const hint = (keys, text) => {
        const span = document.createElement('span');
        keys.forEach((key) => {
            const kbd = document.createElement('kbd');
            kbd.className = 'ui-kbd';
            kbd.append(key);
            span.append(kbd);
        });
        span.append(' ' + text);
        return span;
    };
    foot.append(
        hint([iconEl('arrow-up'), iconEl('arrow-down')], t('search_hint_move', 'to select')),
        hint([iconEl('corner-down-left')], t('search_hint_open', 'to open')),
    );

    dialog.append(head, list, foot);
    scrim.append(dialog);
    document.body.append(scrim);

    return { scrim, input, list };
}

if (trigger) {
    // The server writes the localized "Ctrl K"; on the Mac the key is ⌘
    if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
        trigger.querySelectorAll('[data-command-key]').forEach((el) => { el.textContent = '⌘ K'; });
    }

    let ui = null;
    let items = [];
    let active = 0;
    let lastFocus = null;

    // Server response to the latest search; new input aborts the previous request
    let remote = { query: '', groups: [] };
    let pending = '';
    let timer = null;
    let request = null;

    const fetchRemote = (raw) => {
        clearTimeout(timer);
        request?.abort();
        if (!trigger.dataset.commandSearch || raw.length < 2) {
            pending = '';
            return;
        }
        pending = raw;
        timer = setTimeout(() => {
            request = new AbortController();
            fetch(`${trigger.dataset.commandSearch}?q=${encodeURIComponent(raw)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: request.signal,
            })
                .then((response) => (response.ok ? response.json() : { groups: [] }))
                .then((data) => {
                    if (pending !== raw) return;
                    remote = { query: raw, groups: Array.isArray(data.groups) ? data.groups : [] };
                    pending = '';
                    render();
                })
                .catch(() => {
                    if (pending === raw) { pending = ''; render(); }
                });
        }, 180);
    };

    const render = () => {
        const query = fold(ui.input.value);
        const raw = ui.input.value.trim();
        const all = navEntries();

        let matches = all;
        if (query) {
            const words = query.split(/\s+/);
            // Matches whose name starts with the search term first (sort is stable)
            const starts = (entry) => (fold(entry.label).startsWith(words[0]) ? 0 : 1);
            matches = all
                .filter((entry) => words.every((word) => entry.key.includes(word)))
                .sort((a, b) => starts(a) - starts(b));
        }

        items = matches.map((entry) => ({ ...entry, group: 'nav', groupLabel: t('search_group_nav', 'Navigation') }));

        // Server matches, as long as they belong to the current input; skip
        // anything already listed as a menu item
        if (raw && remote.query === raw) {
            const shown = new Set(items.map((item) => item.href));
            remote.groups.forEach((group) => {
                (group.items || []).forEach((hit) => {
                    const href = new URL(hit.url, window.location.href).href;
                    if (shown.has(href)) return;
                    shown.add(href);
                    items.push({ group: group.key, groupLabel: group.label, href, label: hit.title, meta: hit.context || '', iconName: group.icon });
                });
            });
        }

        active = Math.min(active, Math.max(items.length - 1, 0));
        ui.list.replaceChildren();

        const searching = raw.length >= 2 && pending === raw;

        if (!items.length) {
            if (searching) {
                const status = document.createElement('p');
                status.className = 'ui-cmd-status';
                status.textContent = t('search_loading', 'Searching …');
                ui.list.append(status);
                return;
            }
            const empty = document.createElement('p');
            empty.className = 'ui-cmd-empty';
            empty.textContent = t('search_empty', 'No matching entry.');
            ui.list.append(empty);
            return;
        }

        let group = null;
        items.forEach((item, index) => {
            if (item.group !== group) {
                group = item.group;
                const label = document.createElement('p');
                label.className = 'ui-cmd-group';
                label.textContent = item.groupLabel;
                ui.list.append(label);
            }

            const option = document.createElement('a');
            option.className = 'ui-cmd-item';
            option.href = item.href;
            option.id = `ui-cmd-item-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(index === active));

            const icon = document.createElement('span');
            icon.className = 'ui-cmd-item-icon';
            if (item.icon) icon.innerHTML = item.icon;
            else icon.append(iconEl(item.iconName || 'arrow-right'));

            const text = document.createElement('span');
            text.className = 'ui-cmd-item-label';
            if (item.context) {
                const ctx = document.createElement('span');
                ctx.className = 'ui-cmd-item-context';
                ctx.textContent = item.context;
                text.append(ctx);
            }
            text.append(item.label);
            if (item.meta) {
                const meta = document.createElement('span');
                meta.className = 'ui-cmd-item-meta';
                meta.textContent = item.meta;
                text.append(meta);
            }

            option.append(icon, text);
            if (item.current) {
                const here = iconEl('check', 'ui-cmd-item-here');
                option.append(here);
            }

            option.addEventListener('mousemove', () => {
                if (active !== index) { active = index; highlight(); }
            });

            ui.list.append(option);
        });

        if (searching) {
            const status = document.createElement('p');
            status.className = 'ui-cmd-status';
            status.textContent = t('search_loading', 'Searching …');
            ui.list.append(status);
        }

        ui.input.setAttribute('aria-activedescendant', `ui-cmd-item-${active}`);
    };

    const highlight = () => {
        ui.list.querySelectorAll('.ui-cmd-item').forEach((el, index) => {
            el.setAttribute('aria-selected', String(index === active));
            if (index === active) el.scrollIntoView({ block: 'nearest' });
        });
        ui.input.setAttribute('aria-activedescendant', `ui-cmd-item-${active}`);
    };

    const close = () => {
        if (!ui || ui.scrim.hidden) return;
        ui.scrim.hidden = true;
        document.documentElement.classList.remove('ui-cmd-open');
        lastFocus?.focus?.();
    };

    const open = () => {
        if (!ui) {
            ui = build();

            ui.scrim.addEventListener('mousedown', (e) => { if (e.target === ui.scrim) close(); });
            ui.input.addEventListener('input', () => {
                active = 0;
                fetchRemote(ui.input.value.trim());
                render();
            });
            ui.input.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % Math.max(items.length, 1); highlight(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + items.length) % Math.max(items.length, 1); highlight(); }
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    const item = items[active];
                    if (item) window.location.href = item.href;
                } else if (e.key === 'Escape') { e.preventDefault(); close(); }
                else if (e.key === 'Tab') { e.preventDefault(); }
            });
        }

        lastFocus = document.activeElement;
        fetchRemote('');
        remote = { query: '', groups: [] };
        ui.input.value = '';
        active = 0;
        render();
        ui.scrim.hidden = false;
        document.documentElement.classList.add('ui-cmd-open');
        ui.input.focus();
    };

    trigger.addEventListener('click', open);

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (ui && !ui.scrim.hidden) close(); else open();
        }
    });
}
