import Sortable from 'sortablejs';
import { blockTypes } from './block-types.js';
import { iconEl, iconHtml } from '../ui/icons.js';

/* ── helpers ── */
function h(tag, cls, ...children) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    for (const c of children.flat(Infinity)) {
        if (c == null || c === false) continue;
        el.append(typeof c === 'string' ? document.createTextNode(c) : c);
    }
    return el;
}

// Lucide SVG instead of <i class="bi …"> — the Bootstrap font is not
// loaded. icons.js maps bi names via the shared alias table.
function icon(name, extra = '') {
    return iconEl(name, extra);
}

function generateId() {
    return 'block-' + Math.random().toString(36).substring(2, 11);
}

// Unique ids so that every label points at its field (<label for>)
let fieldSeq = 0;
function fieldId() {
    return 'pb-field-' + (++fieldSeq);
}

// Language tag as accepted by App\Support\BlockDesign::lang (BCP 47)
const LANG_RE = /^[a-z]{2,3}(?:[-_][a-z0-9]{1,8})*$/i;

// Headings h2–h6 for the "Heading level" options
const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'];

// Plain objects for data/settings — PHP stores empty ones as [] and keys
// written to an array are lost when saving
function plainObject(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function deepCloneWithNewIds(block) {
    const clone = JSON.parse(JSON.stringify(block));
    clone.id = generateId();
    if (clone.children) clone.children = clone.children.map(deepCloneWithNewIds);
    return clone;
}

/* ── PageBuilder ── */
export class PageBuilder {
    constructor(root, config) {
        this.root = root;
        this.blocks = config.blocks || [];
        this.previewUrl = config.previewUrl || '';
        this.saveUrl = config.saveUrl || '';
        this.csrf = config.csrf || '';
        this.mediaUrl = config.mediaUrl || '';
        this.inputName = config.inputName || 'blocks';
        this.defaultBlocks = config.defaultBlocks || null;
        this.t = config.t || {};

        // Mode "editor" (page editor, pages/edit): only the structure here,
        // the settings of the selected block in a separate element
        // (sidebar), adding via the dialog with search. Without a mode
        // (theme regions): palette, settings in the card, preview.
        this.editorMode = config.mode === 'editor';
        this.settingsHost = this.editorMode && config.settingsHost ? document.querySelector(config.settingsHost) : null;
        this.inputForm = config.inputForm || '';
        this.inserterEl = null;
        this.insertTarget = 'canvas';
        this.insertAfter = null;
        // Selectable icons for cards and buttons (App\Support\ContentIcons)
        this.icons = Array.isArray(config.icons) ? config.icons : [];
        // Installed content languages for the "Language" option: [{ code, name }]
        this.locales = Array.isArray(config.locales) ? config.locales : [];
        // Page editor only: { title: true } when the theme shows the page title
        // as the H1 above the blocks — the accessibility check counts it
        this.headingContext = config.headings || null;
        this.a11yIssues = [];
        this.a11yPanel = null;
        this.a11yBtn = null;
        this._a11yTimer = null;

        // Merge plugin-provided block types
        const pluginTypes = window.__inverysPluginBlockTypes || [];
        for (const pt of pluginTypes) {
            if (!blockTypes.find(t => t.slug === pt.slug)) {
                blockTypes.push(pt);
            }
        }

        this.selectedId = null;
        this.view = 'builder';

        // Collapsed container blocks, remembered per page. Long, nested
        // layouts (theme regions, pages with many sections) would otherwise be
        // endless, because every container shows its content fully expanded.
        this.collapseKey = 'pb-collapsed:' + location.pathname + ':' + this.inputName;
        this.collapsed = new Set();
        try { this.collapsed = new Set(JSON.parse(localStorage.getItem(this.collapseKey) || '[]')); } catch (e) { /* private mode */ }
        this._saveTimer = null;
        // Save sequentially: at most one request in flight, then possibly
        // exactly one with the latest state (otherwise an older one wins)
        this._saving = false;
        this._saveQueued = false;
        this._unsaved = false;
        this._saveFailed = false;
        // Translation without its own blocks: only send blocks on a real
        // change — otherwise "Save" freezes the original's blocks
        this.pristine = !!config.pristine;

        // DOM refs filled during render
        this.canvas = null;
        this.builderView = null;
        this.htmlView = null;
        this.previewView = null;
        this.hiddenInput = null;
        this.countEl = null;
        this.modalEl = null;
        this.toolbarBtns = {};

        // Undo/redo (editor mode): snapshots of the blocks
        this.history = [{ json: JSON.stringify(this.blocks), sel: null }];
        this.future = [];
        this._historyTimer = null;
        this._restoring = false;

        this.render();
        this.refreshCanvas();
        this.updateA11y();

        if (this.editorMode) {
            this.renderSettingsPanel();
            this.emitHistory();
        } else {
            // Restore persisted view tab
            const hash = location.hash.replace('#', '');
            // Always switch once so the active view button is highlighted
            this.switchView(['builder', 'html', 'preview'].includes(hash) ? hash : 'builder', false);
        }

        // Close add-menus on outside click
        document.addEventListener('click', () => this.closeMenus());
    }

    _(key, fb, replace) {
        let text = this.t[key] || fb || key;
        for (const [name, value] of Object.entries(replace || {})) text = text.split(':' + name).join(String(value));
        return text;
    }

    // Name of a block type in the UI language; plugins deliver it already translated
    typeName(bt) {
        return bt ? this._('block_' + bt.slug.replace(/-/g, '_'), bt.name) : '';
    }

    // Size level with technical value: "Small (max-w-5xl)"
    size(level, value) {
        const names = { s: this._('size_small', 'Small'), m: this._('size_medium', 'Medium'), l: this._('size_large', 'Large'), xl: this._('size_xlarge', 'Extra Large'), xxl: this._('size_xxlarge', 'Very large') };
        return `${names[level]} (${value})`;
    }

    /* ================================================================
       FULL RENDER — builds the entire editor shell once
       ================================================================ */
    render() {
        this.root.innerHTML = '';
        this.root.className = 'page-builder';

        if (this.editorMode) {
            this.renderEditorShell();
            return;
        }

        // Toolbar
        this.root.appendChild(this.renderToolbar());
        this.root.appendChild(this.renderA11yPanel());

        // Builder view (palette + canvas)
        // Stacked when narrow (palette on top), side by side from md
        this.builderView = h('div', 'flex flex-col gap-4 md:flex-row');
        this.builderView.appendChild(this.renderPalette());
        const canvasOuter = h('div', 'min-w-0 flex-1 min-h-[500px]');
        const canvasWrap = h('div', 'bg-surface-sunken border border-line rounded-lg p-4');
        this.canvas = h('div', 'page-builder-canvas min-h-[300px] space-y-2');
        this.canvas.dataset.containerId = 'canvas';
        canvasWrap.appendChild(this.canvas);
        canvasOuter.appendChild(canvasWrap);
        this.builderView.appendChild(canvasOuter);
        this.root.appendChild(this.builderView);

        // HTML view
        this.htmlView = h('div', 'min-h-[500px]');
        this.htmlView.style.display = 'none';
        this.root.appendChild(this.htmlView);

        // Preview view
        this.previewView = h('div', 'min-h-[500px]');
        this.previewView.style.display = 'none';
        this.root.appendChild(this.previewView);

        // Hidden input
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = this.inputName;
        this.root.appendChild(this.hiddenInput);

        // Delete modal (appended to body to avoid stacking context issues)
        this.modalEl = this.renderDeleteModal();
        document.body.appendChild(this.modalEl);

    }

    /*
     * Page editor: header with count and collapse/expand, below it the
     * blocks as a tree, at the end "Add block".
     */
    renderEditorShell() {
        this.root.classList.add('page-builder-editor');

        const head = h('div', 'pb-structure-head');
        head.appendChild(h('span', 'pb-structure-title', this._('structure', 'Structure')));
        this.countEl = h('span', 'ui-badge ui-badge-plain');
        head.appendChild(this.countEl);
        const tools = h('div', 'ms-auto flex items-center gap-0.5');
        const tool = (ic, label, handler) => {
            const b = h('button', 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost', icon(ic));
            b.type = 'button';
            b.title = label;
            b.setAttribute('aria-label', label);
            b.addEventListener('click', handler);
            return b;
        };
        tools.append(
            this.renderA11yToggle(),
            tool('chevrons-down-up', this._('collapse_all', 'Collapse all'), () => this.setAllCollapsed(true)),
            tool('chevrons-up-down', this._('expand_all', 'Expand all'), () => this.setAllCollapsed(false)),
        );
        if (this.defaultBlocks) {
            tools.appendChild(tool('rotate-ccw', this._('reset_default', 'Default'), () => {
                this.blocks = JSON.parse(JSON.stringify(this.defaultBlocks));
                this.selectedId = null;
                this.refreshCanvas();
                this.renderSettingsPanel();
                this.autoSave();
            }));
        }
        head.appendChild(tools);
        this.root.appendChild(head);
        this.root.appendChild(this.renderA11yPanel());

        this.canvas = h('div', 'page-builder-canvas pb-structure');
        this.canvas.dataset.containerId = 'canvas';
        this.root.appendChild(this.canvas);

        const add = h('button', 'ui-btn ui-btn-sm ui-btn-secondary pb-structure-add', icon('plus'), this._('add_block', 'Add block'));
        add.type = 'button';
        add.addEventListener('click', () => this.openInserter('canvas'));
        this.root.appendChild(add);


        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = this.inputName;
        // The page settings form lives elsewhere (sidebar)
        if (this.inputForm) this.hiddenInput.setAttribute('form', this.inputForm);
        this.root.appendChild(this.hiddenInput);

        this.modalEl = this.renderDeleteModal();
        document.body.appendChild(this.modalEl);
    }

    /*
     * Settings of the selected block in the sidebar (editor mode):
     * header with type and actions, below it the fields — without a selection a
     * notice with "Add block".
     */
    renderSettingsPanel() {
        if (!this.settingsHost) return;
        const host = this.settingsHost;
        host.innerHTML = '';
        const block = this.selectedId ? this.findBlock(this.selectedId) : null;

        if (!block) {
            this.selectedId = null;
            const empty = h('div', 'pb-panel-empty',
                h('span', 'ui-chip ui-tint-accent', icon('mouse-pointer-click')),
                h('p', 'pb-panel-empty-title', this._('no_selection_title', 'No block selected')),
                h('p', 'ui-help', this._('no_selection_text', 'Select a block in the structure to edit it, or add a new one.')));
            const add = h('button', 'ui-btn ui-btn-sm ui-btn-secondary', icon('plus'), this._('add_block', 'Add block'));
            add.type = 'button';
            add.addEventListener('click', () => this.openInserter('canvas'));
            empty.appendChild(add);
            host.appendChild(empty);
            this.releaseEditors();
            this.emit('select', { id: null });
            return;
        }

        const bt = this.getBlockType(block.type);
        const head = h('div', 'pb-panel-head');
        head.appendChild(h('span', 'ui-chip ui-chip-sm ui-tint-accent', icon(bt?.icon || 'square')));
        const name = h('div', 'min-w-0 flex-1',
            h('p', 'pb-panel-title', this.typeName(bt) || block.type),
            h('p', 'pb-panel-meta', this.getBlockPreview(block).replace(/^— /, '')));
        head.appendChild(name);

        const action = (ic, label, handler, cls = 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost') => {
            const b = h('button', cls, icon(ic));
            b.type = 'button';
            b.title = label;
            b.setAttribute('aria-label', label);
            b.addEventListener('click', handler);
            return b;
        };
        const actions = h('div', 'flex shrink-0 items-center gap-0.5',
            action('chevron-up', this._('move_up', 'Move up'), () => this.moveBlock(block.id, -1)),
            action('chevron-down', this._('move_down', 'Move down'), () => this.moveBlock(block.id, 1)),
            action('copy', this._('duplicate', 'Duplicate'), () => this.duplicateBlock(block.id)),
            action('trash-2', this._('delete', 'Delete'), () => this.showDeleteConfirm(block.id), 'ui-btn ui-btn-xs ui-btn-icon ui-btn-danger'));
        head.appendChild(actions);
        host.appendChild(head);

        const body = h('div', 'pb-panel-body');
        const inner = this.renderBlockSettings(block);
        while (inner.firstChild) body.appendChild(inner.firstChild);
        host.appendChild(body);
        this.releaseEditors();
        this.emit('select', { id: block.id, type: block.type });
    }

    // Rebuild a block's settings (e.g. after picking an image)
    rerenderSettings(block) {
        if (this.editorMode) {
            if (this.selectedId === block.id) this.renderSettingsPanel();
            return;
        }
        const blockEl = this.root.querySelector(`[data-block-id="${block.id}"]`);
        const settings = blockEl?.querySelector('.block-settings');
        if (!settings) return;
        const fresh = this.renderBlockSettings(block);
        fresh.className = settings.className;
        fresh.style.display = settings.style.display;
        settings.replaceWith(fresh);
    }

    // Event on the root element, e.g. pb:saved for the editor preview
    emit(name, detail = {}) {
        this.root.dispatchEvent(new CustomEvent('pb:' + name, { detail, bubbles: true }));
    }

    /* ================================================================
       INSERTER — dialog with search, categories and "Recently used"
       ================================================================ */
    // afterId: new block directly after this one (preview toolbar)
    openInserter(containerId = 'canvas', afterId = null) {
        this.insertTarget = containerId;
        this.insertAfter = afterId;
        if (!this.inserterEl) {
            this.inserterEl = this.renderInserter();
            document.body.appendChild(this.inserterEl);
        }
        this.renderInserterRecent();
        this.inserterSearch.value = '';
        this.filterInserter('');
        if (typeof this.inserterEl.showModal === 'function') this.inserterEl.showModal();
        else this.inserterEl.setAttribute('open', '');
        this.inserterSearch.focus();
    }

    closeInserter() {
        if (!this.inserterEl) return;
        if (typeof this.inserterEl.close === 'function') this.inserterEl.close();
        else this.inserterEl.removeAttribute('open');
    }

    renderInserter() {
        const dialog = document.createElement('dialog');
        dialog.className = 'pb-inserter';
        dialog.setAttribute('aria-labelledby', 'pb-inserter-title');

        const head = h('div', 'pb-inserter-head');
        const title = h('h2', 'ui-card-title', this._('inserter_title', 'Add block'));
        title.id = 'pb-inserter-title';
        const close = h('button', 'ui-btn ui-btn-sm ui-btn-icon ui-btn-ghost', icon('x'));
        close.type = 'button';
        close.setAttribute('aria-label', this._('cancel', 'Cancel'));
        close.addEventListener('click', () => this.closeInserter());
        head.append(title, close);

        const searchWrap = h('label', 'pb-inserter-search', icon('search'));
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'pb-inserter-input';
        search.placeholder = this._('inserter_search', 'Search blocks…');
        search.setAttribute('aria-label', search.placeholder);
        search.autocomplete = 'off';
        search.addEventListener('input', () => this.filterInserter(search.value));
        search.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const first = [...dialog.querySelectorAll('[data-inserter-slug]')].find(el => !el.hidden && !el.closest('[hidden]'));
            if (first) this.chooseBlock(first.dataset.inserterSlug);
        });
        searchWrap.appendChild(search);
        this.inserterSearch = search;

        const body = h('div', 'pb-inserter-body');
        this.inserterRecent = h('section', 'pb-inserter-group');
        body.appendChild(this.inserterRecent);

        const cats = [
            ['layout', this._('layout', 'Layout')],
            ['content', this._('content_cat', 'Content')],
            ['interactive', this._('interactive', 'Interactive')],
        ];
        const known = cats.map(([c]) => c);
        const other = blockTypes.filter(t => !t.internal && !known.includes(t.category));
        if (other.length) cats.push(['__other', this._('other_blocks', 'More')]);

        for (const [cat, label] of cats) {
            const types = cat === '__other' ? other : blockTypes.filter(t => t.category === cat && !t.internal);
            if (!types.length) continue;
            const group = h('section', 'pb-inserter-group');
            group.appendChild(h('h3', 'pb-inserter-label', label));
            const grid = h('div', 'pb-inserter-grid');
            for (const bt of types) grid.appendChild(this.inserterItem(bt));
            group.appendChild(grid);
            body.appendChild(group);
        }

        this.inserterEmpty = h('p', 'pb-inserter-empty', this._('inserter_empty', 'No block found.'));
        this.inserterEmpty.hidden = true;
        body.appendChild(this.inserterEmpty);

        dialog.append(head, searchWrap, body);
        // Clicking the dimmed backdrop closes it
        dialog.addEventListener('click', (e) => { if (e.target === dialog) this.closeInserter(); });
        return dialog;
    }

    inserterItem(bt) {
        const btn = h('button', 'pb-inserter-item', h('span', 'pb-inserter-icon', icon(bt.icon)), h('span', 'pb-inserter-name', this.typeName(bt)));
        btn.type = 'button';
        btn.dataset.inserterSlug = bt.slug;
        btn.dataset.search = (this.typeName(bt) + ' ' + bt.name + ' ' + bt.slug).toLowerCase();
        btn.addEventListener('click', () => this.chooseBlock(bt.slug));
        return btn;
    }

    renderInserterRecent() {
        const box = this.inserterRecent;
        box.innerHTML = '';
        const recent = this.recentBlocks().map(slug => this.getBlockType(slug)).filter(bt => bt && !bt.internal);
        box.hidden = recent.length === 0;
        if (!recent.length) return;
        box.appendChild(h('h3', 'pb-inserter-label', this._('inserter_recent', 'Recently used')));
        const grid = h('div', 'pb-inserter-grid');
        for (const bt of recent) grid.appendChild(this.inserterItem(bt));
        box.appendChild(grid);
    }

    filterInserter(query) {
        const q = query.trim().toLowerCase();
        let visible = 0;
        this.inserterRecent.hidden = q !== '' || this.inserterRecent.childElementCount === 0;
        for (const group of this.inserterEl.querySelectorAll('.pb-inserter-group')) {
            if (group === this.inserterRecent) continue;
            let groupVisible = 0;
            for (const item of group.querySelectorAll('[data-inserter-slug]')) {
                const match = q === '' || item.dataset.search.includes(q);
                item.hidden = !match;
                if (match) groupVisible++;
            }
            group.hidden = groupVisible === 0;
            visible += groupVisible;
        }
        this.inserterEmpty.hidden = visible > 0;
    }

    chooseBlock(slug) {
        this.closeInserter();
        this.rememberBlock(slug);
        if (this.insertAfter) this.insertBlockAfter(this.insertAfter, slug);
        else if (this.insertTarget === 'canvas') this.addBlock(slug);
        else this.addChildBlock(this.insertTarget, slug);
    }

    insertBlockAfter(afterId, slug) {
        const ctx = this.findBlockContext(afterId);
        if (!ctx) { this.addBlock(slug); return; }
        const block = this.createBlock(slug);
        if (!block) return;
        ctx.array.splice(ctx.index + 1, 0, block);
        this.selectedId = block.id;
        this.refreshCanvas();
        this.afterInsert(block.id);
        this.autoSave();
    }

    /* ── Interface for the preview (pages/editor.js) ── */

    // Name per block ID for the labels in the preview
    labels() {
        const out = {};
        const walk = (blocks) => {
            for (const b of blocks) {
                out[b.id] = this.typeName(this.getBlockType(b.type)) || b.type;
                if (b.children) walk(b.children);
            }
        };
        walk(this.blocks);
        return out;
    }

    // Show in the structure: expand collapsed parents, scroll to it
    revealBlock(id) {
        const trail = [];
        const find = (blocks, path) => {
            for (const b of blocks) {
                if (b.id === id) { trail.push(...path); return true; }
                if (b.children && find(b.children, [...path, b.id])) return true;
            }
            return false;
        };
        find(this.blocks, []);
        let changed = false;
        for (const parentId of trail) {
            if (this.collapsed.has(parentId)) { this.setCollapsed(parentId, false); changed = true; }
        }
        if (changed) this.persistCollapsed();
        this.root.querySelector(`.page-builder-block[data-block-id="${id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    // Apply text from inline editing in the preview
    // field: 'title' or a path into a list like 'items.0.text' (buttons,
    // plans). A path only changes what already exists — it creates nothing.
    setField(id, field, value) {
        const block = this.findBlock(id);
        if (!block || typeof field !== 'string' || typeof value !== 'string') return;
        if (!block.data || Array.isArray(block.data)) block.data = {};
        const path = field.split('.');
        if (!path.every((part) => /^(?:[a-z_][a-z0-9_]*|\d+)$/i.test(part))) return;
        let target = block.data;
        for (const part of path.slice(0, -1)) {
            target = target?.[part];
            if (!target || typeof target !== 'object') return;
        }
        const key = path[path.length - 1];
        if (path.length > 1 && typeof target[key] !== 'string') return;
        target[key] = value;
        this.updatePreviewText(block);
        this.syncHiddenInput();
        this.scheduleAutoSave();
    }

    // Toolbar on the block in the preview
    canvasAction(action, id) {
        if (!id || !this.findBlock(id)) return;
        if (action === 'up') this.moveBlock(id, -1);
        else if (action === 'down') this.moveBlock(id, 1);
        else if (action === 'duplicate') this.duplicateBlock(id);
        else if (action === 'delete') this.showDeleteConfirm(id);
        else if (action === 'insert') this.openInserter('canvas', id);
    }

    // Keyboard shortcuts for the selected block (pages/editor.js)
    shortcut(name) {
        const id = this.selectedId;
        if (name === 'undo') return this.undo();
        if (name === 'redo') return this.redo();
        if (name === 'deselect') { this.deselect(); return true; }
        if (!id) return false;
        if (name === 'duplicate') { this.duplicateBlock(id); return true; }
        if (name === 'delete') { this.showDeleteConfirm(id); return true; }
        if (name === 'up') { this.moveBlock(id, -1); return true; }
        if (name === 'down') { this.moveBlock(id, 1); return true; }
        return false;
    }

    recentBlocks() {
        try { return JSON.parse(localStorage.getItem('pb-recent-blocks') || '[]').filter(s => typeof s === 'string'); } catch (e) { return []; }
    }

    rememberBlock(slug) {
        const list = [slug, ...this.recentBlocks().filter(s => s !== slug)].slice(0, 6);
        try { localStorage.setItem('pb-recent-blocks', JSON.stringify(list)); } catch (e) { /* private mode */ }
    }

    renderToolbar() {
        // Wrapping, otherwise the bar runs off the side of the screen on phones
        const bar = h('div', 'mb-4 flex flex-wrap items-center justify-between gap-2');
        const left = h('div', 'flex flex-wrap items-center gap-2');
        const views = [
            ['builder', 'bi-bricks', this._('page_builder', 'Page Builder')],
            ['html', 'bi-code-slash', this._('html_code', 'HTML Code')],
            ['preview', 'bi-eye', this._('preview', 'Preview')],
        ];
        for (const [key, ic, label] of views) {
            const btn = h('button', 'ui-btn ui-btn-sm ui-btn-secondary', icon(ic), label);
            btn.type = 'button';
            btn.addEventListener('click', () => this.switchView(key));
            this.toolbarBtns[key] = btn;
            left.appendChild(btn);
        }
        const right = h('div', 'flex flex-wrap items-center gap-2');
        this.countEl = h('span', 'text-xs text-fg-muted');
        right.appendChild(this.countEl);
        right.appendChild(this.renderA11yToggle());

        // Collapse or expand all containers at once
        const collapseAll = h('button', 'ui-btn ui-btn-xs ui-btn-ghost', icon('chevron-up'), this._('collapse_all', 'Collapse all'));
        collapseAll.type = 'button';
        collapseAll.addEventListener('click', () => this.setAllCollapsed(true));
        const expandAll = h('button', 'ui-btn ui-btn-xs ui-btn-ghost', icon('chevron-down'), this._('expand_all', 'Expand all'));
        expandAll.type = 'button';
        expandAll.addEventListener('click', () => this.setAllCollapsed(false));
        right.append(collapseAll, expandAll);

        if (this.defaultBlocks) {
            const resetBtn = document.createElement('button');
            resetBtn.type = 'button';
            resetBtn.innerHTML = iconHtml('rotate-ccw') + ' ' + this._('reset_default', 'Default');
            resetBtn.className = 'ui-btn ui-btn-xs ui-btn-ghost';
            resetBtn.addEventListener('click', () => {
                this.blocks = JSON.parse(JSON.stringify(this.defaultBlocks));
                this.refreshCanvas();
                this.autoSave();
            });
            right.appendChild(resetBtn);
        }

        bar.appendChild(left);
        bar.appendChild(right);
        return bar;
    }

    switchView(view, persist = true) {
        this.view = view;
        this.builderView.style.display = view === 'builder' ? '' : 'none';
        this.htmlView.style.display = view === 'html' ? '' : 'none';
        this.previewView.style.display = view === 'preview' ? '' : 'none';
        for (const [k, btn] of Object.entries(this.toolbarBtns)) {
            btn.classList.toggle('ui-btn-primary', k === view);
            btn.classList.toggle('ui-btn-secondary', k !== view);
            btn.setAttribute('aria-pressed', k === view ? 'true' : 'false');
        }
        // Remember the active view in the URL — not on startup, otherwise
        // every page with a builder would get #builder appended unasked
        if (persist) history.replaceState(null, '', '#' + view);
        if (view !== 'builder') this.loadPreview();
    }

    renderPalette() {
        // Palette shared with the form builder (ui-palette, page-builder.css)
        const wrap = h('div', 'w-full shrink-0 md:w-56');
        const inner = h('div', 'ui-palette');
        inner.appendChild(h('h3', 'ui-palette-title', this._('blocks_label', 'Blocks')));
        const cats = [
            ['layout', this._('layout', 'Layout')],
            ['content', this._('content_cat', 'Content')],
            ['interactive', this._('interactive', 'Interactive')],
        ];
        const group = h('div', 'ui-palette-groups');
        for (const [cat, label] of cats) {
            const sec = h('div', '');
            sec.appendChild(h('h4', 'ui-palette-label', label));
            const list = h('div', 'ui-palette-list');
            for (const bt of blockTypes.filter(t => t.category === cat && !t.internal)) {
                const btn = h('button', 'ui-palette-item', icon(bt.icon), h('span', 'truncate', this.typeName(bt)));
                btn.title = this.typeName(bt);
                btn.type = 'button';
                btn.addEventListener('click', () => this.addBlock(bt.slug));
                list.appendChild(btn);
            }
            sec.appendChild(list);
            group.appendChild(sec);
        }
        inner.appendChild(group);
        wrap.appendChild(inner);
        return wrap;
    }

    /* ================================================================
       CANVAS — re-renders all blocks inside the drop zone
       ================================================================ */
    refreshCanvas() {
        this.canvas.innerHTML = '';
        this.releaseEditors();
        if (this.blocks.length === 0 && this.editorMode) {
            const empty = h('div', 'pb-structure-empty',
                icon('layers'),
                h('p', '', this._('empty_structure', 'This page has no blocks yet.')));
            const add = h('button', 'ui-btn ui-btn-sm ui-btn-primary', icon('plus'), this._('add_first', 'Add the first block'));
            add.type = 'button';
            add.addEventListener('click', () => this.openInserter('canvas'));
            empty.appendChild(add);
            this.canvas.appendChild(empty);
        } else if (this.blocks.length === 0) {
            this.canvas.classList.add('border-2', 'border-dashed', 'border-line', 'rounded-lg', 'p-4');
            const empty = h('div', 'text-center py-12 text-fg-muted',
                icon('bi-plus-circle', 'text-4xl mb-2 block'),
                h('p', '', this._('empty_canvas', 'Click a block type from the palette to add it')));
            this.canvas.appendChild(empty);
        } else {
            this.canvas.classList.remove('border-2', 'border-dashed', 'border-line', 'rounded-lg', 'p-4');
            for (const block of this.blocks) {
                this.canvas.appendChild(this.renderBlock(block));
            }
        }
        this.initSortables();
        this.updateCount();
        this.syncHiddenInput();
        this.commitHistory();
    }

    refreshContainer(containerId) {
        if (containerId === 'canvas') { this.refreshCanvas(); return; }
        const el = this.root.querySelector(`[data-container-id="${containerId}"]`);
        if (!el) { this.refreshCanvas(); return; }
        const parent = this.findBlock(containerId);
        if (!parent?.children) return;
        // Keep only non-block children (like add buttons, labels)
        el.querySelectorAll(':scope > .page-builder-block').forEach(c => c.remove());
        this.releaseEditors();
        const addBtn = el.querySelector('.add-block-wrap');
        for (const block of parent.children) {
            el.insertBefore(this.renderBlock(block), addBtn);
        }
        this.initSortables();
        this.updateCount();
        this.syncHiddenInput();
    }

    /**
     * Tear down TinyMCE instances of text blocks whose textarea dropped out of
     * the DOM on re-render. Otherwise the ID stays taken — the block gets no
     * editor the next time it is opened — and the orphaned instance
     * throws errors on save (triggerSave).
     */
    releaseEditors() {
        if (!window.tinymce) return;
        for (const editor of window.tinymce.get()) {
            if (!editor.id.startsWith('mce-') || editor.getElement()?.isConnected) continue;
            try { editor.remove(); } catch (e) { /* half initialized: nothing left to salvage */ }
        }
    }

    updateCount() {
        const total = this.countBlocks(this.blocks);
        this.countEl.textContent = total + ' ' + this._('blocks_count', 'block(s)');
    }

    countBlocks(blocks) {
        let n = blocks.length;
        for (const b of blocks) if (b.children) n += this.countBlocks(b.children);
        return n;
    }

    /* ================================================================
       RENDER BLOCK — creates a single block DOM element
       ================================================================ */
    renderBlock(block) {
        const el = h('div', 'page-builder-block bg-surface border border-line rounded-lg');
        el.dataset.blockId = block.id;
        const isSelected = this.selectedId === block.id;
        if (isSelected) el.classList.add(...(this.editorMode ? ['is-selected'] : ['ring-2', 'ring-accent']));

        // Header
        el.appendChild(this.renderBlockHeader(block, el));

        // Settings — lazy: only build form fields when block is selected
        // (editor mode: the settings live in the sidebar)
        if (!this.editorMode) {
            const settings = h('div', 'block-settings px-3 pb-3 border-t border-line ');
            if (isSelected) {
                const inner = this.renderBlockSettings(block);
                while (inner.firstChild) settings.appendChild(inner.firstChild);
            } else {
                settings.dataset.lazy = '1';
            }
            settings.style.display = isSelected ? '' : 'none';
            el.appendChild(settings);
        }

        // Children — collapsible so long layouts stay manageable
        if (block.children !== undefined) {
            const kids = this.renderBlockChildren(block);
            kids.classList.add('block-children');
            kids.hidden = this.collapsed.has(block.id);
            el.appendChild(kids);
        }

        // Select on click
        el.addEventListener('click', (e) => { e.stopPropagation(); this.selectBlock(block.id); });

        return el;
    }

    renderBlockHeader(block, blockEl) {
        const header = h('div', this.editorMode ? 'pb-node-head' : 'flex flex-wrap items-center justify-between gap-2 p-3');
        const hasChildren = block.children !== undefined;
        const isCollapsed = this.collapsed.has(block.id);

        const makeBtn = (ic, handler, cls = 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost', label = '') => {
            const b = h('button', cls, icon(ic));
            b.type = 'button';
            if (label) { b.title = label; b.setAttribute('aria-label', label); }
            b.addEventListener('click', (e) => { e.stopPropagation(); handler(b); });
            return b;
        };

        const left = h('div', 'flex min-w-0 items-center gap-2');
        left.appendChild(icon('bi-grip-vertical', 'text-fg-subtle drag-handle cursor-move'));

        if (hasChildren) {
            const toggle = makeBtn(isCollapsed ? 'chevron-right' : 'chevron-down', () => this.toggleCollapsed(block.id),
                'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost', this._('toggle_children', 'Show/hide contents'));
            toggle.dataset.collapseToggle = block.id;
            toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            left.appendChild(toggle);
        }

        // The name may shrink: longer names (e.g. "Accordion Section") must not
        // break the row with the actions on narrow screens
        const typeName = this.typeName(this.getBlockType(block.type)) || block.type;
        const nameEl = h('span', 'pb-node-name min-w-0 truncate text-sm font-medium', typeName);
        nameEl.title = typeName;

        // The preview gives way first (flex-shrink 100): in narrow columns only
        // "C…" was left otherwise instead of the block name
        const previewEl = h('span', 'pb-node-preview min-w-0 truncate text-xs text-fg-muted', this.getBlockPreview(block));
        previewEl.style.flexShrink = '100';

        left.append(
            icon(this.getBlockType(block.type)?.icon || 'bi-square', 'shrink-0 text-accent'),
            nameEl,
            previewEl,
        );

        if (hasChildren) {
            // Number of contained blocks — tells what is inside when collapsed
            left.appendChild(h('span', 'ui-badge ui-badge-plain shrink-0', String(this.countBlocks(block.children))));
        }

        header.appendChild(left);

        // Editor mode: the actions sit in the settings header (sidebar),
        // the narrow structure column only shows name and preview
        if (this.editorMode) {
            // Keyboard-reachable: Tab to the block, Enter/Space selects it
            header.tabIndex = 0;
            header.setAttribute('role', 'button');
            header.setAttribute('aria-label', [typeName, this.getBlockPreview(block)].filter(Boolean).join(' '));
            if (this.selectedId === block.id) header.setAttribute('aria-current', 'true');
            header.addEventListener('keydown', (e) => {
                if (e.target !== header || (e.key !== 'Enter' && e.key !== ' ')) return;
                e.preventDefault();
                e.stopPropagation();
                this.selectBlock(block.id);
            });
            return header;
        }

        // Actions always visible — not only on hover
        const right = h('div', 'flex shrink-0 items-center gap-0.5');
        right.appendChild(makeBtn('chevron-up', () => this.moveBlock(block.id, -1), undefined, this._('move_up', 'Move up')));
        right.appendChild(makeBtn('chevron-down', () => this.moveBlock(block.id, 1), undefined, this._('move_down', 'Move down')));
        right.appendChild(makeBtn('copy', () => this.duplicateBlock(block.id), undefined, this._('duplicate', 'Duplicate')));
        right.appendChild(makeBtn('trash-2', () => this.showDeleteConfirm(block.id), 'ui-btn ui-btn-xs ui-btn-icon ui-btn-danger', this._('delete', 'Delete')));

        header.appendChild(right);
        return header;
    }

    toggleCollapsed(id) {
        this.setCollapsed(id, !this.collapsed.has(id));
        this.persistCollapsed();
    }

    setCollapsed(id, collapsed) {
        if (collapsed) this.collapsed.add(id); else this.collapsed.delete(id);

        const blockEl = this.root.querySelector('.page-builder-block[data-block-id="' + id + '"]');
        if (!blockEl) return;
        const kids = blockEl.querySelector(':scope > .block-children');
        if (kids) kids.hidden = collapsed;
        const toggle = blockEl.querySelector('[data-collapse-toggle="' + id + '"]');
        if (toggle) {
            toggle.replaceChildren(icon(collapsed ? 'chevron-right' : 'chevron-down'));
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    }

    setAllCollapsed(collapsed) {
        const walk = (blocks) => {
            for (const b of blocks) {
                if (b.children !== undefined) { this.setCollapsed(b.id, collapsed); walk(b.children); }
            }
        };
        walk(this.blocks);
        this.persistCollapsed();
    }

    persistCollapsed() {
        try { localStorage.setItem(this.collapseKey, JSON.stringify([...this.collapsed])); } catch (e) { /* private mode */ }
    }

    /* ================================================================
       BLOCK CHILDREN — columns, accordion, tabs, generic
       ================================================================ */
    renderBlockChildren(block) {
        if (block.type === 'columns') return this.renderColumnsChildren(block);
        if (block.type === 'accordion') return this.renderAccordionChildren(block);
        if (block.type === 'tabs') return this.renderTabsChildren(block);
        return this.renderGenericChildren(block);
    }

    renderColumnsChildren(block) {
        // Column count as a variable; when space runs short (phone, nested),
        // page-builder.css stacks the columns via a container query
        const outer = h('div', 'pb-cols');
        const wrap = h('div', 'pb-cols-grid');
        wrap.style.setProperty('--pb-cols', String(block.children.length));
        outer.appendChild(wrap);
        for (let i = 0; i < block.children.length; i++) {
            const col = block.children[i];
            const zone = h('div', 'pb-col');
            zone.dataset.containerId = col.id;
            zone.appendChild(h('div', 'pb-col-label', this._('column_n', 'Column :n', { n: i + 1 })));
            if (col.children) {
                for (const child of col.children) zone.appendChild(this.renderBlock(child));
            }
            zone.appendChild(this.renderAddButton(col.id));
            wrap.appendChild(zone);
        }
        return outer;
    }

    renderAccordionChildren(block) {
        const wrap = h('div', 'mx-3 mb-3 space-y-2');
        const container = h('div', 'space-y-2');
        container.dataset.containerId = block.id;
        for (const item of block.children) {
            const el = h('div', 'page-builder-block border border-accent-line rounded-lg overflow-hidden');
            el.dataset.blockId = item.id;
            // Header
            const hdr = h('div', 'flex items-center gap-2 px-3 py-2 bg-caution-soft');
            hdr.appendChild(icon('bi-grip-vertical', 'text-fg-subtle drag-handle cursor-move'));
            hdr.appendChild(icon('bi-chevron-bar-expand', 'text-accent text-xs'));
            const titleInput = document.createElement('input');
            titleInput.type = 'text';
            titleInput.value = item.data?.title || '';
            titleInput.placeholder = this._('section_title', 'Section title');
            titleInput.setAttribute('aria-label', titleInput.placeholder);
            titleInput.className = 'flex-1 text-sm border-line-strong  rounded px-2 py-1';
            titleInput.addEventListener('click', e => e.stopPropagation());
            titleInput.addEventListener('input', (e) => { item.data.title = e.target.value; this.syncHiddenInput(); this.scheduleAutoSave(); });
            hdr.appendChild(titleInput);
            const delBtn = h('button', 'p-1 text-critical hover:text-critical', icon('bi-trash', 'text-xs'));
            delBtn.type = 'button';
            delBtn.setAttribute('aria-label', this._('delete_named', 'Delete “:name”', { name: item.data?.title || this.typeName(this.getBlockType(item.type)) }));
            delBtn.addEventListener('click', (e) => { e.stopPropagation(); this.showDeleteConfirm(item.id); });
            hdr.appendChild(delBtn);
            el.appendChild(hdr);
            // Children zone
            const zone = h('div', 'p-2 space-y-2 min-h-[40px]');
            zone.dataset.containerId = item.id;
            if (item.children) {
                for (const child of item.children) zone.appendChild(this.renderBlock(child));
            }
            zone.appendChild(this.renderAddButton(item.id));
            el.appendChild(zone);
            container.appendChild(el);
        }
        wrap.appendChild(container);
        const addSec = h('button', 'flex items-center gap-1 px-2 py-1 text-xs text-accent hover:text-accent font-medium',
            icon('bi-plus'), ` ${this._('add_section', 'Add Section')}`);
        addSec.type = 'button';
        addSec.addEventListener('click', (e) => { e.stopPropagation(); this.addChildBlock(block.id, 'accordion-item'); });
        wrap.appendChild(addSec);
        return wrap;
    }

    renderTabsChildren(block) {
        const wrap = h('div', 'mx-3 mb-3 space-y-2');
        const container = h('div', 'space-y-2');
        container.dataset.containerId = block.id;
        for (const item of block.children) {
            const el = h('div', 'page-builder-block border border-accent-line rounded-lg overflow-hidden');
            el.dataset.blockId = item.id;
            const hdr = h('div', 'flex items-center gap-2 px-3 py-2 bg-accent-soft');
            hdr.appendChild(icon('bi-grip-vertical', 'text-fg-subtle drag-handle cursor-move'));
            hdr.appendChild(icon('bi-window-stack', 'text-accent text-xs'));
            const titleInput = document.createElement('input');
            titleInput.type = 'text';
            titleInput.value = item.data?.title || '';
            titleInput.placeholder = this._('tab_title', 'Tab title');
            titleInput.setAttribute('aria-label', titleInput.placeholder);
            titleInput.className = 'flex-1 text-sm border-line-strong  rounded px-2 py-1';
            titleInput.addEventListener('click', e => e.stopPropagation());
            titleInput.addEventListener('input', (e) => { item.data.title = e.target.value; this.syncHiddenInput(); this.scheduleAutoSave(); });
            hdr.appendChild(titleInput);
            const delBtn = h('button', 'p-1 text-critical hover:text-critical', icon('bi-trash', 'text-xs'));
            delBtn.type = 'button';
            delBtn.setAttribute('aria-label', this._('delete_named', 'Delete “:name”', { name: item.data?.title || this.typeName(this.getBlockType(item.type)) }));
            delBtn.addEventListener('click', (e) => { e.stopPropagation(); this.showDeleteConfirm(item.id); });
            hdr.appendChild(delBtn);
            el.appendChild(hdr);
            const zone = h('div', 'p-2 space-y-2 min-h-[40px]');
            zone.dataset.containerId = item.id;
            if (item.children) {
                for (const child of item.children) zone.appendChild(this.renderBlock(child));
            }
            zone.appendChild(this.renderAddButton(item.id));
            el.appendChild(zone);
            container.appendChild(el);
        }
        wrap.appendChild(container);
        const addTab = h('button', 'flex items-center gap-1 px-2 py-1 text-xs text-accent hover:text-accent font-medium',
            icon('bi-plus'), ` ${this._('add_tab', 'Add Tab')}`);
        addTab.type = 'button';
        addTab.addEventListener('click', (e) => { e.stopPropagation(); this.addChildBlock(block.id, 'tab-item'); });
        wrap.appendChild(addTab);
        return wrap;
    }

    renderGenericChildren(block) {
        const wrap = h('div', 'mx-3 mb-3 ml-6 pl-3 border-l-2 border-accent-line space-y-2');
        wrap.dataset.containerId = block.id;
        if (block.children) {
            for (const child of block.children) wrap.appendChild(this.renderBlock(child));
        }
        wrap.appendChild(this.renderAddButton(block.id, this._('add_block', 'Add block')));
        return wrap;
    }

    renderAddButton(containerId, label) {
        if (this.editorMode) {
            const wrap = h('div', 'add-block-wrap');
            const btn = h('button', 'pb-add-inline', icon('plus'), label || this._('add_block', 'Add block'));
            btn.type = 'button';
            btn.addEventListener('click', (e) => { e.stopPropagation(); this.openInserter(containerId); });
            wrap.appendChild(btn);
            return wrap;
        }
        const wrap = h('div', 'relative add-block-wrap');
        const btn = h('button', 'w-full flex items-center justify-center gap-1 px-2 py-1 text-xs text-fg-muted hover:text-accent border border-dashed border-line-strong rounded hover:border-accent transition-colors',
            icon('bi-plus'), label ? ` ${label}` : '');
        btn.type = 'button';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.closeMenus();
            menu.style.display = menu.style.display === 'none' ? '' : 'none';
        });
        const menu = h('div', 'absolute left-0 bottom-full mb-1 z-50 bg-surface border border-line rounded-lg shadow-lg py-1 w-48 max-h-64 overflow-y-auto add-block-menu');
        menu.style.display = 'none';
        for (const bt of blockTypes.filter(t => !t.internal)) {
            const item = h('button', 'w-full flex items-center gap-2 px-3 py-1.5 text-left text-xs text-fg hover:bg-surface-hover',
                icon(bt.icon, 'text-fg-subtle'), this.typeName(bt));
            item.type = 'button';
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                this.addChildBlock(containerId, bt.slug);
                menu.style.display = 'none';
            });
            menu.appendChild(item);
        }
        wrap.appendChild(btn);
        wrap.appendChild(menu);
        return wrap;
    }

    closeMenus() {
        this.root.querySelectorAll('.add-block-menu').forEach(m => m.style.display = 'none');
    }

    /* ================================================================
       BLOCK SETTINGS — inline form for the selected block
       ================================================================ */
    renderBlockSettings(block) {
        const wrap = h('div', 'space-y-3 pt-2');
        const type = block.type;
        block.data = plainObject(block.data);
        block.settings = plainObject(block.settings);
        const d = block.data;
        const s = block.settings;
        const onChange = () => { this.syncHiddenInput(); this.scheduleAutoSave(); };
        // Free-form Tailwind fields: for advanced users only, collapsed at the end
        const advanced = [];
        // Block-specific accessibility options; the language follows for every block
        const a11y = [];

        if (type === 'container') {
            wrap.appendChild(this.selectField(this._('max_width', 'Max Width'), s.full_width ? 'full' : (s.max_width || 'max-w-7xl'), { 'full': this._('full_width', 'Full Width'), 'max-w-5xl': this.size('s', 'max-w-5xl'), 'max-w-6xl': this.size('m', 'max-w-6xl'), 'max-w-7xl': this.size('l', 'max-w-7xl') }, v => { s.full_width = v === 'full'; if (v !== 'full') s.max_width = v; onChange(); }));
            wrap.appendChild(this.selectField(this._('padding', 'Padding'), s.padding, { 'py-0': this._('none', 'None'), 'py-2': this.size('s', 'py-2'), 'py-4': this.size('m', 'py-4'), 'py-8': this.size('l', 'py-8'), 'py-12': this.size('xl', 'py-12') }, v => { s.padding = v; onChange(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('surface', 'Background'), s.style || '', this.surfaceOptions(), v => { s.style = v; onChange(); }),
                this.selectField(this._('text_align', 'Text alignment'), s.align || '', this.alignOptions(), v => { s.align = v; onChange(); }),
                h('p', 'ui-help', this._('surface_help', 'With a background, a container that is not full width becomes a rounded panel.')),
                ...this.backgroundFields(s, onChange, d),
                this.selectField(this._('block_gap', 'Space between blocks'), s.gap || '', { none: this._('none', 'None'), sm: this._('size_small', 'Small'), '': this._('size_normal', 'Normal'), lg: this._('size_large', 'Large') }, v => { s.gap = v; onChange(); }),
                this.anchorField(s, onChange),
            ));
            a11y.push(...this.landmarkFields(s, d, 'div', onChange));
            advanced.push(this.textField(this._('background', 'Background'), s.background, v => { s.background = v; onChange(); }, this._('example', 'e.g. :value', { value: 'bg-surface-sunken' })));
        } else if (type === 'columns') {
            wrap.appendChild(this.selectField(this._('columns', 'Columns'), s.columns, Object.fromEntries(['1', '2', '3', '4', '5', '6'].map(n => [n, n === '1' ? this._('column_one', '1 Column') : this._('columns_n', ':count Columns', { count: n })])), v => { s.columns = v; this.syncColumnCount(block); onChange(); }));
            wrap.appendChild(this.selectField(this._('gap', 'Gap'), s.gap, { 'gap-2': this.size('s', 'gap-2'), 'gap-4': this.size('m', 'gap-4'), 'gap-6': this.size('l', 'gap-6'), 'gap-8': this.size('xl', 'gap-8'), 'gap-12': this.size('xxl', 'gap-12') }, v => { s.gap = v; onChange(); }));
            const equal = this._('ratio_equal', 'Equal widths');
            const ratios = {
                2: { '': equal, '2-1': '⅔ + ⅓', '1-2': '⅓ + ⅔', '3-2': '⅗ + ⅖', '2-3': '⅖ + ⅗' },
                3: { '': equal, '2-1-1': '½ + ¼ + ¼', '1-2-1': '¼ + ½ + ¼', '1-1-2': '¼ + ¼ + ½' },
            }[parseInt(s.columns) || block.children?.length];
            if (ratios) wrap.appendChild(this.selectField(this._('ratio', 'Split'), s.ratio || '', ratios, v => { s.ratio = v; onChange(); }));
            wrap.appendChild(this.selectField(this._('valign', 'Vertical alignment'), s.valign || '', { '': this._('valign_top', 'Top (equal height)'), center: this._('valign_center', 'Centered') }, v => { s.valign = v; onChange(); }));
        } else if (type === 'section') {
            wrap.appendChild(this.selectField(this._('padding', 'Padding'), s.padding, { 'py-0': this._('none', 'None'), 'py-4': this.size('s', 'py-4'), 'py-8': this.size('m', 'py-8'), 'py-12': this.size('l', 'py-12'), 'py-16': this.size('xl', 'py-16'), 'py-24': this.size('xxl', 'py-24') }, v => { s.padding = v; onChange(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('surface', 'Background'), s.style || '', this.surfaceOptions(), v => { s.style = v; onChange(); }),
                this.selectField(this._('text_align', 'Text alignment'), s.align || '', this.alignOptions(), v => { s.align = v; onChange(); }),
                ...this.backgroundFields(s, onChange, d),
                this.anchorField(s, onChange),
            ));
            a11y.push(...this.landmarkFields(s, d, 'section', onChange));
            advanced.push(this.textField(this._('background', 'Background'), s.background, v => { s.background = v; onChange(); }, this._('example', 'e.g. :value', { value: 'bg-surface-sunken' })));
        } else if (type === 'hero') {
            wrap.appendChild(this.textField(this._('eyebrow', 'Eyebrow'), d.eyebrow, v => { d.eyebrow = v; onChange(); }, this._('eyebrow_help', 'Short label above the title')));
            wrap.appendChild(this.textField(this._('title', 'Title'), d.title, v => { d.title = v; onChange(); this.updatePreviewText(block); }));
            wrap.appendChild(this.textareaField(this._('text', 'Text'), d.text, 3, v => { d.text = v; onChange(); }));
            const buttons = h('div', 'grid gap-3 sm:grid-cols-2');
            buttons.append(
                this.textField(this._('primary_button', 'Main button'), d.primary_text, v => { d.primary_text = v; onChange(); }),
                this.textField(this._('url', 'URL'), d.primary_url, v => { d.primary_url = v; onChange(); }),
                this.textField(this._('secondary_button', 'Second button'), d.secondary_text, v => { d.secondary_text = v; onChange(); }),
                this.textField(this._('url', 'URL'), d.secondary_url, v => { d.secondary_url = v; onChange(); }),
                this.targetField(this._('primary_target', 'Main button opens in'), s.primary_target, v => { s.primary_target = v; onChange(); }),
                this.targetField(this._('secondary_target', 'Second button opens in'), s.secondary_target, v => { s.secondary_target = v; onChange(); }),
            );
            wrap.appendChild(buttons);
            wrap.appendChild(this.imageField(this._('image', 'Image'), d.image, (url, alt, decorative) => { d.image = url; if (alt && !d.image_alt) d.image_alt = alt; if (decorative) s.image_decorative = true; onChange(); if (alt || decorative) this.rerenderSettings(block); }));
            wrap.append(...this.altFields(d.image_alt, s.image_decorative, v => { d.image_alt = v; onChange(); }, v => { s.image_decorative = v; onChange(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('layout_label', 'Layout'), s.layout || 'split', { split: this._('hero_split', 'Text and image side by side'), centered: this._('hero_centered', 'Centered text (no image)') }, v => { s.layout = v; onChange(); }),
                this.selectField(this._('surface', 'Background'), s.style ?? 'glow', this.surfaceOptions(), v => { s.style = v; onChange(); }),
                this.selectField(this._('height', 'Height'), s.size || 'normal', { compact: this._('size_compact', 'Compact'), normal: this._('size_normal', 'Normal'), large: this._('size_large', 'Large') }, v => { s.size = v; onChange(); }),
                this.selectField(this._('title_level', 'Title level'), s.level || 'h1', { h1: this._('title_h1', 'H1 – page intro (once per page)'), h2: this._('title_h2', 'H2 – e.g. call to action further down') }, v => { s.level = v; onChange(); }),
                this.checkboxField(this._('grid_pattern', 'Grid pattern in the background'), s.grid !== false, v => { s.grid = v; onChange(); }),
                ...this.backgroundFields(s, onChange, d),
                this.anchorField(s, onChange),
            ));
        } else if (type === 'stats') {
            wrap.appendChild(this.statsItemsField(block, onChange));
            const animate = this.checkboxField(this._('stats_animate', 'Animate numbers (count up)'), s.animate, v => { s.animate = v; onChange(); });
            wrap.appendChild(this.designGroup(
                this.selectField(this._('style', 'Style'), s.style || 'cards', { cards: this._('stats_cards', 'As cards'), plain: this._('stats_plain', 'Without frame') }, v => { s.style = v; onChange(); }),
                this.selectField(this._('text_align', 'Text alignment'), s.align || 'center', { '': this._('left', 'Left'), center: this._('center', 'Center') }, v => { s.align = v; onChange(); }),
                animate,
                h('p', 'ui-help', this._('stats_animate_help', 'The numbers count up from 0 when they scroll into view — not for visitors who reduce motion. Screen readers and search engines always get the final value.')),
            ));
        } else if (type === 'spacer') {
            wrap.appendChild(this.selectField(this._('height', 'Height'), s.height, { '1rem': this.size('s', '1rem'), '2rem': this.size('m', '2rem'), '3rem': this.size('l', '3rem'), '4rem': this.size('xl', '4rem') }, v => { s.height = v; onChange(); }));
        } else if (type === 'divider') {
            a11y.push(this.checkboxField(this._('divider_decorative', 'Decorative only (hidden from screen readers)'), s.decorative, v => { s.decorative = v; onChange(); }),
                h('p', 'ui-help', this._('divider_decorative_help', 'Switch off when the line separates two topics — screen readers then announce a separator.')));
        } else if (type === 'heading') {
            wrap.appendChild(this.textField(this._('text', 'Text'), d.text, v => { d.text = v; onChange(); this.updatePreviewText(block); }));
            wrap.appendChild(this.textField(this._('eyebrow', 'Eyebrow'), d.eyebrow, v => { d.eyebrow = v; onChange(); }, this._('eyebrow_help', 'Short label above the title')));
            wrap.appendChild(this.selectField(this._('level', 'Level'), d.level, { h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4', h5: 'H5', h6: 'H6' }, v => { d.level = v; onChange(); this.scheduleA11y(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('alignment', 'Alignment'), d.alignment, { left: this._('left', 'Left'), center: this._('center', 'Center'), right: this._('right', 'Right') }, v => { d.alignment = v; onChange(); }),
                this.selectField(this._('style', 'Style'), s.style || '', { '': this._('size_normal', 'Normal'), gradient: this._('gradient_text', 'Gradient text') }, v => { s.style = v; onChange(); }),
            ));
        } else if (type === 'text') {
            wrap.appendChild(this.tinymceField(this._('content_label', 'Content'), block));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('text_size', 'Text size'), s.size || '', { '': this._('size_normal', 'Normal'), lead: this._('size_lead', 'Introduction (larger)'), small: this._('size_small', 'Small') }, v => { s.size = v; onChange(); }),
                this.selectField(this._('text_align', 'Text alignment'), s.align || '', this.alignOptions(), v => { s.align = v; onChange(); }),
                this.selectField(this._('measure', 'Line width'), s.measure || '', { '': this._('measure_full', 'Full width'), readable: this._('measure_readable', 'Readable (narrower)') }, v => { s.measure = v; onChange(); }),
            ));
        } else if (type === 'image') {
            wrap.appendChild(this.imageField(this._('image_source', 'Image Source'), d.src, (url, alt, decorative) => {
                d.src = url; if (alt) d.alt = alt; if (decorative) s.decorative = true; onChange();
                // Refresh settings to show preview
                this.rerenderSettings(block);
            }));
            if (d.src) {
                const preview = h('div', 'border border-line rounded p-2');
                const img = document.createElement('img');
                img.src = window.resolveAssetUrl ? window.resolveAssetUrl(d.src) : d.src;
                img.alt = d.alt || '';
                img.className = 'max-h-32 rounded object-contain mx-auto';
                preview.appendChild(img);
                wrap.appendChild(preview);
            }
            wrap.append(...this.altFields(d.alt, s.decorative, v => { d.alt = v; onChange(); }, v => { s.decorative = v; onChange(); }));
            wrap.appendChild(this.textField(this._('caption', 'Caption'), d.caption, v => { d.caption = v; onChange(); }));
            wrap.appendChild(this.selectField(this._('width', 'Width'), s.width, { full: this._('full_width', 'Full Width'), auto: this._('auto', 'Auto') }, v => { s.width = v; onChange(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('frame', 'Frame'), s.style || '', { '': this._('frame_shadow', 'Frame and shadow'), plain: this._('frame_none', 'No frame') }, v => { s.style = v; onChange(); }),
                this.selectField(this._('image_ratio', 'Aspect ratio'), s.ratio || '', { '': this._('ratio_original', 'Original'), '16-9': '16:9', '4-3': '4:3', '1-1': '1:1', '3-4': '3:4' }, v => { s.ratio = v; onChange(); }),
                this.selectField(this._('corners', 'Corners'), s.corners || '', { '': this._('corners_rounded', 'Rounded'), round: this._('corners_round', 'Round'), square: this._('corners_square', 'Square') }, v => { s.corners = v; onChange(); }),
            ));
            wrap.appendChild(this.textField(this._('link_url', 'Link URL'), d.url, v => { d.url = v; onChange(); }));
        } else if (type === 'video') {
            wrap.appendChild(this.fileField(this._('video_url', 'Video URL'), d.url, 'video', (url) => { d.url = url; onChange(); }));
            wrap.appendChild(this.help(this.textField(this._('video_title', 'Title'), d.title, v => { d.title = v; onChange(); }),
                this._('video_title_help', 'Names the video for screen readers, e.g. “Product tour (2 minutes)”.')));
            wrap.appendChild(this.imageField(this._('poster_image', 'Poster Image'), d.poster, (url) => { d.poster = url; onChange(); }));
            const checks = h('div', 'flex items-center gap-4');
            checks.appendChild(this.checkboxField(this._('controls', 'Controls'), s.controls, v => { s.controls = v; onChange(); }));
            checks.appendChild(this.checkboxField(this._('autoplay', 'Autoplay'), s.autoplay, v => { s.autoplay = v; onChange(); }));
            wrap.appendChild(checks);
            wrap.appendChild(h('p', 'ui-help', this._('video_controls_help', 'While autoplay is on, the controls are always shown so the video can be paused.')));
            // Captions and transcript (WCAG 1.2.2, 1.2.3)
            a11y.push(
                // Picker type "captions": lists the WebVTT files of the library only
                this.help(this.fileField(this._('video_captions', 'Captions (WebVTT file)'), d.captions, 'captions', (url) => { d.captions = url; onChange(); }),
                    this._('video_captions_help', 'Only for your own video files — on YouTube and Vimeo, switch captions on there.')),
                this.langField(s, 'captions_srclang', this._('video_captions_lang', 'Language of the captions'), onChange),
                this.textField(this._('video_captions_label', 'Name of the captions track'), d.captions_label, v => { d.captions_label = v; onChange(); }, this._('example', 'e.g. :value', { value: this._('video_captions_example', 'English captions') })),
                this.help(this.textareaField(this._('video_transcript', 'Transcript'), d.transcript, 5, v => { d.transcript = v; onChange(); }),
                    this._('video_transcript_help', 'Shown below the video as an expandable text. An empty line starts a new paragraph.')),
                this.textField(this._('video_transcript_url', 'Link to a transcript'), d.transcript_url, v => { d.transcript_url = v; onChange(); }),
            );
        } else if (type === 'quote') {
            wrap.appendChild(this.textareaField(this._('quote', 'Quote'), d.text, 3, v => { d.text = v; onChange(); }));
            wrap.appendChild(this.textField(this._('author', 'Author'), d.author, v => { d.author = v; onChange(); }));
            wrap.appendChild(this.textField(this._('source', 'Source'), d.cite, v => { d.cite = v; onChange(); }));
            wrap.appendChild(this.imageField(this._('avatar', 'Photo (optional)'), d.image, (url) => { d.image = url; onChange(); }));
        } else if (type === 'code') {
            wrap.appendChild(this.textField(this._('language', 'Language'), d.language, v => { d.language = v; onChange(); }, 'html, javascript, php...'));
            wrap.appendChild(this.textareaField(this._('code', 'Code'), d.code, 8, v => { d.code = v; onChange(); }, 'font-mono'));
            a11y.push(this.help(this.textField(this._('code_label', 'Name for screen readers'), d.aria_label, v => { d.aria_label = v; onChange(); }, this._('code_label_default', 'Code example')),
                this._('code_label_help', 'The code area can be scrolled with the keyboard; screen readers announce this name.')));
        } else if (type === 'list') {
            wrap.appendChild(this.selectField(this._('style', 'Style'), d.style, { unordered: this._('unordered', 'Unordered (bullets)'), ordered: this._('ordered', 'Ordered (numbers)') }, v => { d.style = v; onChange(); }));
            wrap.appendChild(this.listItemsField(block, onChange));
        } else if (type === 'button') {
            wrap.appendChild(this.textField(this._('text', 'Text'), d.text, v => { d.text = v; onChange(); this.updatePreviewText(block); }));
            wrap.appendChild(this.textField(this._('url', 'URL'), d.url, v => { d.url = v; onChange(); }));
            wrap.appendChild(this.selectField(this._('style', 'Style'), d.style, { primary: this._('primary', 'Primary'), secondary: this._('secondary', 'Secondary'), outline: this._('outline', 'Outline'), ghost: this._('ghost', 'Ghost'), light: this._('light', 'Light'), danger: this._('danger', 'Danger') }, v => { d.style = v; onChange(); }));
            wrap.appendChild(this.targetField(this._('target', 'Target'), d.target, v => { d.target = v; onChange(); }));
            a11y.push(this.ariaLabelField(d.aria_label, v => { d.aria_label = v; onChange(); }));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('size_label', 'Size'), s.size || '', { sm: this._('size_small', 'Small'), '': this._('size_normal', 'Normal'), lg: this._('size_large', 'Large') }, v => { s.size = v; onChange(); }),
                this.selectField(this._('position', 'Position'), s.align || '', { '': this._('left', 'Left'), center: this._('center', 'Center'), right: this._('right', 'Right'), full: this._('full_width', 'Full Width') }, v => { s.align = v; onChange(); }),
                this.iconField(this._('icon', 'Icon'), d.icon, v => { d.icon = v; onChange(); }),
            ));
        } else if (type === 'link-group') {
            wrap.appendChild(this.linksField(block, onChange));
        } else if (type === 'alert') {
            wrap.appendChild(this.textField(this._('title', 'Title'), d.title, v => { d.title = v; onChange(); this.updatePreviewText(block); }));
            wrap.appendChild(this.textareaField(this._('content_label', 'Content'), d.content, 3, v => { d.content = v; onChange(); }));
            wrap.appendChild(this.selectField(this._('type', 'Type'), d.type, { info: this._('info', 'Info'), success: this._('success', 'Success'), warning: this._('warning', 'Warning'), danger: this._('danger', 'Danger') }, v => { d.type = v; onChange(); }));
            a11y.push(
                this.help(this.selectField(this._('alert_role', 'Announcement'), s.role || '', [
                    ['', this._('alert_role_status', 'Status – read out politely (default)')],
                    ['note', this._('alert_role_note', 'Note – not announced, read in place')],
                    ['alert', this._('alert_role_alert', 'Alert – interrupts the screen reader')],
                    ['none', this._('alert_role_none', 'None – plain content')],
                ], v => { s.role = v; onChange(); }), this._('alert_role_help', 'Use “Alert” only for urgent messages; it interrupts whatever a screen reader is reading.')),
                this.headingLevelField(s.title_level, '', v => { s.title_level = v; onChange(); this.scheduleA11y(); }, this._('heading_none_paragraph', 'No heading (paragraph)')),
            );
        } else if (type === 'card') {
            wrap.appendChild(this.textField(this._('title', 'Title'), d.title, v => { d.title = v; onChange(); this.updatePreviewText(block); }));
            wrap.appendChild(this.textareaField(this._('content_label', 'Content'), d.content, 3, v => { d.content = v; onChange(); }));
            wrap.appendChild(this.imageField(this._('image', 'Image'), d.image, (url, alt, decorative) => { d.image = url; if (alt && !d.image_alt) d.image_alt = alt; if (decorative) s.image_decorative = true; onChange(); if (alt || decorative) this.rerenderSettings(block); }));
            wrap.append(...this.altFields(d.image_alt, s.image_decorative, v => { d.image_alt = v; onChange(); }, v => { s.image_decorative = v; onChange(); }));
            wrap.appendChild(this.textField(this._('link_url', 'Link URL'), d.url, v => { d.url = v; onChange(); }));
            wrap.appendChild(this.textField(this._('link_text', 'Link text'), d.link_text, v => { d.link_text = v; onChange(); }, this._('link_text_help', 'Empty: “Read more”')));
            wrap.appendChild(this.targetField(this._('target', 'Target'), s.link_target, v => { s.link_target = v; onChange(); }));
            a11y.push(
                this.headingLevelField(s.title_level, 'h3', v => { s.title_level = v; onChange(); this.scheduleA11y(); }),
                this.ariaLabelField(d.link_aria_label, v => { d.link_aria_label = v; onChange(); }),
            );
            wrap.appendChild(this.designGroup(
                this.iconField(this._('icon', 'Icon'), d.icon, v => { d.icon = v; onChange(); }),
                this.selectField(this._('icon_style', 'Icon style'), s.icon_style || '', { '': this._('icon_soft', 'Soft'), gradient: this._('icon_gradient', 'Gradient') }, v => { s.icon_style = v; onChange(); }),
                this.selectField(this._('style', 'Style'), s.style || '', { '': this._('card_default', 'Card with frame'), tinted: this._('card_tinted', 'Tinted'), plain: this._('card_plain', 'No frame') }, v => { s.style = v; onChange(); }),
                this.selectField(this._('text_align', 'Text alignment'), s.align || '', { '': this._('left', 'Left'), center: this._('center', 'Center') }, v => { s.align = v; onChange(); }),
                this.selectField(this._('image_style', 'Image style'), s.image_style || '', { '': this._('image_cover', 'Cover image'), round: this._('image_round', 'Round portrait'), contain: this._('image_contain', 'Show whole image') }, v => { s.image_style = v; onChange(); }),
            ));
        } else if (type === 'buttons') {
            wrap.appendChild(this.buttonsField(block, onChange));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('size_label', 'Size'), s.size || '', { sm: this._('size_small', 'Small'), '': this._('size_normal', 'Normal'), lg: this._('size_large', 'Large') }, v => { s.size = v; onChange(); }),
                this.selectField(this._('position', 'Position'), s.align || '', this.alignOptions(), v => { s.align = v; onChange(); }),
            ));
        } else if (type === 'pricing') {
            wrap.appendChild(this.plansField(block, onChange));
            a11y.push(this.headingLevelField(s.title_level, 'h3', v => { s.title_level = v; onChange(); this.scheduleA11y(); }));
        } else if (type === 'logos') {
            wrap.appendChild(this.logosField(block, onChange));
            wrap.appendChild(this.designGroup(
                this.selectField(this._('logo_style', 'Display'), s.style || 'muted', { muted: this._('logos_muted', 'Grey, colour on hover'), color: this._('logos_color', 'In colour') }, v => { s.style = v; onChange(); }),
            ));
        } else if (type === 'slider') {
            wrap.appendChild(this.slidesField(block, onChange));
            const opts = h('div', 'space-y-3 pt-3 border-t border-line mt-3');
            const checks = h('div', 'flex items-center gap-4 flex-wrap');
            // Older sliders without the option play automatically (frontend default)
            checks.appendChild(this.checkboxField(this._('autoplay', 'Autoplay'), d.autoplay !== false, v => { d.autoplay = v; onChange(); }));
            checks.appendChild(this.checkboxField(this._('show_arrows', 'Arrows'), d.show_arrows !== false, v => { d.show_arrows = v; onChange(); }));
            checks.appendChild(this.checkboxField(this._('show_dots', 'Dots'), d.show_dots !== false, v => { d.show_dots = v; onChange(); }));
            opts.appendChild(checks);
            opts.appendChild(h('p', 'ui-help', this._('slider_autoplay_help', 'While it plays, visitors see a pause button. It never starts by itself for visitors who reduce motion.')));
            opts.appendChild(this.textField(this._('interval_ms', 'Interval (ms)'), d.interval, v => { d.interval = parseInt(v) || 5000; onChange(); }, '5000'));
            opts.appendChild(this.textField(this._('height', 'Height'), s.height, v => { s.height = v; onChange(); }, '400px'));
            wrap.appendChild(opts);
            a11y.push(
                this.help(this.textField(this._('slider_label', 'Name of the slider'), d.aria_label, v => { d.aria_label = v; onChange(); }, this._('slider_label_default', 'Slideshow')),
                    this._('slider_label_help', 'Screen readers announce it together with “carousel”, e.g. “Customer stories”.')),
                this.headingLevelField(s.title_level, 'h3', v => { s.title_level = v; onChange(); this.scheduleA11y(); }),
            );
        } else if (type === 'accordion') {
            wrap.appendChild(this.checkboxField(this._('accordion_multiple', 'Several sections may be open at once'), s.allow_multiple, v => { s.allow_multiple = v; onChange(); }));
            a11y.push(this.help(this.headingLevelField(s.title_level, '', v => { s.title_level = v; onChange(); this.scheduleA11y(); }, this._('heading_none_button', 'No heading (button only)')),
                this._('accordion_heading_help', 'With a level, screen reader users can jump between the section titles like headings.')));
        } else if (type === 'accordion-item') {
            wrap.appendChild(this.checkboxField(this._('accordion_open', 'Open when the page loads'), s.open, v => { s.open = v; onChange(); }));
        } else if (type === 'tabs') {
            a11y.push(this.help(this.textField(this._('tabs_label', 'Name of the tab list'), d.aria_label, v => { d.aria_label = v; onChange(); }),
                this._('tabs_label_help', 'Optional — screen readers announce it when entering the tabs, e.g. “Plans”.')));
        } else if (window.__inverysBlockSettingsRenderers?.[type]) {
            window.__inverysBlockSettingsRenderers[type](wrap, block, d, s, onChange, this);
        }

        // Accessibility: block-specific options and the language of the block
        wrap.appendChild(this.a11yGroup(s, a11y, onChange));

        // Custom classes for all blocks — collapsed, the select fields above are usually enough
        const more = document.createElement('details');
        more.className = 'pt-2 border-t border-line';
        if (s.class || s.background || s.visibility) more.open = true;
        const summary = h('summary', 'cursor-pointer select-none text-xs font-medium text-fg-muted', this._('advanced', 'Advanced: visibility and CSS classes'));
        summary.addEventListener('click', e => e.stopPropagation());
        const moreBody = h('div', 'space-y-3 pt-2');
        advanced.unshift(this.selectField(this._('visibility', 'Visibility'), s.visibility || '', { '': this._('visibility_all', 'Everywhere'), desktop: this._('visibility_desktop', 'Desktop and tablet only'), mobile: this._('visibility_mobile', 'Mobile only') }, v => { s.visibility = v; onChange(); }));
        moreBody.append(...advanced, this.textField(this._('css_class', 'CSS Class'), s.class, v => { s.class = v; onChange(); }, this._('example', 'e.g. :value', { value: 'my-4 text-center' })));
        more.append(summary, moreBody);
        wrap.appendChild(more);

        return wrap;
    }

    // "Design" section in the block settings
    designGroup(...fields) {
        const group = h('div', 'space-y-3 rounded border border-line bg-surface-sunken/50 p-3');
        group.appendChild(h('div', 'flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-fg-muted', icon('palette'), this._('design', 'Design')));
        group.append(...fields.filter(Boolean));
        return group;
    }

    // "Accessibility" section: the block's own options, then its language
    a11yGroup(s, fields, onChange) {
        const group = h('div', 'space-y-3 rounded border border-line bg-surface-sunken/50 p-3');
        group.appendChild(h('div', 'flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-fg-muted', icon('shield-check'), this._('accessibility', 'Accessibility')));
        group.append(...fields.filter(Boolean));
        group.appendChild(this.help(this.langField(s, 'lang', this._('lang', 'Language of this block'), onChange),
            this._('lang_help', 'For content in a language other than the page, e.g. a quote — screen readers then pronounce it correctly.')));
        return group;
    }

    /*
     * Language picker: "same as the page", the installed content languages
     * or any other BCP 47 code (checked here and in BlockDesign::lang).
     */
    langField(target, key, label, onChange) {
        const value = typeof target[key] === 'string' ? target[key] : '';
        const codes = this.locales.map(l => l.code);
        const isOther = value !== '' && !codes.includes(value);
        const w = h('div', 'space-y-2');

        const error = h('p', 'ui-help text-critical', this._('lang_invalid', 'Not a valid language code — use e.g. fr, en-GB or pt-BR.'));
        error.id = fieldId();
        error.hidden = true;
        const other = this.textField(this._('lang_code', 'Language code'), isOther ? value : '', (v) => {
            target[key] = v.trim();
            validate();
            onChange();
        }, this._('example', 'e.g. :value', { value: 'fr, en-GB, pt-BR' }));
        const otherInput = other.querySelector('input');
        const validate = () => {
            const code = otherInput.value.trim();
            const ok = code === '' || LANG_RE.test(code);
            error.hidden = ok;
            otherInput.setAttribute('aria-invalid', ok ? 'false' : 'true');
            if (ok) otherInput.removeAttribute('aria-errormessage');
            else otherInput.setAttribute('aria-errormessage', error.id);
        };
        other.appendChild(error);
        other.hidden = !isOther;
        if (isOther) validate();

        const options = [
            ['', this._('lang_inherit', 'Same as the page')],
            ...this.locales.map(l => [l.code, `${l.name} (${l.code})`]),
            ['__other', this._('lang_other', 'Other language …')],
        ];
        const select = this.selectField(label, isOther ? '__other' : value, options, (v) => {
            if (v === '__other') {
                other.hidden = false;
                target[key] = otherInput.value.trim();
                otherInput.focus();
            } else {
                other.hidden = true;
                target[key] = v;
            }
            this.scheduleA11y();
            onChange();
        });
        w.append(select, other);
        return w;
    }

    // Element of a container/section and the name of the landmark
    landmarkFields(s, d, def, onChange) {
        const current = s.element || def;
        const label = this.help(this.textField(this._('landmark_label', 'Name of the area'), d.aria_label, v => { d.aria_label = v; onChange(); }),
            this._('landmark_label_help', 'Read out by screen readers, e.g. “Related articles”. Needed when a page has several areas of the same kind.'));
        label.hidden = current === 'div';
        const element = this.help(this.selectField(this._('landmark', 'Type of area'), current, [
            ['div', this._('landmark_div', 'No landmark (div)')],
            ['section', this._('landmark_section', 'Region (section) – needs a name')],
            ['aside', this._('landmark_aside', 'Complementary (aside)')],
            ['nav', this._('landmark_nav', 'Navigation (nav)')],
        ], v => { s.element = v; label.hidden = v === 'div'; onChange(); this.scheduleA11y(); }),
        this._('landmark_help', 'Screen reader users can jump between landmarks.'));
        return [element, label];
    }

    // Alt text with the "decorative" switch; the text field hides while decorative
    altFields(alt, decorative, onAlt, onDecorative) {
        const altField = this.help(this.textField(this._('alt_text', 'Alt Text'), alt, v => { onAlt(v); this.scheduleA11y(); }),
            this._('alt_help', 'Describe what the image shows or, for a link, where it leads.'));
        altField.hidden = !!decorative;
        const check = this.checkboxField(this._('decorative_image', 'Decorative image (no alt text needed)'), decorative, (v) => {
            altField.hidden = v;
            onDecorative(v);
            this.scheduleA11y();
        });
        return [check, altField];
    }

    // Same tab / new tab; legacyLabel: option for entries without a choice yet
    targetField(label, value, onChange, legacyLabel = null) {
        const options = [];
        if (legacyLabel && !value) options.push(['', legacyLabel]);
        options.push(['_self', this._('target_same_tab', 'Same tab')], ['_blank', this._('target_new_tab', 'New tab')]);
        return this.selectField(label, value || (legacyLabel ? '' : '_self'), options, (v) => { onChange(v); this.scheduleA11y(); });
    }

    // Accessible name of a link — replaces the visible text for screen readers
    ariaLabelField(value, onChange) {
        return this.help(this.textField(this._('link_aria_label', 'Accessible name (optional)'), value, v => { onChange(v); this.scheduleA11y(); }),
            this._('link_aria_label_help', 'For screen readers instead of the visible text, e.g. “Read more about our pricing”. Start with the visible words.'));
    }

    // Heading level h2–h6; noneLabel adds "no heading"
    headingLevelField(value, def, onChange, noneLabel = null) {
        const options = noneLabel ? [['', noneLabel]] : [];
        for (const level of HEADING_LEVELS) options.push([level, level.toUpperCase()]);
        return this.selectField(this._('heading_level', 'Heading level'), value || def, options, onChange);
    }

    surfaceOptions() {
        return {
            '': this._('surface_none', 'None'),
            soft: this._('surface_soft', 'Light grey'),
            tint: this._('surface_tint', 'Tinted in the primary colour'),
            glow: this._('surface_glow', 'Glow'),
            gradient: this._('surface_gradient', 'Gradient (light text)'),
            dark: this._('surface_dark', 'Dark (light text)'),
        };
    }

    // Background image with overlay (container, section, hero); decorative unless switched off
    backgroundFields(s, onChange, d = {}) {
        const decorative = s.bg_decorative !== false;
        const alt = this.help(this.textField(this._('bg_alt', 'Alt text of the background image'), d.bg_alt, v => { d.bg_alt = v; onChange(); this.scheduleA11y(); }),
            this._('bg_alt_help', 'Only when the image carries information that the text does not repeat.'));
        alt.hidden = decorative || !s.bg_image;
        const check = this.checkboxField(this._('bg_decorative', 'Background image is decorative'), decorative, (v) => {
            s.bg_decorative = v;
            alt.hidden = v || !s.bg_image;
            onChange();
            this.scheduleA11y();
        });
        return [
            this.imageField(this._('bg_image', 'Background image'), s.bg_image, (url) => { s.bg_image = url; alt.hidden = s.bg_decorative !== false || !url; onChange(); }),
            this.selectField(this._('bg_overlay', 'Over the image'), s.bg_overlay || 'dark', { dark: this._('overlay_dark', 'Darken (light text)'), brand: this._('overlay_brand', 'Brand gradient (light text)'), light: this._('overlay_light', 'Lighten'), none: this._('overlay_none', 'Nothing') }, v => { s.bg_overlay = v; onChange(); }),
            check,
            alt,
        ];
    }

    // Repeatable entries (buttons, plans, logos): one card with fields per entry
    repeaterField(block, key, label, addLabel, newItem, renderItem, onChange) {
        const w = h('div', '');
        const title = h('p', 'block text-xs font-medium text-fg-muted mb-2', label);
        title.id = fieldId();
        w.appendChild(title);
        const list = h('div', 'space-y-3');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', title.id);
        const render = () => {
            list.innerHTML = '';
            (block.data[key] || []).forEach((item, idx) => {
                const card = h('div', 'border border-line rounded p-2 space-y-2');
                const top = h('div', 'flex items-center justify-between');
                top.appendChild(h('span', 'text-xs font-medium text-fg-muted', `${idx + 1}.`));
                const del = h('button', 'p-1 text-critical hover:text-critical', icon('bi-x'));
                del.type = 'button';
                del.setAttribute('aria-label', this._('delete_entry', 'Delete entry :n', { n: idx + 1 }));
                del.addEventListener('click', (e) => { e.stopPropagation(); block.data[key].splice(idx, 1); onChange(); render(); this.scheduleA11y(); });
                top.appendChild(del);
                card.appendChild(top);
                card.append(...renderItem(item));
                list.appendChild(card);
            });
        };
        render();
        w.appendChild(list);
        const add = h('button', 'mt-2 text-xs text-accent hover:text-accent font-medium', icon('bi-plus'), ` ${addLabel}`);
        add.type = 'button';
        add.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!Array.isArray(block.data[key])) block.data[key] = [];
            block.data[key].push(newItem());
            onChange();
            render();
        });
        w.appendChild(add);
        return w;
    }

    buttonsField(block, onChange) {
        const styles = { primary: this._('primary', 'Primary'), secondary: this._('secondary', 'Secondary'), outline: this._('outline', 'Outline'), ghost: this._('ghost', 'Ghost'), light: this._('light', 'Light') };
        return this.repeaterField(block, 'items', this._('buttons_items', 'Buttons'), this._('add_button', 'Add button'),
            () => ({ text: '', url: '#', style: 'secondary', icon: '', target: '_self' }),
            (item) => [
                this.textField(this._('text', 'Text'), item.text, v => { item.text = v; onChange(); }),
                this.textField(this._('url', 'URL'), item.url, v => { item.url = v; onChange(); }),
                // Buttons from before this option open external links in a new tab
                this.targetField(this._('target', 'Target'), item.target, v => { item.target = v; onChange(); }, this._('target_auto', 'Automatic (external links in a new tab)')),
                this.ariaLabelField(item.aria_label, v => { item.aria_label = v; onChange(); }),
                this.selectField(this._('style', 'Style'), item.style || 'primary', styles, v => { item.style = v; onChange(); }),
                this.iconField(this._('icon', 'Icon'), item.icon, v => { item.icon = v; onChange(); }),
            ], onChange);
    }

    plansField(block, onChange) {
        return this.repeaterField(block, 'plans', this._('plans', 'Plans'), this._('add_plan', 'Add plan'),
            () => ({ name: '', price: '', period: '', description: '', features: '', button_text: '', button_url: '#', button_target: '_self', featured: false, badge: '' }),
            (plan) => {
                const row = h('div', 'grid gap-2 sm:grid-cols-2');
                row.append(
                    this.textField(this._('plan_price', 'Price'), plan.price, v => { plan.price = v; onChange(); }, this._('example', 'e.g. :value', { value: '29 €' })),
                    this.textField(this._('plan_period', 'Period'), plan.period, v => { plan.period = v; onChange(); }, this._('example', 'e.g. :value', { value: this._('default_plan_period', 'per month') })),
                );
                const buttons = h('div', 'grid gap-2 sm:grid-cols-2');
                buttons.append(
                    this.textField(this._('plan_button', 'Button text'), plan.button_text, v => { plan.button_text = v; onChange(); }),
                    this.textField(this._('url', 'URL'), plan.button_url, v => { plan.button_url = v; onChange(); }),
                );
                return [
                    this.textField(this._('plan_name', 'Name'), plan.name, v => { plan.name = v; onChange(); }),
                    row,
                    this.textField(this._('plan_description', 'Description'), plan.description, v => { plan.description = v; onChange(); }),
                    this.textareaField(this._('plan_features', 'Features (one per line)'), plan.features, 4, v => { plan.features = v; onChange(); }),
                    buttons,
                    this.targetField(this._('target', 'Target'), plan.button_target, v => { plan.button_target = v; onChange(); }),
                    this.ariaLabelField(plan.button_aria_label, v => { plan.button_aria_label = v; onChange(); }),
                    this.checkboxField(this._('plan_featured', 'Highlight this plan'), plan.featured, v => { plan.featured = v; onChange(); }),
                    this.textField(this._('plan_badge', 'Badge (when highlighted)'), plan.badge, v => { plan.badge = v; onChange(); }, this._('example', 'e.g. :value', { value: this._('default_plan_badge', 'Popular') })),
                ];
            }, onChange);
    }

    logosField(block, onChange) {
        return this.repeaterField(block, 'items', this._('logos_items', 'Logos'), this._('add_logo', 'Add logo'),
            () => ({ image: '', alt: '', url: '', target: '_self' }),
            (item) => [
                this.imageField(this._('image', 'Image'), item.image, (url, alt, decorative) => { item.image = url; if (alt && !item.alt) item.alt = alt; if (decorative) item.decorative = true; onChange(); if (alt || decorative) this.rerenderSettings(block); }),
                ...this.altFields(item.alt, item.decorative, v => { item.alt = v; onChange(); }, v => { item.decorative = v; onChange(); }),
                this.textField(this._('link_url', 'Link URL'), item.url, v => { item.url = v; onChange(); }),
                // Logos from before this option open in a new tab
                this.targetField(this._('target', 'Target'), item.target || '_blank', v => { item.target = v; onChange(); }),
            ], onChange);
    }

    // Jump target for links like "#contact" (container, section, hero)
    anchorField(s, onChange) {
        const field = this.textField(this._('anchor', 'Anchor'), s.anchor, v => { s.anchor = v; onChange(); }, this._('example', 'e.g. :value', { value: 'contact' }));
        field.appendChild(h('p', 'ui-help', this._('anchor_help', 'Link to this block with #name, e.g. in a button.')));
        return field;
    }

    alignOptions() {
        return { '': this._('left', 'Left'), center: this._('center', 'Center'), right: this._('right', 'Right') };
    }

    updatePreviewText(block) {
        const el = this.root.querySelector(`[data-block-id="${block.id}"] > :first-child .pb-node-preview`);
        if (el) el.textContent = this.getBlockPreview(block);
        // Settings header (editor mode)
        const meta = this.settingsHost?.querySelector('.pb-panel-meta');
        if (meta && this.selectedId === block.id) meta.textContent = this.getBlockPreview(block).replace(/^— /, '');
    }

    /* ================================================================
       FORM FIELD HELPERS
       ================================================================ */
    // Label linked to its control (<label for>) — clicking it focuses the field
    fieldLabel(text, control, cls = 'block text-xs font-medium text-fg-muted mb-1') {
        const label = h('label', cls, text);
        if (!control.id) control.id = fieldId();
        label.htmlFor = control.id;
        return label;
    }

    // Help text below a field, announced with it (aria-describedby)
    help(field, text) {
        const note = h('p', 'ui-help mt-1', text);
        note.id = fieldId();
        field.appendChild(note);
        const control = field.querySelector('input:not([type="checkbox"]), select, textarea');
        if (control) control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), note.id].filter(Boolean).join(' '));
        return field;
    }

    textField(label, value, onChange, placeholder) {
        const w = h('div', '');
        const input = document.createElement('input');
        input.type = 'text';
        input.value = value || '';
        input.className = 'w-full text-sm border-line-strong  rounded px-2 py-1.5';
        if (placeholder) input.placeholder = placeholder;
        input.addEventListener('input', () => onChange(input.value));
        input.addEventListener('click', e => e.stopPropagation());
        w.append(this.fieldLabel(label, input), input);
        return w;
    }

    selectField(label, value, options, onChange) {
        const w = h('div', '');
        const select = document.createElement('select');
        select.className = 'w-full text-sm border-line-strong  rounded';
        // Object or list of [value, text] pairs (order is preserved)
        for (const [val, text] of Array.isArray(options) ? options : Object.entries(options)) {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = text;
            if (val === String(value)) opt.selected = true;
            select.appendChild(opt);
        }
        select.addEventListener('change', () => onChange(select.value));
        select.addEventListener('click', e => e.stopPropagation());
        w.append(this.fieldLabel(label, select), select);
        return w;
    }

    textareaField(label, value, rows, onChange, extraCls = '') {
        const w = h('div', '');
        const ta = document.createElement('textarea');
        ta.value = value || '';
        ta.rows = rows;
        ta.className = `w-full text-sm border-line-strong  rounded ${extraCls}`;
        ta.addEventListener('input', () => onChange(ta.value));
        ta.addEventListener('click', e => e.stopPropagation());
        w.append(this.fieldLabel(label, ta), ta);
        return w;
    }

    // Same markup as x-ui.toggle — the backend only uses switches for yes/no
    checkboxField(label, checked, onChange) {
        const w = h('label', 'ui-toggle');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = !!checked;
        cb.className = 'ui-toggle-input';
        cb.addEventListener('change', () => onChange(cb.checked));
        cb.addEventListener('click', e => e.stopPropagation());
        w.append(cb, h('span', 'ui-toggle-track'), h('span', 'ui-toggle-label', label));
        return w;
    }

    // Icon picker as a grid (App\Support\ContentIcons); first tile = no icon
    iconField(label, value, onChange) {
        const w = h('div', '');
        const title = h('span', 'block text-xs font-medium text-fg-muted mb-1', label);
        title.id = fieldId();
        w.appendChild(title);
        // As many columns as fit the width — also in narrow column blocks
        const grid = h('div', 'grid grid-cols-[repeat(auto-fill,minmax(2rem,1fr))] gap-1 max-h-36 overflow-y-auto rounded border border-line bg-surface p-1');
        grid.setAttribute('role', 'group');
        grid.setAttribute('aria-labelledby', title.id);
        const tiles = [];
        let current = value || '';
        const mark = () => {
            for (const [name, tile] of tiles) {
                const on = name === current;
                tile.classList.toggle('bg-accent-soft', on);
                tile.classList.toggle('text-accent', on);
                tile.classList.toggle('text-fg-muted', !on);
                tile.setAttribute('aria-pressed', on ? 'true' : 'false');
            }
        };
        for (const name of ['', ...this.icons]) {
            const tile = h('button', 'flex aspect-square items-center justify-center rounded text-base hover:bg-surface-hover', icon(name || 'minus'));
            tile.type = 'button';
            tile.title = name || this._('icon_none', 'No icon');
            tile.setAttribute('aria-label', tile.title);
            tile.addEventListener('click', (e) => { e.stopPropagation(); current = name; onChange(name); mark(); });
            tiles.push([name, tile]);
            grid.appendChild(tile);
        }
        mark();
        w.appendChild(grid);
        return w;
    }

    // Key figures: value ("98 %") and label per row
    statsItemsField(block, onChange) {
        const w = h('div', '');
        const title = h('p', 'block text-xs font-medium text-fg-muted mb-2', this._('stats_items', 'Key figures'));
        title.id = fieldId();
        w.appendChild(title);
        const list = h('div', 'space-y-2');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', title.id);
        const input = (value, placeholder, label, cls, handler) => {
            const el = document.createElement('input');
            el.type = 'text';
            el.value = value || '';
            el.placeholder = placeholder;
            el.setAttribute('aria-label', label);
            el.className = `${cls} min-w-0 text-sm border-line-strong rounded px-2 py-1.5`;
            el.addEventListener('input', () => handler(el.value));
            el.addEventListener('click', e => e.stopPropagation());
            return el;
        };
        const renderItems = () => {
            list.innerHTML = '';
            (block.data.items || []).forEach((item, idx) => {
                const n = idx + 1;
                const row = h('div', 'flex items-center gap-1');
                row.append(
                    input(item.value, this._('stat_value', 'Value'), this._('stat_value_n', 'Value :n', { n }), 'w-24 shrink-0', v => { item.value = v; onChange(); }),
                    input(item.label, this._('stat_label', 'Label'), this._('stat_label_n', 'Label :n', { n }), 'flex-1', v => { item.label = v; onChange(); }),
                );
                const del = h('button', 'p-1 text-critical hover:text-critical', icon('bi-x'));
                del.type = 'button';
                del.setAttribute('aria-label', this._('delete_entry', 'Delete entry :n', { n }));
                del.addEventListener('click', (e) => { e.stopPropagation(); block.data.items.splice(idx, 1); onChange(); renderItems(); });
                row.appendChild(del);
                list.appendChild(row);
            });
        };
        renderItems();
        w.appendChild(list);

        const addBtn = h('button', 'mt-2 text-xs text-accent hover:text-accent font-medium', icon('bi-plus'), ` ${this._('add_stat', 'Add key figure')}`);
        addBtn.type = 'button';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!Array.isArray(block.data.items)) block.data.items = [];
            block.data.items.push({ value: '', label: '' });
            onChange();
            renderItems();
        });
        w.appendChild(addBtn);
        return w;
    }

    tinymceField(label, block) {
        const w = h('div', '');
        const textarea = document.createElement('textarea');
        textarea.id = 'mce-' + block.id;
        textarea.value = block.data.content || '';
        w.appendChild(this.fieldLabel(label, textarea));
        const wrap = h('div', 'tinymce-wrapper');
        wrap.appendChild(textarea);
        w.appendChild(wrap);

        // Initialize TinyMCE after the element is in the DOM
        requestAnimationFrame(() => {
            // Re-rendered or already removed again: no instance on a dead field
            if (!textarea.isConnected) return;
            this.releaseEditors();
            if (window.initTinyMCE) {
                window.initTinyMCE('#' + textarea.id, {
                    min_height: this.editorMode ? 280 : 200,
                    max_height: this.editorMode ? 560 : 400,
                    setup: (editor) => {
                        editor.on('click', e => e.stopPropagation());
                        // One handler (not onChange AND keyup) and only on changed content —
                        // arrow and shift keys would otherwise save every time and reload the preview
                        editor.on('input change undo redo', () => {
                            if (!editor.initialized || editor.removed) return;
                            const content = editor.getContent();
                            if (content === (block.data.content || '')) return;
                            block.data.content = content;
                            this.syncHiddenInput();
                            this.scheduleAutoSave();
                        });
                    },
                });
            }
        });

        return w;
    }

    imageField(label, value, onSelect) {
        return this.fileField(label, value, 'image', onSelect);
    }

    // URL field with a button to the media library; type: image, video, captions, '' (all files)
    fileField(label, value, type, onSelect) {
        const w = h('div', '');
        const row = h('div', 'flex gap-1');
        const input = document.createElement('input');
        input.type = 'text';
        input.value = value || '';
        input.className = 'flex-1 text-sm border-line-strong  rounded px-2 py-1.5';
        input.placeholder = this._('enter_url_or_select', 'Enter URL or select from library');
        input.addEventListener('input', () => onSelect(input.value));
        input.addEventListener('click', e => e.stopPropagation());
        const browseBtn = h('button', 'px-2 py-1 text-xs bg-accent-soft text-accent border border-accent-line rounded hover:bg-accent-soft whitespace-nowrap',
            icon('bi-images'), ` ${this._('library', 'Library')}`);
        browseBtn.type = 'button';
        browseBtn.setAttribute('aria-label', `${this._('library', 'Library')}: ${label}`);
        browseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.openMediaBrowser((url, alt, decorative) => { input.value = url; onSelect(url, alt, decorative); }, type);
        });
        w.appendChild(this.fieldLabel(label, input));
        row.appendChild(input);
        row.appendChild(browseBtn);
        w.appendChild(row);
        if (type === 'image' && this.mediaUrl) w.appendChild(this.focalHint());
        return w;
    }

    // Image fields: the crop follows the focal point of the media item (set in the media library)
    focalHint() {
        const link = h('a', 'underline hover:text-accent', this._('focal_hint_link', 'Media library'),
            h('span', 'sr-only', ' ' + this._('focal_hint_new_tab', '(opens in a new tab)')));
        link.href = this.mediaUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.addEventListener('click', e => e.stopPropagation());
        return h('p', 'mt-1 text-xs text-fg-muted', this._('focal_hint', 'Cropped images keep the focal point set in the media library.') + ' ', link);
    }

    listItemsField(block, onChange) {
        const w = h('div', '');
        const title = h('p', 'block text-xs font-medium text-fg-muted mb-2', this._('items', 'Items'));
        title.id = fieldId();
        w.appendChild(title);
        const list = h('div', 'space-y-2');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', title.id);

        const renderItems = () => {
            list.innerHTML = '';
            (block.data.items || []).forEach((item, idx) => {
                const row = h('div', 'flex items-center gap-1');
                const input = document.createElement('input');
                input.type = 'text';
                input.value = item;
                input.setAttribute('aria-label', this._('item_n', 'Item :n', { n: idx + 1 }));
                input.className = 'flex-1 text-sm border-line-strong  rounded px-2 py-1.5';
                input.addEventListener('input', () => { block.data.items[idx] = input.value; onChange(); });
                input.addEventListener('click', e => e.stopPropagation());
                const del = h('button', 'p-1 text-critical hover:text-critical', icon('bi-x'));
                del.type = 'button';
                del.setAttribute('aria-label', this._('delete_entry', 'Delete entry :n', { n: idx + 1 }));
                del.addEventListener('click', (e) => { e.stopPropagation(); block.data.items.splice(idx, 1); onChange(); renderItems(); });
                row.appendChild(input);
                row.appendChild(del);
                list.appendChild(row);
            });
        };
        renderItems();
        w.appendChild(list);

        const addBtn = h('button', 'mt-2 text-xs text-accent hover:text-accent font-medium', icon('bi-plus'), ` ${this._('add_item', 'Add Item')}`);
        addBtn.type = 'button';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!block.data.items) block.data.items = [];
            block.data.items.push('');
            onChange();
            renderItems();
        });
        w.appendChild(addBtn);
        return w;
    }

    linksField(block, onChange) {
        const w = h('div', '');
        const title = h('p', 'block text-xs font-medium text-fg-muted mb-2', this._('links', 'Links'));
        title.id = fieldId();
        w.appendChild(title);
        const list = h('div', 'space-y-3');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', title.id);

        const renderLinks = () => {
            list.innerHTML = '';
            (block.data.links || []).forEach((link, idx) => {
                const n = idx + 1;
                const card = h('div', 'border border-line rounded p-2 space-y-2');
                const topRow = h('div', 'flex items-center gap-1');
                const textInput = document.createElement('input');
                textInput.type = 'text';
                textInput.value = link.text || '';
                textInput.placeholder = this._('text', 'Text');
                textInput.setAttribute('aria-label', this._('link_text_n', 'Text of link :n', { n }));
                textInput.className = 'flex-1 text-sm border-line-strong  rounded px-2 py-1.5';
                textInput.addEventListener('input', () => { link.text = textInput.value; onChange(); this.scheduleA11y(); });
                textInput.addEventListener('click', e => e.stopPropagation());
                const del = h('button', 'p-1 text-critical hover:text-critical', icon('bi-x'));
                del.type = 'button';
                del.setAttribute('aria-label', this._('delete_entry', 'Delete entry :n', { n }));
                del.addEventListener('click', (e) => { e.stopPropagation(); block.data.links.splice(idx, 1); onChange(); renderLinks(); this.scheduleA11y(); });
                topRow.appendChild(textInput);
                topRow.appendChild(del);
                const urlInput = document.createElement('input');
                urlInput.type = 'text';
                urlInput.value = link.url || '';
                urlInput.placeholder = this._('url', 'URL');
                urlInput.setAttribute('aria-label', this._('link_url_n', 'URL of link :n', { n }));
                urlInput.className = 'w-full text-sm border-line-strong  rounded px-2 py-1.5';
                urlInput.addEventListener('input', () => { link.url = urlInput.value; onChange(); });
                urlInput.addEventListener('click', e => e.stopPropagation());
                card.appendChild(topRow);
                card.appendChild(urlInput);
                card.appendChild(this.targetField(this._('target', 'Target'), link.target, v => { link.target = v; onChange(); }));
                card.appendChild(this.ariaLabelField(link.aria_label, v => { link.aria_label = v; onChange(); }));
                list.appendChild(card);
            });
        };
        renderLinks();
        w.appendChild(list);

        const addBtn = h('button', 'mt-2 text-xs text-accent hover:text-accent font-medium', icon('bi-plus'), ` ${this._('add_link', 'Add Link')}`);
        addBtn.type = 'button';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!block.data.links) block.data.links = [];
            block.data.links.push({ text: '', url: '#', target: '_self' });
            onChange();
            renderLinks();
        });
        w.appendChild(addBtn);
        return w;
    }

    slidesField(block, onChange) {
        const w = h('div', '');
        const title = h('p', 'block text-xs font-medium text-fg-muted mb-2', this._('slides', 'Slides'));
        title.id = fieldId();
        w.appendChild(title);
        const list = h('div', 'space-y-3');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', title.id);

        const renderSlides = () => {
            list.innerHTML = '';
            (block.data.slides || []).forEach((slide, idx) => {
                const name = `${this._('slide', 'Slide')} ${idx + 1}`;
                const card = h('div', 'border border-line rounded p-2 space-y-2');
                card.setAttribute('role', 'group');
                card.setAttribute('aria-label', name);
                const topRow = h('div', 'flex items-center justify-between');
                topRow.appendChild(h('span', 'text-xs font-medium text-fg-muted', name));
                const del = h('button', 'p-1 text-critical hover:text-critical', icon('bi-x'));
                del.type = 'button';
                del.setAttribute('aria-label', this._('delete_named', 'Delete “:name”', { name }));
                del.addEventListener('click', (e) => { e.stopPropagation(); block.data.slides.splice(idx, 1); onChange(); renderSlides(); this.scheduleA11y(); });
                topRow.appendChild(del);
                card.appendChild(topRow);

                card.appendChild(this.imageField(this._('image', 'Image'), slide.image, (url, alt, decorative) => {
                    slide.image = url;
                    if (alt && !slide.alt) slide.alt = alt;
                    if (decorative) slide.decorative = true;
                    onChange();
                    this.rerenderSettings(block);
                }));
                card.append(...this.altFields(slide.alt, slide.decorative, v => { slide.alt = v; onChange(); }, v => { slide.decorative = v; onChange(); }));

                const input = (key, placeholder) => {
                    const el = document.createElement('input');
                    el.type = 'text';
                    el.value = slide[key] || '';
                    el.placeholder = placeholder;
                    el.setAttribute('aria-label', `${placeholder} (${name})`);
                    el.className = 'w-full text-sm border-line-strong  rounded px-2 py-1.5';
                    el.addEventListener('input', () => { slide[key] = el.value; onChange(); this.scheduleA11y(); });
                    el.addEventListener('click', e => e.stopPropagation());
                    return el;
                };
                card.appendChild(input('title', this._('title', 'Title')));
                card.appendChild(input('text', this._('text', 'Text')));
                card.appendChild(input('url', this._('url', 'URL')));
                card.appendChild(this.targetField(this._('target', 'Target'), slide.target, v => { slide.target = v; onChange(); }));
                card.appendChild(this.ariaLabelField(slide.aria_label, v => { slide.aria_label = v; onChange(); }));

                list.appendChild(card);
            });
        };
        renderSlides();
        w.appendChild(list);

        const addBtn = h('button', 'mt-2 text-xs text-accent hover:text-accent font-medium', icon('bi-plus'), ` ${this._('add_slide', 'Add Slide')}`);
        addBtn.type = 'button';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!block.data.slides) block.data.slides = [];
            block.data.slides.push({ image: '', title: '', text: '', url: '', target: '_self' });
            onChange();
            renderSlides();
        });
        w.appendChild(addBtn);
        return w;
    }

    /* ================================================================
       ACCESSIBILITY CHECK — runs over the block tree in the browser
       ================================================================ */

    /**
     * Problems an editor can fix in the block options. Each issue:
     * { id, type, severity: 'error' | 'warning' | 'info', message }.
     * Errors and warnings are counted on the toolbar button; infos are hints.
     */
    checkAccessibility() {
        const issues = [];
        const headings = [];
        const add = (block, severity, key, fallback, replace) => {
            issues.push({ id: block.id, type: block.type, severity, message: this._(key, fallback, replace) });
        };
        const blank = (v) => typeof v !== 'string' || v.trim() === '';
        const level = (tag) => parseInt(String(tag).slice(1), 10) || 0;
        const external = (url) => /^https?:\/\//i.test(url || '') && !String(url).startsWith(location.origin);
        const newTabInfo = (block) => add(block, 'info', 'a11y_new_tab', 'A link opens in a new tab. Screen readers announce this; only use it where leaving the page would lose work.');
        const imageAlt = (block, hasAlt, linked, decorative) => {
            if (linked && (decorative || !hasAlt)) add(block, 'error', 'a11y_linked_image_alt', 'A linked image has no alt text, so the link has no name. Describe where it leads.');
            else if (!decorative && !hasAlt) add(block, 'error', 'a11y_image_alt', 'An image has no alt text. Describe it or mark it as decorative.');
        };

        const visit = (block, parent) => {
            const d = plainObject(block.data);
            const s = plainObject(block.settings);

            if (typeof s.lang === 'string' && s.lang.trim() !== '' && !LANG_RE.test(s.lang.trim())) {
                add(block, 'error', 'a11y_lang_invalid', 'The language code “:code” is not valid.', { code: s.lang });
            }
            if (s.bg_image && s.bg_decorative === false && blank(d.bg_alt)) {
                add(block, 'error', 'a11y_bg_alt', 'The background image is marked as informative but has no alt text.');
            }

            switch (block.type) {
                case 'container':
                case 'section':
                    if (['aside', 'nav'].includes(s.element) && blank(d.aria_label)) {
                        add(block, 'info', 'a11y_landmark_label', 'Give this area a name so screen readers can tell it apart from others of the same kind.');
                    }
                    break;
                case 'hero':
                    if (d.image && (s.layout || 'split') === 'split') imageAlt(block, !blank(d.image_alt), false, !!s.image_decorative);
                    if (!blank(d.title)) headings.push({ block, level: s.level === 'h2' ? 2 : 1 });
                    if ((s.primary_target === '_blank' && !blank(d.primary_text)) || (s.secondary_target === '_blank' && !blank(d.secondary_text))) newTabInfo(block);
                    break;
                case 'heading':
                    if (blank(d.text)) add(block, 'warning', 'a11y_heading_empty', 'The heading is empty.');
                    else headings.push({ block, level: level(d.level || 'h2') });
                    break;
                case 'text': {
                    const html = typeof d.content === 'string' ? d.content : '';
                    for (const m of html.matchAll(/<h([1-6])\b/gi)) headings.push({ block, level: parseInt(m[1], 10) });
                    if (/<img\b(?![^>]*\balt\s*=)[^>]*>/i.test(html)) add(block, 'warning', 'a11y_text_image_alt', 'The text contains an image without alt text.');
                    break;
                }
                case 'image':
                    if (d.src) imageAlt(block, !blank(d.alt ?? d.caption), !blank(d.url), !!s.decorative);
                    break;
                case 'card':
                    if (d.image && !s.image_decorative && blank(d.image_alt)) {
                        if (blank(d.title)) imageAlt(block, false, false, false);
                        else add(block, 'warning', 'a11y_card_alt', 'The card image has no alt text of its own — the title is read out instead. Describe the image or mark it as decorative.');
                    }
                    if (!blank(d.title)) headings.push({ block, level: level(s.title_level || 'h3') });
                    if (!blank(d.url)) {
                        if (blank(d.link_text) && blank(d.link_aria_label)) add(block, 'warning', 'a11y_generic_link', 'The link only says “Read more”. Add a link text or an accessible name that says where it leads.');
                        if (s.link_target === '_blank') newTabInfo(block);
                    }
                    break;
                case 'button':
                    if (blank(d.text) && blank(d.aria_label)) add(block, 'error', 'a11y_link_empty', 'A button or link has no text.');
                    if (d.target === '_blank') newTabInfo(block);
                    break;
                case 'buttons':
                    if ((Array.isArray(d.items) ? d.items : []).some(i => !blank(i?.text) && (i.target === '_blank' || (!i.target && external(i.url))))) newTabInfo(block);
                    break;
                case 'link-group': {
                    const links = Array.isArray(d.links) ? d.links : [];
                    if (links.some(l => blank(l?.text) && blank(l?.aria_label))) add(block, 'error', 'a11y_link_empty', 'A button or link has no text.');
                    if (links.some(l => l?.target === '_blank')) newTabInfo(block);
                    break;
                }
                case 'logos': {
                    const items = (Array.isArray(d.items) ? d.items : []).filter(i => i && !blank(i.image));
                    items.forEach(i => imageAlt(block, !blank(i.alt), !blank(i.url), !!i.decorative));
                    if (items.some(i => !blank(i.url) && (i.target || '_blank') === '_blank')) newTabInfo(block);
                    break;
                }
                case 'pricing': {
                    const plans = (Array.isArray(d.plans) ? d.plans : []).filter(p => p && !blank(p.name));
                    if (plans.length) headings.push({ block, level: level(s.title_level || 'h3') });
                    if (plans.some(p => !blank(p.button_text) && p.button_target === '_blank')) newTabInfo(block);
                    break;
                }
                case 'slider': {
                    const slides = Array.isArray(d.slides) ? d.slides.filter(x => x && typeof x === 'object') : [];
                    if (d.autoplay !== false && slides.length > 1) add(block, 'warning', 'a11y_slider_autoplay', 'The slider changes automatically. A pause button is shown, but slides that only change on request are easier to read.');
                    slides.forEach((slide, i) => {
                        if (slide.image && !slide.decorative && blank(slide.alt)) {
                            if (blank(slide.title)) imageAlt(block, false, false, false);
                            else add(block, 'warning', 'a11y_slide_alt', 'Slide :n: the image has no alt text of its own — the title is read out instead.', { n: i + 1 });
                        }
                        if (!blank(slide.url) && blank(slide.aria_label) && slides.filter(x => !blank(x.url)).length > 1) {
                            add(block, 'warning', 'a11y_slide_link', 'Slide :n: the link only says “Learn more”. Add an accessible name that says where it leads.', { n: i + 1 });
                        }
                    });
                    if (slides.some(x => !blank(x.title))) headings.push({ block, level: level(s.title_level || 'h3') });
                    if (slides.some(x => !blank(x.url) && x.target === '_blank')) newTabInfo(block);
                    break;
                }
                case 'video':
                    if (!blank(d.url)) {
                        const embedded = /youtu\.?be|vimeo\.com/i.test(d.url);
                        if (blank(d.title)) add(block, 'warning', 'a11y_video_title', 'The video has no title. Screen readers announce it to say what the video is about.');
                        if (embedded) add(block, 'info', 'a11y_video_embed_captions', 'Make sure captions are switched on for this video on YouTube or Vimeo.');
                        else if (blank(d.captions)) add(block, 'error', 'a11y_video_captions', 'The video has no captions. Add a WebVTT file.');
                    }
                    break;
                case 'alert':
                    if (s.title_level && !blank(d.title)) headings.push({ block, level: level(s.title_level) });
                    break;
                case 'accordion-item':
                    if (blank(d.title)) add(block, 'error', 'a11y_accordion_title', 'An accordion section has no title, so its button has no name.');
                    else if (parent?.type === 'accordion' && plainObject(parent.settings).title_level) headings.push({ block, level: level(parent.settings.title_level) });
                    break;
                case 'tab-item':
                    if (blank(d.title)) add(block, 'error', 'a11y_tab_title', 'A tab has no title.');
                    break;
            }

            for (const child of Array.isArray(block.children) ? block.children : []) visit(child, block);
        };
        for (const block of this.blocks) visit(block, null);

        // Heading structure — only where the page around the blocks is known
        if (this.headingContext && this.blocks.length) {
            // The theme leaves out the page title when the page starts with a hero
            const titleH1 = !!this.headingContext.title && this.blocks[0]?.type !== 'hero';
            let previous = titleH1 ? 1 : 0;
            let h1s = titleH1 ? 1 : 0;
            for (const { block, level: current } of headings) {
                if (current === 1) {
                    h1s++;
                    if (h1s > 1) {
                        if (titleH1) add(block, 'error', 'a11y_h1_title', 'The page title is already the H1 of this page. Use H2 here.');
                        else add(block, 'error', 'a11y_h1_multiple', 'There is more than one H1 on this page. Use H2 for further headings.');
                    }
                } else if (previous && current > previous + 1) {
                    add(block, 'warning', 'a11y_heading_skip', 'A heading level is skipped: H:from is followed by H:to.', { from: previous, to: current });
                }
                previous = current;
            }
            if (!h1s) add(headings[0]?.block || this.blocks[0], 'warning', 'a11y_h1_missing', 'The page has no H1. Make the main heading at the top an H1 (e.g. in the hero).');
        }

        const order = { error: 0, warning: 1, info: 2 };
        return issues.sort((a, b) => order[a.severity] - order[b.severity]);
    }

    // Re-check shortly after a change (typing)
    scheduleA11y() {
        clearTimeout(this._a11yTimer);
        this._a11yTimer = setTimeout(() => this.updateA11y(), 400);
    }

    updateA11y() {
        clearTimeout(this._a11yTimer);
        if (!this.a11yBtn) return;
        this.a11yIssues = this.checkAccessibility();
        const problems = this.a11yIssues.filter(i => i.severity !== 'info').length;
        const errors = this.a11yIssues.some(i => i.severity === 'error');
        this.a11yBadge.textContent = String(problems);
        this.a11yBadge.classList.toggle('text-critical', errors);
        const label = problems
            ? this._('a11y_button_issues', 'Accessibility check: :count problem(s)', { count: problems })
            : this._('a11y_button_ok', 'Accessibility check: no problems found');
        this.a11yBtn.title = label;
        this.a11yBtn.setAttribute('aria-label', label);
        if (this.a11yPanel && !this.a11yPanel.hidden) this.renderA11yList();
    }

    renderA11yToggle() {
        this.a11yPanelId = this.a11yPanelId || fieldId();
        this.a11yBadge = h('span', 'ui-badge ui-badge-plain', '0');
        const btn = h('button', 'ui-btn ui-btn-xs ui-btn-ghost gap-1', icon('shield-check'), this.a11yBadge);
        btn.type = 'button';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', this.a11yPanelId);
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleA11yPanel();
        });
        this.a11yBtn = btn;
        return btn;
    }

    renderA11yPanel() {
        const panel = h('section', 'pb-a11y-panel mb-3 space-y-2 rounded-lg border border-line bg-surface p-3 text-sm');
        panel.id = this.a11yPanelId = this.a11yPanelId || fieldId();
        panel.hidden = true;
        const titleId = fieldId();
        const head = h('div', 'flex items-center gap-2');
        const title = h('h2', 'text-xs font-semibold uppercase tracking-wider text-fg-muted', this._('a11y_title', 'Accessibility check'));
        title.id = titleId;
        panel.setAttribute('aria-labelledby', titleId);
        const close = h('button', 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost ms-auto', icon('x'));
        close.type = 'button';
        close.setAttribute('aria-label', this._('a11y_close', 'Close the accessibility check'));
        close.addEventListener('click', (e) => { e.stopPropagation(); this.toggleA11yPanel(false); });
        head.append(icon('shield-check', 'text-accent'), title, close);
        this.a11yList = h('div', 'space-y-1');
        panel.append(head, this.a11yList);
        panel.addEventListener('click', e => e.stopPropagation());
        this.a11yPanel = panel;
        return panel;
    }

    toggleA11yPanel(open) {
        if (!this.a11yPanel) return;
        const show = open ?? this.a11yPanel.hidden;
        this.a11yPanel.hidden = !show;
        this.a11yBtn?.setAttribute('aria-expanded', show ? 'true' : 'false');
        if (show) {
            this.updateA11y();
            this.renderA11yList();
            this.a11yPanel.querySelector('button')?.focus();
        } else {
            this.a11yBtn?.focus();
        }
    }

    renderA11yList() {
        const list = this.a11yList;
        list.innerHTML = '';
        if (!this.a11yIssues.length) {
            list.appendChild(h('p', 'flex items-center gap-2 text-fg-muted', icon('circle-check', 'text-positive'), this._('a11y_none', 'No problems found. Also check colours, link texts and the reading order in the preview.')));
            return;
        }
        const icons = { error: ['circle-alert', 'text-critical'], warning: ['triangle-alert', 'text-caution'], info: ['info', 'text-accent'] };
        const severity = {
            error: this._('a11y_error', 'Problem'),
            warning: this._('a11y_warning', 'Warning'),
            info: this._('a11y_info', 'Hint'),
        };
        const ul = h('ul', 'space-y-1');
        for (const issue of this.a11yIssues) {
            const [ic, cls] = icons[issue.severity];
            const name = this.typeName(this.getBlockType(issue.type)) || issue.type;
            const btn = h('button', 'flex w-full items-start gap-2 rounded px-2 py-1.5 text-left text-xs hover:bg-surface-hover',
                icon(ic, `mt-0.5 shrink-0 ${cls}`),
                h('span', 'sr-only', severity[issue.severity] + ': '),
                h('span', 'min-w-0', h('span', 'font-medium text-fg', name), ' — ', h('span', 'text-fg-muted', issue.message)));
            btn.type = 'button';
            btn.addEventListener('click', (e) => { e.stopPropagation(); this.focusBlock(issue.id); });
            ul.appendChild(h('li', '', btn));
        }
        list.appendChild(ul);
    }

    // Select a block from the check and bring it into view
    focusBlock(id) {
        if (!this.findBlock(id)) return;
        this.revealBlock(id);
        if (this.editorMode || this.selectedId !== id) this.selectBlock(id);
        const el = this.root.querySelector(`.page-builder-block[data-block-id="${id}"]`);
        el?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        const head = el?.querySelector(':scope > .pb-node-head');
        head?.focus?.();
    }

    /* ================================================================
       MEDIA BROWSER MODAL
       ================================================================ */
    // type: image (default), video, captions (WebVTT files), '' = all files
    openMediaBrowser(callback, type = 'image') {
        const overlay = h('div', 'fixed inset-0 z-[9999] flex items-center justify-center bg-ink-950/40 backdrop-blur-sm');
        const modal = h('div', 'bg-surface rounded-lg shadow-xl w-full max-w-3xl max-h-[80vh] flex flex-col mx-4');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', this._('media_library', 'Media Library'));
        // While open: no editor shortcuts behind it (Del, Ctrl+Z, Escape)
        this._mediaOpen = true;
        const header = h('div', 'flex items-center justify-between p-4 border-b border-line');
        header.appendChild(h('h3', 'text-sm font-semibold text-fg', this._('media_library', 'Media Library')));
        const headerRight = h('div', 'flex items-center gap-2');
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = this._('search', 'Search...');
        searchInput.setAttribute('aria-label', searchInput.placeholder);
        searchInput.className = 'text-sm border-line-strong  rounded px-2 py-1';
        let searchTimer;
        searchInput.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadMedia(searchInput.value), 300); });
        const closeBtn = h('button', 'text-fg-muted hover:text-fg-muted p-1', icon('bi-x-lg'));
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', this._('cancel', 'Cancel'));
        closeBtn.addEventListener('click', close);
        headerRight.appendChild(searchInput);
        headerRight.appendChild(closeBtn);
        header.appendChild(headerRight);
        const grid = h('div', 'flex-1 overflow-y-auto p-4');
        modal.appendChild(header);
        modal.appendChild(grid);
        overlay.appendChild(modal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        const escHandler = (e) => { if (e.key === 'Escape') close(); };
        document.addEventListener('keydown', escHandler);
        document.body.appendChild(overlay);

        const self = this;
        function close() { overlay.remove(); document.removeEventListener('keydown', escHandler); self._mediaOpen = false; }

        // Only the response to the latest search counts — older ones may arrive later
        let searchSeq = 0;
        function loadMedia(search = '') {
            const seq = ++searchSeq;
            grid.innerHTML = '';
            grid.appendChild(h('div', 'flex items-center justify-center py-12 text-fg-muted', icon('bi-arrow-clockwise', 'animate-spin text-xl mr-2')));
            const params = new URLSearchParams();
            if (type) params.set('type', type);
            if (search) params.set('search', search);
            fetch(`${self.mediaUrl}?${params}`, { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (seq !== searchSeq) return;
                    grid.innerHTML = '';
                    if (!data.data?.length) {
                        const empty = type === 'image' ? self._('no_images', 'No images found') : self._('no_files', 'No media files found');
                        grid.appendChild(h('div', 'text-center py-12 text-fg-muted', icon(type === 'image' ? 'bi-image' : 'file-text', 'text-3xl mb-2 block'), h('p', '', empty)));
                        return;
                    }
                    const g = h('div', 'grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-2');
                    for (const item of data.data) {
                        const btn = h('button', 'group aspect-square border-2 border-transparent hover:border-accent rounded-lg overflow-hidden bg-surface-sunken focus:outline-none focus:border-accent');
                        btn.type = 'button';
                        if (String(item.mime_type || 'image/').startsWith('image/')) {
                            const img = document.createElement('img');
                            img.src = item.thumbnail || item.url;
                            img.alt = item.name;
                            img.className = 'w-full h-full object-cover';
                            btn.appendChild(img);
                        } else {
                            // Other files (captions, videos): icon and name instead of a thumbnail
                            btn.classList.add('flex', 'flex-col', 'items-center', 'justify-center', 'gap-1', 'p-1', 'text-xs', 'text-fg-muted');
                            btn.append(icon('file-text', 'text-xl'), h('span', 'w-full truncate', item.name));
                        }
                        // Alt text and the "decorative" flag of the media library are taken over
                        btn.addEventListener('click', () => { callback(item.storage_path, item.alt_text, !!item.is_decorative); close(); });
                        g.appendChild(btn);
                    }
                    grid.appendChild(g);
                })
                .catch(() => { grid.innerHTML = ''; grid.appendChild(h('div', 'text-center py-12 text-critical', self._('error_loading_media', 'Could not load the media library.'))); });
        }
        loadMedia();
    }

    /* ================================================================
       DELETE MODAL
       ================================================================ */
    renderDeleteModal() {
        const overlay = document.createElement('div');
        Object.assign(overlay.style, { position: 'fixed', inset: '0', zIndex: '99999', display: 'none', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(4px)' });

        const card = document.createElement('div');
        Object.assign(card.style, { width: '26rem', borderRadius: '1rem', overflow: 'hidden', boxShadow: '0 25px 50px rgba(0,0,0,0.3)' });
        card.className = 'bg-surface';

        const body = document.createElement('div');
        Object.assign(body.style, { padding: '2rem', textAlign: 'center' });

        const iconWrap = document.createElement('div');
        Object.assign(iconWrap.style, { width: '3.5rem', height: '3.5rem', borderRadius: '50%', margin: '0 auto 1rem', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#fef2f2', border: '2px solid #fecaca' });
        const trashIcon = icon('bi-trash', 'text-xl');
        trashIcon.style.color = '#ef4444';
        iconWrap.appendChild(trashIcon);
        body.appendChild(iconWrap);

        const title = document.createElement('h3');
        title.textContent = this._('confirm_delete', 'Do you really want to delete this block?');
        title.className = 'text-fg';
        Object.assign(title.style, { fontSize: '1.05rem', fontWeight: '600', marginBottom: '0.5rem' });
        body.appendChild(title);

        const subtitle = document.createElement('p');
        subtitle.textContent = this._('confirm_delete_sub', 'This action cannot be undone.');
        subtitle.className = 'text-fg-muted';
        Object.assign(subtitle.style, { fontSize: '0.85rem' });
        body.appendChild(subtitle);

        const footer = document.createElement('div');
        Object.assign(footer.style, { display: 'flex', gap: '0.75rem', padding: '0 2rem 1.5rem' });

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.textContent = this._('cancel', 'Cancel');
        cancelBtn.className = 'text-fg bg-surface-sunken hover:bg-surface-sunken ';
        Object.assign(cancelBtn.style, { flex: '1', padding: '0.6rem 1rem', borderRadius: '0.5rem', fontSize: '0.875rem', fontWeight: '500', cursor: 'pointer', border: 'none', transition: 'background 0.15s' });
        cancelBtn.addEventListener('click', () => { overlay.style.display = 'none'; });

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.innerHTML = iconHtml('trash-2') + ' ' + this._('delete', 'Delete');
        Object.assign(deleteBtn.style, { flex: '1', padding: '0.6rem 1rem', borderRadius: '0.5rem', fontSize: '0.875rem', fontWeight: '600', cursor: 'pointer', border: 'none', background: '#ef4444', color: '#fff', transition: 'background 0.15s' });
        deleteBtn.addEventListener('mouseenter', () => { deleteBtn.style.background = '#dc2626'; });
        deleteBtn.addEventListener('mouseleave', () => { deleteBtn.style.background = '#ef4444'; });
        deleteBtn.addEventListener('click', () => { this.executeDelete(); });
        this._deleteBtn = deleteBtn;
        overlay.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); overlay.style.display = 'none'; }
        });

        footer.appendChild(cancelBtn);
        footer.appendChild(deleteBtn);
        card.appendChild(body);
        card.appendChild(footer);
        overlay.appendChild(card);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.style.display = 'none'; });
        return overlay;
    }

    showDeleteConfirm(id) {
        this._deleteId = id;
        this.modalEl.style.display = 'flex';
        this.modalEl.setAttribute('role', 'dialog');
        this.modalEl.setAttribute('aria-modal', 'true');
        this._deleteBtn?.focus();
    }

    get mediaModalOpen() {
        return !!this._mediaOpen;
    }

    get deleteDialogOpen() {
        return this.modalEl?.style.display === 'flex';
    }

    executeDelete() {
        this.modalEl.style.display = 'none';
        if (this._deleteId) {
            this.removeBlock(this._deleteId);
            this._deleteId = null;
        }
    }

    /* ================================================================
       TOAST
       ================================================================ */
    showToast(message, type = 'success') {
        // Shared backend toast (ui/toast.js) instead of a copy of its own
        if (window.uiToast) window.uiToast(message, type === 'error' ? 'critical' : 'positive');
    }

    /* ================================================================
       PREVIEW / HTML VIEWS
       ================================================================ */
    async loadPreview() {
        const loading = h('div', 'flex items-center justify-center py-20 text-fg-muted', icon('bi-arrow-clockwise', 'animate-spin text-2xl mr-2'), this._('loading', 'Loading...'));
        const target = this.view === 'html' ? this.htmlView : this.previewView;
        target.innerHTML = '';
        target.appendChild(loading);
        try {
            const res = await fetch(this.previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                body: JSON.stringify({ blocks: JSON.stringify(this.blocks) }),
            });
            const data = await res.json();
            const html = data.html || `<p class="text-fg-muted">${this._('no_content', 'No content')}</p>`;
            target.innerHTML = '';
            if (this.view === 'html') {
                const pre = h('pre', 'bg-ink-900 text-positive border border-line-strong rounded-lg p-6 text-sm font-mono overflow-x-auto max-h-[70vh] overflow-y-auto whitespace-pre-wrap');
                const code = h('code', '');
                code.textContent = html;
                pre.appendChild(code);
                target.appendChild(pre);
            } else {
                const wrap = h('div', 'bg-surface border border-line rounded-lg p-6');
                const prose = h('div', 'prose dark:prose-invert max-w-none');
                prose.innerHTML = html;
                wrap.appendChild(prose);
                target.appendChild(wrap);
            }
        } catch {
            target.innerHTML = '';
            target.appendChild(h('p', 'text-critical text-center py-12', this._('preview_error', 'The preview could not be loaded.')));
        }
    }

    /* ================================================================
       SORTABLEJS — no Alpine conflict, just works
       ================================================================ */
    initSortables() {
        this.root.querySelectorAll('[data-container-id]').forEach(el => {
            if (el._sortable) return;
            el._sortable = new Sortable(el, {
                group: 'page-builder-blocks',
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                draggable: '.page-builder-block',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: (evt) => this.onSortEnd(evt),
            });
        });
    }

    onSortEnd(evt) {
        const blockId = evt.item.dataset.blockId;
        const fromId = evt.from.dataset.containerId;
        const toId = evt.to.dataset.containerId;
        if (!blockId || !fromId || !toId) return;

        if (fromId === toId) {
            // Same container — SortableJS already moved the DOM, just update data
            const arr = this.getBlockArray(fromId);
            if (!arr) return;
            const domIds = [...evt.from.querySelectorAll(':scope > .page-builder-block')].map(el => el.dataset.blockId);
            const reordered = domIds.map(id => arr.find(b => b.id === id)).filter(Boolean);
            if (reordered.length === arr.length) {
                this.setBlockArray(fromId, reordered);
            }
        } else {
            // Cross container — SortableJS moved the DOM, update data to match
            const fromArr = this.getBlockArray(fromId);
            const toArr = this.getBlockArray(toId);
            if (!fromArr || !toArr) return;

            const idx = fromArr.findIndex(b => b.id === blockId);
            if (idx === -1) return;
            const [moved] = fromArr.splice(idx, 1);

            const targetIds = [...evt.to.querySelectorAll(':scope > .page-builder-block')].map(el => el.dataset.blockId);
            const newIdx = targetIds.indexOf(blockId);
            toArr.splice(Math.max(0, newIdx), 0, moved);
        }

        this.syncHiddenInput();
        this.autoSave();
    }

    getBlockArray(containerId) {
        if (containerId === 'canvas') return this.blocks;
        const parent = this.findBlock(containerId);
        return parent?.children || null;
    }

    setBlockArray(containerId, arr) {
        if (containerId === 'canvas') { this.blocks = arr; return; }
        const parent = this.findBlock(containerId);
        if (parent) parent.children = arr;
    }

    /* ================================================================
       STATE MUTATIONS
       ================================================================ */
    selectBlock(id) {
        if (this.editorMode) {
            // Selection persists on a repeat click; the settings live in the sidebar
            if (this.selectedId === id) { this.emit('select', { id, again: true }); return; }
            this.root.querySelectorAll('.page-builder-block.is-selected').forEach(el => el.classList.remove('is-selected'));
            // Record typed input as its own step first — otherwise the
            // new selection gets attached to the previous block's change
            if (this._historyTimer) this.commitHistory();
            this.selectedId = id;
            this.root.querySelectorAll('.pb-node-head[aria-current]').forEach(el => el.removeAttribute('aria-current'));
            const node = this.root.querySelector(`.page-builder-block[data-block-id="${id}"]`);
            node?.classList.add('is-selected');
            node?.querySelector(':scope > .pb-node-head')?.setAttribute('aria-current', 'true');
            if (this.history?.length) this.history[this.history.length - 1].sel = id;
            this.renderSettingsPanel();
            return;
        }
        const prev = this.selectedId;
        this.selectedId = this.selectedId === id ? null : id;

        if (prev) {
            const el = this.root.querySelector(`[data-block-id="${prev}"]`);
            if (el) {
                el.classList.remove('ring-2', 'ring-accent');
                const s = el.querySelector(':scope > .block-settings');
                if (s) s.style.display = 'none';
            }
        }
        if (this.selectedId) {
            const el = this.root.querySelector(`[data-block-id="${this.selectedId}"]`);
            if (el) {
                el.classList.add('ring-2', 'ring-accent');
                const s = el.querySelector(':scope > .block-settings');
                if (s) {
                    // Lazy render settings on first open
                    if (s.dataset.lazy) {
                        const block = this.findBlock(this.selectedId);
                        if (block) {
                            const inner = this.renderBlockSettings(block);
                            while (inner.firstChild) s.appendChild(inner.firstChild);
                            delete s.dataset.lazy;
                        }
                    }
                    s.style.display = '';
                }
            }
        }
    }

    addBlock(slug) {
        const block = this.createBlock(slug);
        if (!block) return;
        this.blocks.push(block);
        this.selectedId = block.id;
        this.refreshCanvas();
        this.afterInsert(block.id);
        this.autoSave();
    }

    // New block: show it in the structure and (editor mode) open its settings
    afterInsert(id) {
        if (!this.editorMode) return;
        this.renderSettingsPanel();
        const el = this.root.querySelector(`.page-builder-block[data-block-id="${id}"]`);
        el?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    addChildBlock(containerId, slug) {
        const parent = this.findBlock(containerId);
        if (!parent || !Array.isArray(parent.children)) return;
        const block = this.createBlock(slug);
        if (!block) return;
        parent.children.push(block);
        this.selectedId = block.id;
        // Collapsed container: expand it, otherwise the new block is not visible
        if (this.collapsed.has(containerId)) { this.collapsed.delete(containerId); this.persistCollapsed(); }
        this.refreshCanvas();
        this.afterInsert(block.id);
        this.autoSave();
    }

    removeBlock(id) {
        const ctx = this.findBlockContext(id);
        if (!ctx) return;
        if (this.selectedId === id || (this.selectedId && this.findBlock(this.selectedId, [ctx.array[ctx.index]]))) this.selectedId = null;
        ctx.array.splice(ctx.index, 1);
        this.refreshCanvas();
        if (this.editorMode) this.renderSettingsPanel();
        this.autoSave();
    }

    duplicateBlock(id) {
        const ctx = this.findBlockContext(id);
        if (!ctx) return;
        const clone = deepCloneWithNewIds(ctx.array[ctx.index]);
        ctx.array.splice(ctx.index + 1, 0, clone);
        if (this.editorMode) this.selectedId = clone.id;
        this.refreshCanvas();
        this.afterInsert(clone.id);
        this.autoSave();
    }

    moveBlock(id, direction) {
        const ctx = this.findBlockContext(id);
        if (!ctx) return;
        const newIdx = ctx.index + direction;
        if (newIdx < 0 || newIdx >= ctx.array.length) return;
        const [item] = ctx.array.splice(ctx.index, 1);
        ctx.array.splice(newIdx, 0, item);
        this.refreshCanvas();
        this.autoSave();
    }

    syncColumnCount(block) {
        const target = parseInt(block.settings.columns) || 2;
        const current = block.children.length;
        if (target > current) {
            for (let i = current; i < target; i++) block.children.push(this.createBlock('column'));
        } else if (target < current) {
            block.children.splice(target);
        }
        this.refreshCanvas();
    }

    /* ================================================================
       BLOCK HELPERS
       ================================================================ */
    createBlock(slug) {
        const def = this.getBlockType(slug);
        if (!def) return null;
        const block = {
            id: generateId(),
            type: slug,
            data: JSON.parse(JSON.stringify(def.defaults?.data || {})),
            settings: JSON.parse(JSON.stringify(def.defaults?.settings || { class: '' })),
        };
        if (def.hasChildren) block.children = [];
        this.localizeDefaults(slug, block.data);
        if (slug === 'columns') {
            const n = parseInt(block.settings.columns) || 2;
            for (let i = 0; i < n; i++) block.children.push(this.createBlock('column'));
        }
        if (slug === 'accordion') block.children.push(this.createBlock('accordion-item'));
        if (slug === 'tabs') block.children.push(this.createBlock('tab-item'));
        return block;
    }

    getBlockType(slug) { return blockTypes.find(t => t.slug === slug); }

    // The defaults in block-types.js are English — new blocks get them
    // in the UI language.
    localizeDefaults(slug, data) {
        if (slug === 'button') data.text = this._('default_button', 'Click me');
        if (slug === 'list') data.items = [1, 2, 3].map(n => this._('default_item', 'Item :n', { n }));
        if (slug === 'link-group') data.links = (data.links || []).map((link, i) => ({ ...link, text: this._('default_link', 'Link :n', { n: i + 1 }) }));
        if (slug === 'accordion-item') data.title = this._('default_section', 'Section');
        if (slug === 'tab-item') data.title = this._('default_tab', 'Tab');
        if (slug === 'hero') {
            data.title = this._('default_hero_title', 'Your headline');
            data.text = this._('default_hero_text', 'One or two sentences on what visitors can expect here.');
            data.primary_text = this._('default_hero_button', 'Get started');
        }
        if (slug === 'buttons') data.items = [
            { text: this._('default_hero_button', 'Get started'), url: '#', style: 'primary', icon: 'arrow-right', target: '_self' },
            { text: this._('default_button_second', 'Learn more'), url: '#', style: 'secondary', icon: '', target: '_self' },
        ];
        if (slug === 'pricing') data.plans = [1, 2, 3].map(n => ({
            name: this._('default_plan', 'Plan :n', { n }), price: String(n * 10), period: this._('default_plan_period', 'per month'),
            description: '', features: this._('default_plan_features', 'First feature\nSecond feature').split('\\n').join('\n'), button_text: this._('default_hero_button', 'Get started'),
            button_url: '#', button_target: '_self', featured: n === 2, badge: n === 2 ? this._('default_plan_badge', 'Popular') : '',
        }));
        if (slug === 'stats') data.items = [1, 2, 3].map(n => ({ value: String(n * 100), label: this._('default_stat', 'Key figure :n', { n }) }));
    }

    getBlockPreview(block) {
        const d = block.data;
        if (!d) return '';
        if (block.type === 'heading' && d.text) return '— ' + d.text.substring(0, 30);
        if (block.type === 'text' && d.content) return '— ' + d.content.replace(/<[^>]*>/g, '').substring(0, 30);
        if (block.type === 'button' && d.text) return '— ' + d.text;
        if (block.type === 'alert' && d.title) return '— ' + d.title;
        if (block.type === 'card' && d.title) return '— ' + d.title;
        if (block.type === 'hero' && d.title) return '— ' + d.title.substring(0, 40);
        if (block.type === 'buttons' && Array.isArray(d.items)) return '— ' + d.items.map(i => i.text).filter(Boolean).join(' · ');
        if (block.type === 'pricing' && Array.isArray(d.plans)) return '— ' + d.plans.map(p => p.name).filter(Boolean).join(' · ');
        if (block.type === 'logos' && Array.isArray(d.items)) return '— ' + d.items.length;
        if (block.type === 'stats' && Array.isArray(d.items)) return '— ' + d.items.map(i => i.value).filter(Boolean).join(' · ');
        if (block.type === 'image' && d.alt) return '— ' + d.alt;
        if (block.type === 'accordion-item' && d.title) return '— ' + d.title;
        if (block.type === 'tab-item' && d.title) return '— ' + d.title;
        return '';
    }

    findBlock(id, blocks) {
        blocks = blocks || this.blocks;
        for (const b of blocks) {
            if (b.id === id) return b;
            if (b.children) { const f = this.findBlock(id, b.children); if (f) return f; }
        }
        return null;
    }

    findBlockContext(id, blocks, parent) {
        blocks = blocks || this.blocks;
        for (let i = 0; i < blocks.length; i++) {
            if (blocks[i].id === id) return { array: blocks, index: i, parent };
            if (blocks[i].children) { const f = this.findBlockContext(id, blocks[i].children, blocks[i]); if (f) return f; }
        }
        return null;
    }

    /* ================================================================
       PERSISTENCE
       ================================================================ */
    /** Is there at least one block? */
    hasContent() {
        return this.blocks.length > 0;
    }

    /**
     * Replaces all blocks with a single text block containing this HTML —
     * for taking over content from the text editor without losing anything.
     */
    replaceWithHtml(html) {
        this.blocks = [{ id: generateId(), type: 'text', data: { content: html || '' }, settings: { class: '' } }];
        this.refreshCanvas();
        this.syncHiddenInput();
        this.scheduleAutoSave();
    }

    syncHiddenInput() {
        if (this.hiddenInput) this.hiddenInput.value = this.pristine ? '' : JSON.stringify(this.blocks);
        this.scheduleA11y();
        // Input (typing) becomes one step after a pause — not every letter
        if (this.editorMode && !this._restoring) {
            clearTimeout(this._historyTimer);
            this._historyTimer = setTimeout(() => this.commitHistory(), 600);
        }
    }

    /* ================================================================
       HISTORY — undo / redo (editor mode)
       ================================================================ */
    commitHistory() {
        if (!this.editorMode || this._restoring || !this.history) return;
        clearTimeout(this._historyTimer);
        const json = JSON.stringify(this.blocks);
        const top = this.history[this.history.length - 1];
        // Same content: only update the selection of this step
        if (json === top.json) { top.sel = this.selectedId; return; }
        this.history.push({ json, sel: this.selectedId });
        if (this.history.length > 100) this.history.shift();
        this.future = [];
        this.emitHistory();
    }

    undo() {
        this.commitHistory();
        if (this.history.length < 2) return false;
        this.future.push(this.history.pop());
        this.restoreHistory(this.history[this.history.length - 1]);
        return true;
    }

    redo() {
        this.commitHistory();
        if (!this.future.length) return false;
        const state = this.future.pop();
        this.history.push(state);
        this.restoreHistory(state);
        return true;
    }

    restoreHistory(state) {
        this._restoring = true;
        this.blocks = JSON.parse(state.json);
        // The step's selection — otherwise the current one, if that block still exists
        if (state.sel && this.findBlock(state.sel)) this.selectedId = state.sel;
        else if (this.selectedId && !this.findBlock(this.selectedId)) this.selectedId = null;
        this.refreshCanvas();
        this.renderSettingsPanel();
        this._restoring = false;
        this.emitHistory();
        this.autoSave();
    }

    emitHistory() {
        this.emit('history', { undo: this.history.length > 1, redo: this.future.length > 0 });
    }

    // Clear selection (Esc)
    deselect() {
        if (!this.selectedId) return;
        this.root.querySelectorAll('.page-builder-block.is-selected').forEach(el => el.classList.remove('is-selected'));
        this.selectedId = null;
        this.renderSettingsPanel();
    }

    /*
     * Move a block: before or after another one — also into a different
     * container (dragging in the preview). Never into itself.
     */
    moveBlockRelative(id, targetId, after) {
        if (!id || !targetId || id === targetId) return false;
        const moving = this.findBlock(id);
        if (!moving || this.findBlock(targetId, [moving])) return false;
        const from = this.findBlockContext(id);
        if (!from) return false;
        const [block] = from.array.splice(from.index, 1);
        const to = this.findBlockContext(targetId);
        if (!to) { from.array.splice(from.index, 0, block); return false; }
        to.array.splice(to.index + (after ? 1 : 0), 0, block);
        this.selectedId = id;
        this.refreshCanvas();
        this.renderSettingsPanel();
        this.autoSave();
        return true;
    }

    markChanged() {
        if (this.pristine) {
            this.pristine = false;
            this.syncHiddenInput();
        }
        this._unsaved = true;
    }

    scheduleAutoSave() {
        this.markChanged();
        if (!this.saveUrl) return;
        clearTimeout(this._saveTimer);
        this.emit('dirty');
        this._saveTimer = setTimeout(() => this.autoSave(), 800);
    }

    /** Unsaved blocks: a change is still pending or the last save failed */
    get hasUnsavedBlocks() {
        return !!this.saveUrl && (this._saveFailed || (this._unsaved && !this._saving));
    }

    autoSave() {
        clearTimeout(this._saveTimer);
        this._saveTimer = null;
        this.markChanged();
        this.syncHiddenInput();

        if (!this.saveUrl) return;
        if (this._saving) { this._saveQueued = true; return; }

        this._saving = true;
        this._unsaved = false;
        this.emit('saving');
        fetch(this.saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
            body: JSON.stringify({ blocks: JSON.stringify(this.blocks) }),
        }).then(res => {
            this._saveFailed = !res.ok;
            if (!res.ok) this._unsaved = true;
            // 419: session expired — further saving is pointless until the page is reloaded
            this.emit(res.ok ? 'saved' : 'save-error', { status: res.status });
            // In the editor the bar shows the state — no "Saved" on every keystroke
            if (!this.editorMode) this.showToast(res.ok ? this._('autosaved', 'Saved') : this._('save_error', 'Error'), res.ok ? 'success' : 'error');
        }).catch(() => {
            this._saveFailed = true;
            this._unsaved = true;
            this.emit('save-error', { status: 0 });
            if (!this.editorMode) this.showToast(this._('save_error', 'Error'), 'error');
        }).finally(() => {
            this._saving = false;
            if (this._saveQueued) {
                this._saveQueued = false;
                this.autoSave();
            }
        });
    }

    /**
     * When leaving the page: send a still pending change immediately.
     * keepalive lets the request outlive the page (up to approx. 64 KB) —
     * larger pages report via hasUnsavedBlocks (confirmation prompt).
     */
    flushSave() {
        if (!this.saveUrl || !(this._saveTimer || this._saveQueued || (this._unsaved && !this._saving))) return;
        clearTimeout(this._saveTimer);
        this._saveTimer = null;
        const body = JSON.stringify({ blocks: JSON.stringify(this.blocks) });
        if (body.length > 60000) { this.autoSave(); return; }
        try {
            fetch(this.saveUrl, {
                method: 'POST',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                body,
            });
            this._unsaved = false;
            this._saveQueued = false;
        } catch (e) { /* stays unsaved → confirmation prompt */ }
    }
}
