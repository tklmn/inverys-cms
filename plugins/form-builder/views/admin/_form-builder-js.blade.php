<script @cspNonce>
(function () {
    'use strict';

    // Icons are Lucide names. The label comes from the language files
    // (_('type_' + type)); only field types from other plugins carry `label`.
    var FIELD_TYPES = [
        { type: 'text', icon: 'text-cursor-input' },
        { type: 'email', icon: 'mail' },
        { type: 'number', icon: 'hash' },
        { type: 'tel', icon: 'phone' },
        { type: 'url', icon: 'link' },
        { type: 'textarea', icon: 'align-left' },
        { type: 'select', icon: 'chevron-down' },
        { type: 'multi-select', icon: 'list-checks' },
        { type: 'checkbox', icon: 'square-check' },
        { type: 'checkbox-group', icon: 'list-checks' },
        { type: 'radio', icon: 'circle-dot' },
        { type: 'file', icon: 'paperclip' },
        { type: 'hidden', icon: 'eye-off' },
        { type: 'date', icon: 'calendar' },
        { type: 'time', icon: 'clock' },
        { type: 'static-text', icon: 'type' },
    ];

    // Expose for plugin extensions (e.g. captcha field type)
    window.__fbFieldTypes = FIELD_TYPES;

    // Merge any plugin-registered field types (from head hooks that ran before this script)
    (window.__fbPluginFieldTypes || []).forEach(function (ft) { FIELD_TYPES.push(ft); });

    var HAS_OPTIONS = ['select', 'multi-select', 'checkbox-group', 'radio'];

    function uid() {
        return 'field-' + Math.random().toString(36).substr(2, 9);
    }

    function slugify(str) {
        return str.toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '')
            .replace(/_+/g, '_');
    }

    function h(tag, className, text) {
        var el = document.createElement(tag);
        if (className) el.className = className;
        if (text) el.textContent = text;
        return el;
    }

    // Lucide icon as an element. uiIcon resolves Bootstrap Icons names (bi-…) from
    // third-party plugins via the alias table. Extra classes are kept,
    // because e.g. item-drag-handle is the handle for SortableJS.
    function icon(name, cls) {
        if (window.uiIcon) return window.uiIcon(name, cls || '');
        return h('span', 'ui-icon ' + (cls || ''));
    }

    // Icon-only button: needs a label for screen readers and the tooltip
    function iconButton(name, label, cls) {
        var btn = h('button', cls);
        btn.type = 'button';
        btn.title = label;
        btn.setAttribute('aria-label', label);
        btn.appendChild(icon(name));
        return btn;
    }

    // ====================================================================
    // FormBuilder
    // ====================================================================
    window.FormBuilder = function FormBuilder(container, config) {
        this.container = container;
        this.fields = JSON.parse(JSON.stringify(config.fields || []));
        this.hiddenInput = config.hiddenInput;
        this.saveUrl = config.saveUrl || null;
        this.draftUrl = config.draftUrl || null;
        this.csrf = config.csrf || '';
        this.t = config.t || {};
        this.expandedId = null;
        this._saveTimer = null;
        this._sortables = [];
        this._draftCreating = false;

        // Ensure each item has an id (including nested)
        this._ensureIds(this.fields);

        this.render();
        this.sync();
    };

    // All texts come as config.t from forms.php; without a match the
    // fallback of a third-party plugin, finally the key itself
    FormBuilder.prototype._ = function (key, fallback) {
        return this.t[key] || fallback || key;
    };

    FormBuilder.prototype._typeLabel = function (ft, type) {
        return this._('type_' + type, ft ? ft.label : type);
    };

    FormBuilder.prototype._ensureIds = function (items) {
        var self = this;
        (items || []).forEach(function (item) {
            if (!item.id) item.id = uid();
            if (item.children) {
                item.children.forEach(function (child) {
                    if (!child.id) child.id = uid();
                    if (child.children) self._ensureIds(child.children);
                });
            }
        });
    };

    FormBuilder.prototype.sync = function () {
        if (this.hiddenInput) {
            this.hiddenInput.value = JSON.stringify(this.fields);
        }
    };

    // ====================================================================
    // Auto-save
    // ====================================================================
    FormBuilder.prototype.scheduleAutoSave = function () {
        if (!this.saveUrl && !this.draftUrl) return;
        clearTimeout(this._saveTimer);
        var self = this;
        this._saveTimer = setTimeout(function () { self.autoSave(); }, 800);
    };

    FormBuilder.prototype.autoSave = function () {
        clearTimeout(this._saveTimer);
        this.sync();
        var self = this;
        var headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.csrf,
            'Accept': 'application/json',
        };

        // No saveUrl yet but draftUrl available — create draft first
        if (!this.saveUrl && this.draftUrl) {
            if (this._draftCreating) return;
            this._draftCreating = true;
            fetch(this.draftUrl, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({ fields: JSON.stringify(this.fields) }),
            }).then(function (res) { return res.json(); })
                .then(function (data) {
                    self._draftCreating = false;
                    if (data.ok && data.save_url) {
                        self.saveUrl = data.save_url;
                        self.draftUrl = null;
                        // The create form takes over the draft instead of creating a second form
                        var form = self.container.closest('form');
                        if (form && data.form_id) {
                            var draftInput = form.querySelector('input[name="draft_id"]');
                            if (!draftInput) {
                                draftInput = document.createElement('input');
                                draftInput.type = 'hidden';
                                draftInput.name = 'draft_id';
                                form.appendChild(draftInput);
                            }
                            draftInput.value = data.form_id;
                        }
                        if (data.edit_url) {
                            history.replaceState(null, '', data.edit_url);
                        }
                        self.showToast(self._('autosaved'), 'success');
                    }
                }).catch(function () {
                    self._draftCreating = false;
                });
            return;
        }

        if (!this.saveUrl) return;

        fetch(this.saveUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ fields: JSON.stringify(this.fields) }),
        }).then(function (res) {
            self.showToast(res.ok ? self._('autosaved') : self._('save_error'), res.ok ? 'success' : 'error');
        }).catch(function () {
            self.showToast(self._('save_error'), 'error');
        });
    };

    FormBuilder.prototype.showToast = function (message, type) {
        // The backend's shared toast instead of a copy of our own
        if (window.uiToast) window.uiToast(message, type === 'error' ? 'critical' : 'positive');
    };

    // ====================================================================
    // Render
    // ====================================================================
    FormBuilder.prototype.render = function () {
        var self = this;

        // Destroy old sortable instances
        this._sortables.forEach(function (s) { s.destroy(); });
        this._sortables = [];

        this.container.innerHTML = '';
        this.container.className = 'form-builder flex flex-col md:flex-row md:items-start gap-6';

        // Palette on the left — the same as in the page builder (ui-palette, page-builder.css):
        // from md up it sticks while scrolling and scrolls itself if needed; when narrow
        // the field types sit in two columns.
        var palette = h('div', 'form-builder-palette ui-palette w-full shrink-0 md:w-56');
        palette.appendChild(h('div', 'ui-palette-title', this._('field_types')));

        var paletteList = h('div', 'ui-palette-list');

        // Row element
        var rowLabel = this._('type_row');
        var rowBtn = h('button', 'ui-palette-item ui-palette-item-accent');
        rowBtn.type = 'button';
        rowBtn.title = rowLabel;
        rowBtn.appendChild(icon('columns-3'));
        rowBtn.appendChild(h('span', 'truncate', rowLabel));
        rowBtn.addEventListener('click', function () { self.addRow(); });
        paletteList.appendChild(rowBtn);

        // Separator
        paletteList.appendChild(h('div', 'ui-palette-sep'));

        // Field types
        FIELD_TYPES.forEach(function (ft) {
            var label = self._typeLabel(ft, ft.type);
            var btn = h('button', 'ui-palette-item');
            btn.type = 'button';
            btn.title = label;
            btn.appendChild(icon(ft.icon));
            btn.appendChild(h('span', 'truncate', label));
            btn.addEventListener('click', function () { self.addField(ft.type); });
            paletteList.appendChild(btn);
        });

        palette.appendChild(paletteList);
        this.container.appendChild(palette);

        // Main canvas
        var canvas = h('div', 'form-builder-canvas flex-1 min-w-0 w-full');
        this.canvasEl = canvas;

        if (this.fields.length === 0) {
            var empty = h('div', 'flex flex-col items-center justify-center py-16 border-2 border-dashed border-line-strong rounded-lg text-fg-muted');
            empty.appendChild(icon('circle-plus', 'text-3xl mb-2'));
            empty.appendChild(h('p', 'text-sm', this._('click_to_add')));
            canvas.appendChild(empty);
        } else {
            var list = h('div', 'form-builder-list space-y-2');
            list.id = 'form-builder-sortable';
            this.fields.forEach(function (item) {
                if (item.type === 'row') {
                    list.appendChild(self.renderRowCard(item));
                } else {
                    list.appendChild(self.renderFieldCard(item));
                }
            });
            canvas.appendChild(list);

            // Top-level sortable — shared group allows fields to move in/out of columns
            this._sortables.push(new Sortable(list, {
                handle: '.item-drag-handle',
                animation: 150,
                ghostClass: 'opacity-30',
                group: { name: 'fb-fields', pull: true, put: true },
                onUpdate: function (evt) {
                    // Reorder within top-level
                    var moved = self.fields.splice(evt.oldIndex, 1)[0];
                    self.fields.splice(evt.newIndex, 0, moved);
                    self.sync();
                    self.scheduleAutoSave();
                },
                onAdd: function () {
                    // Field arrived from a column — rebuild from DOM
                    self._rebuildFromDom();
                    self.sync();
                    self.scheduleAutoSave();
                },
                onRemove: function () {
                    // Field left to a column — rebuild from DOM
                    self._rebuildFromDom();
                    self.sync();
                    self.scheduleAutoSave();
                },
            }));
        }

        this.container.appendChild(canvas);
    };

    // ====================================================================
    // Add field / row
    // ====================================================================
    FormBuilder.prototype._afterAdd = function (item) {
        this.expandedId = item.id;
        this.render();
        this.sync();
        this.scheduleAutoSave();

        var newCard = document.querySelector('[data-field-id="' + item.id + '"]');
        if (newCard) newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    FormBuilder.prototype.addField = function (type, targetArray) {
        var arr = targetArray || this.fields;
        var field;

        // Plugin-provided field creators (e.g. captcha)
        if (window.__fbFieldCreators && window.__fbFieldCreators[type]) {
            field = window.__fbFieldCreators[type](uid);
            arr.push(field);
            this._afterAdd(field);
            return;
        }

        var ft = FIELD_TYPES.find(function (t) { return t.type === type; });
        var label = this._typeLabel(ft, type);

        if (type === 'static-text') {
            field = {
                id: uid(),
                type: 'static-text',
                label: label,
                content: '',
                width: 'full',
                css_class: '',
            };
        } else {
            field = {
                id: uid(),
                type: type,
                label: label,
                name: slugify(label),
                placeholder: '',
                help_text: '',
                required: false,
                validation: '',
                width: 'full',
                default_value: '',
                css_class: '',
            };
            if (HAS_OPTIONS.indexOf(type) !== -1) {
                field.options = [
                    { label: this._('option_text') + ' 1', value: 'option_1' },
                    { label: this._('option_text') + ' 2', value: 'option_2' },
                ];
            }
        }

        arr.push(field);
        this._afterAdd(field);
    };

    FormBuilder.prototype.addRow = function () {
        var row = {
            id: uid(),
            type: 'row',
            columns: 2,
            children: [
                { id: uid(), type: 'column', children: [] },
                { id: uid(), type: 'column', children: [] },
            ],
        };
        this.fields.push(row);
        this._afterAdd(row);
    };

    FormBuilder.prototype.removeItem = function (id) {
        // Remove from top-level or from inside rows
        this.fields = this._removeById(this.fields, id);
        if (this.expandedId === id) this.expandedId = null;
        this.render();
        this.sync();
        this.scheduleAutoSave();
    };

    // Deleting is saved automatically right away — so ask first
    FormBuilder.prototype.confirmRemove = function (item) {
        var isRow = item.type === 'row';
        var name = isRow ? this._('type_row') : (item.label || item.name || '');
        var message = this._(isRow ? 'confirm_remove_row' : 'confirm_remove_field').replace(':name', name);

        if (window.confirm(message)) this.removeItem(item.id);
    };

    FormBuilder.prototype._removeById = function (items, id) {
        var result = [];
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (item.id === id) continue;
            if (item.children) {
                item.children = item.children.map(function (col) {
                    if (col.id === id) return null;
                    if (col.children) {
                        col.children = col.children.filter(function (f) { return f.id !== id; });
                    }
                    return col;
                }).filter(Boolean);
            }
            result.push(item);
        }
        return result;
    };

    // ====================================================================
    // Find item by id (for settings)
    // ====================================================================
    FormBuilder.prototype._findById = function (items, id) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === id) return items[i];
            if (items[i].children) {
                for (var j = 0; j < items[i].children.length; j++) {
                    if (items[i].children[j].id === id) return items[i].children[j];
                    if (items[i].children[j].children) {
                        var found = this._findById(items[i].children[j].children, id);
                        if (found) return found;
                    }
                }
            }
        }
        return null;
    };

    // ====================================================================
    // Row card
    // ====================================================================
    FormBuilder.prototype.renderRowCard = function (row) {
        var self = this;
        var isExpanded = this.expandedId === row.id;
        var cols = row.children || [];

        var card = h('div', 'row-card border border-accent-line rounded-lg bg-accent-soft overflow-hidden' + (isExpanded ? ' ring-2 ring-accent' : ''));
        card.setAttribute('data-field-id', row.id);

        // Header
        var header = h('div', 'flex items-center gap-2 px-4 py-3 cursor-pointer select-none hover:bg-surface-hover transition');
        var handle = icon('grip-vertical', 'item-drag-handle text-fg-subtle cursor-grab');
        header.appendChild(handle);
        header.appendChild(icon('columns-3', 'text-accent'));
        header.appendChild(h('span', 'flex-1 text-sm font-medium text-fg', this._('type_row') + ' (' + cols.length + ')'));
        header.appendChild(icon(isExpanded ? 'chevron-up' : 'chevron-down', 'text-fg-subtle text-sm'));

        var removeBtn = iconButton('trash-2', this._('remove_row'), 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost ml-1 hover:text-critical');
        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            self.confirmRemove(row);
        });
        header.appendChild(removeBtn);

        header.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            self.expandedId = self.expandedId === row.id ? null : row.id;
            self.render();
        });

        card.appendChild(header);

        // Row settings (column count)
        if (isExpanded) {
            var settings = h('div', 'px-4 pb-3 border-t border-accent-line pt-3');
            var selWrap = h('div', 'ui-field');
            var selId = 'fb-cols-' + row.id;
            var selLabel = h('label', 'ui-label', this._('columns'));
            selLabel.htmlFor = selId;
            selWrap.appendChild(selLabel);

            var sel = h('select', 'ui-select');
            sel.id = selId;
            [2, 3, 4].forEach(function (n) {
                var opt = document.createElement('option');
                opt.value = n;
                opt.textContent = n + ' ' + self._('columns');
                opt.selected = cols.length === n;
                sel.appendChild(opt);
            });
            sel.addEventListener('change', function () { self.syncColumnCount(row, parseInt(sel.value, 10)); });
            selWrap.appendChild(sel);
            settings.appendChild(selWrap);
            card.appendChild(settings);
        }

        // Column zones
        var colZones = h('div', 'mx-3 mb-3 grid gap-2');
        colZones.style.gridTemplateColumns = 'repeat(' + cols.length + ', minmax(0, 1fr))';

        cols.forEach(function (col, colIdx) {
            var zone = h('div', 'border-2 border-dashed border-accent-line rounded-lg p-2 min-h-[80px] space-y-2 bg-surface');
            zone.dataset.containerId = col.id;

            zone.appendChild(h('div', 'text-xs text-fg-muted text-center font-medium select-none', self._('column') + ' ' + (colIdx + 1)));

            // Render fields in this column
            (col.children || []).forEach(function (field) {
                zone.appendChild(self.renderFieldCard(field));
            });

            // Add field button inside column
            var addBtn = iconButton('plus', self._('add_field'), 'w-full py-1.5 text-xs text-fg-muted hover:text-accent border border-dashed border-line hover:border-accent rounded transition flex items-center justify-center gap-1');
            addBtn.addEventListener('click', function () {
                self._showFieldPicker(function (type) {
                    self.addField(type, col.children);
                });
            });
            zone.appendChild(addBtn);

            // Make column sortable — shared group so fields can move between columns and top-level
            setTimeout(function () {
                self._sortables.push(new Sortable(zone, {
                    group: {
                        name: 'fb-fields',
                        pull: true,
                        put: function (to, from, el) {
                            return el.classList.contains('field-card');
                        },
                    },
                    handle: '.field-drag-handle',
                    animation: 150,
                    ghostClass: 'opacity-30',
                    draggable: '.field-card',
                    onUpdate: function () {
                        self._rebuildFromDom();
                        self.sync();
                        self.scheduleAutoSave();
                    },
                    onAdd: function () {
                        self._rebuildFromDom();
                        self.sync();
                        self.scheduleAutoSave();
                    },
                    onRemove: function () {
                        self._rebuildFromDom();
                        self.sync();
                        self.scheduleAutoSave();
                    },
                }));
            }, 0);

            colZones.appendChild(zone);
        });

        card.appendChild(colZones);
        return card;
    };

    FormBuilder.prototype.syncColumnCount = function (row, target) {
        var current = (row.children || []).length;
        var i;
        if (target > current) {
            for (i = current; i < target; i++) {
                row.children.push({ id: uid(), type: 'column', children: [] });
            }
        } else if (target < current) {
            // Move fields from removed columns to the last remaining column
            var lastCol = row.children[target - 1];
            for (i = target; i < current; i++) {
                (row.children[i].children || []).forEach(function (f) {
                    lastCol.children.push(f);
                });
            }
            row.children.splice(target);
        }
        this.render();
        this.sync();
        this.scheduleAutoSave();
    };

    /**
     * Build a flat index of all data objects (fields + rows) by ID.
     */
    FormBuilder.prototype._buildIndex = function () {
        var idx = {};
        var walk = function (items) {
            (items || []).forEach(function (item) {
                idx[item.id] = item;
                if (item.children) {
                    item.children.forEach(function (col) {
                        idx[col.id] = col;
                        if (col.children) walk(col.children);
                    });
                }
            });
        };
        walk(this.fields);
        return idx;
    };

    /**
     * Rebuild this.fields entirely from the current DOM state.
     * Handles fields moving between top-level and column zones.
     */
    FormBuilder.prototype._rebuildFromDom = function () {
        var index = this._buildIndex();
        var sortable = document.getElementById('form-builder-sortable');
        if (!sortable) return;

        var newFields = [];

        // Walk top-level children
        Array.prototype.forEach.call(sortable.children, function (el) {
            var fid = el.getAttribute('data-field-id');
            if (!fid || !index[fid]) return;
            var item = index[fid];

            if (item.type === 'row') {
                // Sync columns from their DOM zones
                (item.children || []).forEach(function (col) {
                    var zone = document.querySelector('[data-container-id="' + col.id + '"]');
                    if (!zone) return;
                    var colChildren = [];
                    zone.querySelectorAll(':scope > .field-card[data-field-id]').forEach(function (fieldEl) {
                        var cfid = fieldEl.getAttribute('data-field-id');
                        if (cfid && index[cfid]) colChildren.push(index[cfid]);
                    });
                    col.children = colChildren;
                });
                newFields.push(item);
            } else if (el.classList.contains('field-card')) {
                newFields.push(item);
            }
        });

        this.fields = newFields;
    };

    FormBuilder.prototype._showFieldPicker = function (callback) {
        var self = this;

        // Quick inline dropdown
        var overlay = h('div', 'fixed inset-0 z-50');
        overlay.addEventListener('click', function () { overlay.remove(); });

        var popup = h('div', 'fixed z-50 bg-surface border border-line rounded-lg shadow-xl p-3 max-h-64 overflow-auto');
        popup.style.cssText = 'top:50%;left:50%;transform:translate(-50%,-50%);min-width:240px;';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-label', self._('field_types'));
        popup.appendChild(h('div', 'ui-section-label mb-2', self._('field_types')));

        FIELD_TYPES.forEach(function (ft) {
            var btn = h('button', 'flex items-center gap-2 px-3 py-2 text-sm rounded hover:bg-surface-hover transition text-fg text-left w-full');
            btn.type = 'button';
            btn.appendChild(icon(ft.icon, 'text-base text-fg-muted'));
            btn.appendChild(h('span', '', self._typeLabel(ft, ft.type)));
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                overlay.remove();
                callback(ft.type);
            });
            popup.appendChild(btn);
        });

        overlay.appendChild(popup);
        document.body.appendChild(overlay);
    };

    // ====================================================================
    // Field card
    // ====================================================================
    FormBuilder.prototype.renderFieldCard = function (field) {
        var self = this;
        var isExpanded = this.expandedId === field.id;
        var ft = FIELD_TYPES.find(function (t) { return t.type === field.type; });

        var card = h('div', 'field-card border border-line rounded-lg bg-surface overflow-hidden' + (isExpanded ? ' ring-2 ring-accent' : ''));
        card.setAttribute('data-field-id', field.id);

        // Header
        var header = h('div', 'field-card-header flex items-center gap-2 px-4 py-3 cursor-pointer select-none hover:bg-surface-hover transition');
        header.appendChild(icon('grip-vertical', 'field-drag-handle item-drag-handle text-fg-subtle cursor-grab'));
        header.appendChild(icon(ft ? ft.icon : 'text-cursor-input', 'text-accent'));

        var labelSpan = h('span', 'flex-1 min-w-0 text-sm font-medium text-fg truncate', field.label || field.name);
        if (field.required) {
            labelSpan.appendChild(h('span', 'ml-1 text-critical text-xs', '*'));
        }
        header.appendChild(labelSpan);

        header.appendChild(h('span', 'hidden sm:inline text-xs text-fg-muted', self._typeLabel(ft, field.type)));
        header.appendChild(h('span', 'text-xs px-1.5 py-0.5 rounded bg-surface-sunken text-fg-muted', field.width === 'half' ? self._('width_badge_half') : self._('width_badge_full')));
        header.appendChild(icon(isExpanded ? 'chevron-up' : 'chevron-down', 'text-fg-muted text-sm'));

        var removeBtn = iconButton('trash-2', this._('remove_field'), 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost ml-1 hover:text-critical');
        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            self.confirmRemove(field);
        });
        header.appendChild(removeBtn);

        header.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            self.expandedId = self.expandedId === field.id ? null : field.id;
            self.render();
        });

        card.appendChild(header);

        // Expanded settings
        if (isExpanded) {
            var body = h('div', 'field-card-body px-4 pb-4 border-t border-line pt-4');
            body.appendChild(self.renderFieldSettings(field));
            card.appendChild(body);
        }

        return card;
    };

    // ====================================================================
    // Field settings
    // ====================================================================
    FormBuilder.prototype.widthSelect = function (field) {
        var self = this;
        var id = 'fb-width-' + field.id;
        var wrap = h('div', 'ui-field');
        var label = h('label', 'ui-label', self._('width'));
        label.htmlFor = id;
        wrap.appendChild(label);

        var select = h('select', 'ui-select');
        select.id = id;
        [{ v: 'full', l: self._('width_full') }, { v: 'half', l: self._('width_half') }].forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o.v;
            opt.textContent = o.l;
            opt.selected = field.width === o.v;
            select.appendChild(opt);
        });
        select.addEventListener('change', function () {
            field.width = select.value;
            self.sync();
        });
        wrap.appendChild(select);

        return wrap;
    };

    FormBuilder.prototype.textArea = function (label, value, onChange, rows, placeholder) {
        var id = 'fb-' + uid();
        var wrap = h('div', 'ui-field');
        var lab = h('label', 'ui-label', label);
        lab.htmlFor = id;
        wrap.appendChild(lab);

        var area = h('textarea', 'ui-textarea');
        area.id = id;
        area.rows = rows || 3;
        area.value = value || '';
        if (placeholder) area.placeholder = placeholder;
        area.addEventListener('input', function () { onChange(area.value); });
        wrap.appendChild(area);

        return wrap;
    };

    FormBuilder.prototype.renderFieldSettings = function (field) {
        var self = this;
        var wrap = h('div', 'space-y-4');

        // Plugin-provided field settings renderers (e.g. captcha)
        if (window.__fbFieldSettingsRenderers && window.__fbFieldSettingsRenderers[field.type]) {
            window.__fbFieldSettingsRenderers[field.type](wrap, field, self);
            return wrap;
        }

        // Static text: just a content textarea + width + class
        if (field.type === 'static-text') {
            wrap.appendChild(self.textInput(self._('label'), field.label, function (v) { field.label = v; self.sync(); }));
            wrap.appendChild(self.textArea(self._('content'), field.content, function (v) { field.content = v; self.sync(); }, 4, self._('static_text_placeholder')));

            var staticRow = h('div', 'ui-grid');
            staticRow.appendChild(self.textInput(self._('css_class'), field.css_class || '', function (v) { field.css_class = v; self.sync(); }));
            staticRow.appendChild(self.widthSelect(field));
            wrap.appendChild(staticRow);

            return wrap;
        }

        // Label and Name
        var row1 = h('div', 'ui-grid');
        row1.appendChild(self.textInput(self._('label'), field.label, function (v) {
            field.label = v;
            var nameInput = wrap.querySelector('[data-setting="name"]');
            if (nameInput && !nameInput.dataset.customized) {
                field.name = slugify(v);
                nameInput.value = field.name;
            }
            self.sync();
        }));

        var nameWrap = self.textInput(self._('name'), field.name, function (v) {
            field.name = v;
            self.sync();
        });
        var nameInp = nameWrap.querySelector('input');
        if (nameInp) {
            nameInp.setAttribute('data-setting', 'name');
            nameInp.addEventListener('input', function () { nameInp.dataset.customized = '1'; });
        }
        row1.appendChild(nameWrap);
        wrap.appendChild(row1);

        // Placeholder and Help Text
        if (field.type !== 'hidden' && field.type !== 'checkbox' && field.type !== 'file') {
            var row2 = h('div', 'ui-grid');
            row2.appendChild(self.textInput(self._('placeholder'), field.placeholder || '', function (v) { field.placeholder = v; self.sync(); }));
            row2.appendChild(self.textInput(self._('help_text'), field.help_text || '', function (v) { field.help_text = v; self.sync(); }));
            wrap.appendChild(row2);
        }

        // Default Value (multi-line for textarea: own full row)
        if (field.type === 'textarea') {
            wrap.appendChild(self.textArea(self._('default_value'), field.default_value, function (v) { field.default_value = v; self.sync(); }, 3));
        }

        // Default Value, CSS Class, Width, Required
        var row3 = h('div', 'ui-grid ui-grid-dense items-end');
        if (field.type !== 'file' && field.type !== 'textarea') {
            row3.appendChild(self.textInput(self._('default_value'), field.default_value || '', function (v) { field.default_value = v; self.sync(); }));
        }
        row3.appendChild(self.textInput(self._('css_class'), field.css_class || '', function (v) { field.css_class = v; self.sync(); }));
        row3.appendChild(self.widthSelect(field));

        // Required field: same markup as x-ui.toggle
        if (field.type !== 'hidden') {
            var reqWrap = h('div', 'flex items-end pb-2');
            var reqLabel = h('label', 'ui-toggle');
            var reqCb = document.createElement('input');
            reqCb.type = 'checkbox';
            reqCb.className = 'ui-toggle-input';
            reqCb.checked = !!field.required;
            reqCb.addEventListener('change', function () {
                field.required = reqCb.checked;
                self.sync();
            });
            reqLabel.appendChild(reqCb);
            reqLabel.appendChild(h('span', 'ui-toggle-track'));
            reqLabel.appendChild(h('span', 'ui-toggle-label', self._('required')));
            reqWrap.appendChild(reqLabel);
            row3.appendChild(reqWrap);
        }

        wrap.appendChild(row3);

        // File field settings: allowed types and max size
        if (field.type === 'file') {
            var fileRow = h('div', 'ui-grid');
            fileRow.appendChild(self.textInput(self._('allowed_file_types'), field.allowed_file_types || '', function (v) { field.allowed_file_types = v; self.sync(); }, 'pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt,zip'));
            fileRow.appendChild(self.textInput(self._('max_file_size'), field.max_file_size || '', function (v) { field.max_file_size = v; self.sync(); }, '10240'));
            wrap.appendChild(fileRow);
        }

        // Validation rules
        wrap.appendChild(self.textInput(self._('validation'), field.validation || '', function (v) { field.validation = v; self.sync(); }, self._('validation_placeholder')));

        // Options editor
        if (HAS_OPTIONS.indexOf(field.type) !== -1) {
            wrap.appendChild(self.renderOptionsEditor(field));
        }

        return wrap;
    };

    // ====================================================================
    // Options editor
    // ====================================================================
    FormBuilder.prototype.renderOptionsEditor = function (field) {
        var self = this;
        if (!field.options) field.options = [];

        var wrap = h('div', 'border-t border-line pt-4 mt-2');
        wrap.appendChild(h('div', 'ui-section-label mb-2', self._('options')));

        var list = h('div', 'space-y-2 options-list');
        field.options.forEach(function (opt, idx) {
            var row = h('div', 'flex items-center gap-2');
            row.appendChild(icon('grip-vertical', 'shrink-0 text-fg-subtle cursor-grab option-drag-handle'));

            var labelInput = h('input', 'ui-input min-w-0 flex-1');
            labelInput.type = 'text';
            labelInput.value = opt.label || '';
            labelInput.placeholder = self._('option_label');
            labelInput.setAttribute('aria-label', self._('option_label'));

            var valueInput = h('input', 'ui-input min-w-0 flex-1');
            valueInput.type = 'text';
            valueInput.value = opt.value || '';
            valueInput.placeholder = self._('option_value');
            valueInput.setAttribute('aria-label', self._('option_value'));

            labelInput.addEventListener('input', function () {
                opt.label = labelInput.value;
                if (!valueInput.dataset.customized) {
                    opt.value = slugify(labelInput.value);
                    valueInput.value = opt.value;
                }
                self.sync();
            });
            valueInput.addEventListener('input', function () {
                opt.value = valueInput.value;
                valueInput.dataset.customized = '1';
                self.sync();
            });

            row.appendChild(labelInput);
            row.appendChild(valueInput);

            var removeOpt = iconButton('x', self._('remove_option'), 'ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost shrink-0 hover:text-critical');
            removeOpt.addEventListener('click', function () {
                field.options.splice(idx, 1);
                self.expandedId = field.id;
                self.render();
                self.sync();
            });
            row.appendChild(removeOpt);

            list.appendChild(row);
        });

        wrap.appendChild(list);

        // Make options sortable
        setTimeout(function () {
            var optList = wrap.querySelector('.options-list');
            if (optList) {
                self._sortables.push(new Sortable(optList, {
                    handle: '.option-drag-handle',
                    animation: 150,
                    ghostClass: 'opacity-30',
                    onEnd: function (evt) {
                        var moved = field.options.splice(evt.oldIndex, 1)[0];
                        field.options.splice(evt.newIndex, 0, moved);
                        self.sync();
                        self.scheduleAutoSave();
                    },
                }));
            }
        }, 0);

        var addBtn = h('button', 'ui-btn ui-btn-sm ui-btn-ghost mt-2');
        addBtn.type = 'button';
        addBtn.appendChild(icon('plus'));
        addBtn.appendChild(document.createTextNode(' ' + self._('add_option')));
        addBtn.addEventListener('click', function () {
            field.options.push({ label: '', value: '' });
            self.expandedId = field.id;
            self.render();
            self.sync();
        });
        wrap.appendChild(addBtn);

        return wrap;
    };

    // ====================================================================
    // Text input helper
    // ====================================================================
    FormBuilder.prototype.textInput = function (label, value, onChange, placeholder) {
        var id = 'fb-' + uid();
        var wrap = h('div', 'ui-field');
        var lab = h('label', 'ui-label', label);
        lab.htmlFor = id;
        wrap.appendChild(lab);

        var inp = h('input', 'ui-input');
        inp.type = 'text';
        inp.id = id;
        inp.value = value || '';
        if (placeholder) inp.placeholder = placeholder;
        inp.addEventListener('input', function () { onChange(inp.value); });
        wrap.appendChild(inp);

        return wrap;
    };
})();
</script>
