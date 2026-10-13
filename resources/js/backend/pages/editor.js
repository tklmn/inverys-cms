/**
 * Page editor (full screen): shell around structure, preview and sidebar.
 *
 * - Preview: the real page in the theme (backend.pages.canvas) in an iframe.
 *   After every automatic save of the blocks (pb:saved) it reloads —
 *   in the second, hidden iframe, which only becomes visible once loaded (at
 *   the same scroll position). That way nothing jumps or flickers.
 * - Sidebar: Block · Page · Versions; selecting a block takes you to its
 *   settings.
 * - Preview width (desktop, tablet, mobile) and shown/hidden columns are
 *   remembered per browser.
 * - Narrow (below lg) only one pane is shown at a time.
 * - Page settings: notice and confirmation on unsaved changes,
 *   Ctrl/Cmd+S saves.
 * - Undo/redo and keyboard shortcuts (also from the preview, see
 *   "?"): Ctrl+Z, Ctrl+Shift+Z/Ctrl+Y, Ctrl+D, Del, Alt+Arrow, Esc.
 *
 * Markup: resources/views/backend/pages/edit.blade.php
 */

const store = {
    get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* private mode */ } },
};

function init(shell) {
    const $ = (sel) => shell.querySelector(sel);
    const $$ = (sel) => [...shell.querySelectorAll(sel)];
    const text = (key, fallback) => shell.dataset['t' + key] || fallback;
    const wide = window.matchMedia('(min-width: 64rem)');

    /* ── Sidebar: tabs ── */
    const tabs = $$('[data-side-tab]');
    function showSide(name, persist = true) {
        if (!$(`[data-side-panel="${name}"]`)) name = 'block';
        tabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.sideTab === name ? 'true' : 'false'));
        $$('[data-side-panel]').forEach((panel) => { panel.hidden = panel.dataset.sidePanel !== name; });
        if (persist) store.set('pe-side-tab', name);
    }
    tabs.forEach((tab) => tab.addEventListener('click', () => showSide(tab.dataset.sideTab)));
    showSide(shell.dataset.openSide || store.get('pe-side-tab') || 'block', false);

    /* ── Panes: narrow one at a time, wide show/hide columns ── */
    function showPane(name) {
        shell.dataset.pane = name;
        $$('[data-pane-btn]').forEach((btn) => btn.setAttribute('aria-pressed', btn.dataset.paneBtn === name ? 'true' : 'false'));
    }
    $$('[data-pane-btn]').forEach((btn) => btn.addEventListener('click', () => showPane(btn.dataset.paneBtn)));
    if (shell.dataset.openSide) showPane('side');

    function setPanel(name, visible) {
        shell.toggleAttribute('data-hide-' + name, !visible);
        $(`[data-toggle-panel="${name}"]`)?.setAttribute('aria-pressed', visible ? 'true' : 'false');
        store.set('pe-hide-' + name, visible ? '0' : '1');
    }
    $$('[data-toggle-panel]').forEach((btn) => {
        const name = btn.dataset.togglePanel;
        setPanel(name, store.get('pe-hide-' + name) !== '1');
        btn.addEventListener('click', () => setPanel(name, shell.hasAttribute('data-hide-' + name)));
    });

    // Block selected: show its settings (narrow: switch to that pane)
    // (The builder intercepts clicks on blocks — hence pb:select, not click)
    shell.addEventListener('pb:select', (e) => {
        if (!e.detail?.id) return;
        showSide('block');
        if (shell.hasAttribute('data-hide-side')) setPanel('side', true);
        // Narrow: tapping a block in the structure opens its settings;
        // in the preview you stay put, since that is where editing happens
        if (!wide.matches && shell.dataset.pane === 'structure') showPane('side');
    });

    /* ── Preview width ── */
    const frame = $('[data-canvas-frame]');
    function setDevice(device) {
        frame.dataset.device = device;
        $$('[data-device-btn]').forEach((btn) => btn.setAttribute('aria-pressed', btn.dataset.deviceBtn === device ? 'true' : 'false'));
        store.set('pe-device', device);
    }
    $$('[data-device-btn]').forEach((btn) => btn.addEventListener('click', () => setDevice(btn.dataset.deviceBtn)));
    setDevice(['desktop', 'tablet', 'mobile'].includes(store.get('pe-device')) ? store.get('pe-device') : 'desktop');

    /* ── Preview: double-buffered ── */
    const frames = $$('[data-canvas-iframe]');
    const loading = $('[data-canvas-loading]');
    const baseUrl = shell.dataset.canvasUrl;
    let active = 0;
    let pending = null;
    let reloadTimer = null;

    function scrollOf(iframe) {
        try { return iframe.contentWindow ? iframe.contentWindow.scrollY : 0; } catch (e) { return 0; }
    }

    function loadCanvas() {
        const current = frames[active];
        const next = frames[1 - active];
        const scroll = current.src ? scrollOf(current) : 0;
        const url = baseUrl + (baseUrl.includes('?') ? '&' : '?') + '_=' + Date.now();

        pending = next;
        next.onload = () => {
            if (pending !== next) return;
            pending = null;
            // The hidden frame will not receive edit-end any more — reset the state
            inlineEditing = false;
            try { next.contentWindow.scrollTo(0, scroll); } catch (e) { /* foreign page */ }
            next.hidden = false;
            current.hidden = true;
            active = 1 - active;
            loading.hidden = true;
        };
        next.src = url;
    }

    function scheduleReload() {
        clearTimeout(reloadTimer);
        reloadTimer = setTimeout(loadCanvas, 150);
    }

    // While typing in the preview: discard a scheduled or running reload,
    // otherwise the buffer swaps the frame out from under the cursor
    function cancelReload() {
        clearTimeout(reloadTimer);
        if (pending) {
            pending.onload = null;
            pending = null;
            reloadPending = true;
        }
    }

    loadCanvas();

    /* ── Save state of the blocks ── */
    const status = $('[data-save-status]');
    let formDirty = false;
    function setStatus(message, tone = '') {
        if (!status) return;
        status.textContent = message;
        status.dataset.tone = tone;
    }
    function timeNow() {
        return new Date().toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' });
    }
    shell.addEventListener('pb:dirty', () => { if (!formDirty) setStatus(text('Saving', 'Saving…')); });
    shell.addEventListener('pb:saving', () => setStatus(text('Saving', 'Saving…')));
    shell.addEventListener('pb:saved', () => {
        setStatus(formDirty ? text('Unsaved', 'Unsaved page settings') : text('Saved', 'Saved') + ' · ' + timeNow(), formDirty ? 'caution' : '');
        // While someone types in the preview, reloading would kick out the cursor —
        // the preview already shows the change anyway
        if (inlineEditing) reloadPending = true;
        else scheduleReload();
    });

    /* ── Preview as canvas (resources/js/editor-canvas.js) ── */
    const builder = () => shell.querySelector('[data-page-builder]')?.pageBuilder || null;
    let inlineEditing = false;
    let reloadPending = false;
    let selectFromCanvas = false;

    function toCanvas(target, message) {
        target?.postMessage({ source: 'inverys-editor', ...message }, window.location.origin);
    }

    function canvasTexts(b) {
        return {
            up: b._('move_up', 'Move up'),
            down: b._('move_down', 'Move down'),
            insert: b._('insert_after', 'Add block below'),
            duplicate: b._('duplicate', 'Duplicate'),
            delete: b._('delete', 'Delete'),
            drag: b._('drag_move', 'Drag to move'),
            empty: b._('canvas_empty', 'empty — double-click to write'),
        };
    }

    window.addEventListener('message', (e) => {
        if (e.origin !== window.location.origin || !e.data || e.data.source !== 'inverys-canvas') return;
        // Only our two iframes (including the still hidden one that is loading)
        if (!frames.some((f) => f.contentWindow === e.source)) return;
        const b = builder();
        if (!b) return;
        const { type, id } = e.data;

        if (type === 'ready') {
            toCanvas(e.source, { type: 'init', labels: b.labels(), texts: canvasTexts(b), selected: b.selectedId });
        } else if (type === 'select') {
            selectFromCanvas = true;
            b.selectBlock(id);
            selectFromCanvas = false;
            b.revealBlock(id);
        } else if (type === 'action') {
            b.canvasAction(e.data.action, id);
        } else if (type === 'move') {
            b.moveBlockRelative(id, e.data.target, !!e.data.after);
        } else if (type === 'key') {
            handleShortcut(e.data, null);
        } else if (type === 'field') {
            b.setField(id, e.data.field, e.data.value);
        } else if (type === 'edit-start') {
            inlineEditing = true;
            cancelReload();
        } else if (type === 'edit-end') {
            inlineEditing = false;
            // Bring the sidebar fields up to date
            if (b.selectedId === id) b.renderSettingsPanel();
            if (reloadPending) { reloadPending = false; scheduleReload(); }
        }
    });

    // Selection in the structure → highlight it in the preview and scroll to it
    shell.addEventListener('pb:select', (e) => {
        if (selectFromCanvas) return;
        toCanvas(frames[active].contentWindow, { type: 'select', id: e.detail?.id || null });
    });
    shell.addEventListener('pb:save-error', (e) => {
        // 419: session expired — say what to do instead of just "not saved"
        const expired = e.detail?.status === 419 || e.detail?.status === 401;
        setStatus(expired ? text('SessionExpired', 'Session expired — reload the page') : text('SaveError', 'Not saved'), 'critical');
    });

    /* ── Page settings: unsaved changes ── */
    const form = document.getElementById('page-form');
    const saveButtons = [...document.querySelectorAll('[data-save-page], #page-form [type="submit"]')];
    function markDirty() {
        if (formDirty) return;
        formDirty = true;
        saveButtons.forEach((btn) => btn.setAttribute('data-dirty', ''));
        setStatus(text('Unsaved', 'Unsaved page settings'), 'caution');
    }
    if (form) {
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', () => { formDirty = false; });
        // Invalid field in a hidden tab or collapsed section: take the user there,
        // otherwise the browser blocks saving without any visible notice
        form.addEventListener('invalid', (e) => {
            showSide?.('page');
            e.target.closest('details')?.setAttribute('open', '');
            requestAnimationFrame(() => e.target.focus?.());
        }, true);
    }
    // On leave: still send a pending block change; ask for confirmation if
    // page settings are unsaved or saving the blocks has failed
    window.addEventListener('beforeunload', (e) => {
        const b = builder();
        b?.flushSave();
        if (!formDirty && !b?.hasUnsavedBlocks) return;
        e.preventDefault();
        e.returnValue = '';
    });
    window.addEventListener('pagehide', () => builder()?.flushSave());

    /* ── Undo / redo ── */
    const undoBtn = $('[data-history="undo"]');
    const redoBtn = $('[data-history="redo"]');
    // disabled and aria-disabled together (x-ui.button sets both when disabling)
    const setEnabled = (btn, on) => {
        if (!btn) return;
        btn.disabled = !on;
        if (on) btn.removeAttribute('aria-disabled');
        else btn.setAttribute('aria-disabled', 'true');
    };
    shell.addEventListener('pb:history', (e) => {
        setEnabled(undoBtn, e.detail.undo);
        setEnabled(redoBtn, e.detail.redo);
    });
    undoBtn?.addEventListener('click', () => builder()?.undo());
    redoBtn?.addEventListener('click', () => builder()?.redo());

    /* ── Keyboard shortcuts ── */
    const shortcutsDialog = document.getElementById('pe-shortcuts');
    $$('[data-open-shortcuts]').forEach((btn) => btn.addEventListener('click', () => shortcutsDialog?.showModal()));
    shortcutsDialog?.addEventListener('click', (e) => { if (e.target === shortcutsDialog || e.target.closest('[data-close-shortcuts]')) shortcutsDialog.close(); });

    function isTyping(target) {
        return !!target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName) || !!target.closest?.('.tox'));
    }

    // k: { key, ctrl, shift, alt } — from a keydown here or from the preview
    function handleShortcut(k, event) {
        const b = builder();
        if (!b) return false;
        const key = k.key.toLowerCase();
        let name = null;
        if (k.ctrl && key === 's') {
            if (!form) return false;
            if (window.tinymce) window.tinymce.triggerSave();
            form.requestSubmit();
            return true;
        }
        // Open dialogs (block dialog, delete, overview) have their own keys
        if (document.querySelector('dialog[open]') || b.deleteDialogOpen || b.mediaModalOpen) return false;
        if (k.ctrl && !k.shift && key === 'z') name = 'undo';
        else if (k.ctrl && ((k.shift && key === 'z') || key === 'y')) name = 'redo';
        else if (k.ctrl && key === 'd') name = 'duplicate';
        else if (!k.ctrl && !k.alt && (key === 'delete' || key === 'backspace')) name = 'delete';
        else if (k.alt && key === 'arrowup') name = 'up';
        else if (k.alt && key === 'arrowdown') name = 'down';
        else if (key === 'escape') name = 'deselect';
        else if (k.key === '?') { shortcutsDialog?.showModal(); return true; }
        if (!name) return false;
        const done = b.shortcut(name);
        if (done !== false && event) event.preventDefault();
        return done !== false;
    }

    document.addEventListener('keydown', (e) => {
        const mod = e.ctrlKey || e.metaKey;
        // Ctrl+S even while typing; everything else not in fields (they have their own undo)
        if (!(mod && e.key.toLowerCase() === 's') && isTyping(e.target)) return;
        const handled = handleShortcut({ key: e.key, ctrl: mod, shift: e.shiftKey, alt: e.altKey }, e);
        if (handled) e.preventDefault();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-page-editor]').forEach(init);
});
