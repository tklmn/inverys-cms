@php
    $dashT = [
        'customize'          => __('translation.dashboard.customize'),
        'done'               => __('translation.dashboard.done_editing'),
        'reset_confirm'      => __('translation.dashboard.reset_confirm'),
        'add_widget'         => __('translation.dashboard.add_widget'),
        'add_row'            => __('translation.dashboard.add_row'),
        'no_hidden'          => __('translation.dashboard.no_hidden_widgets'),
        'delete_row_confirm' => __('translation.dashboard.delete_row_confirm'),
        'empty_column'       => __('translation.dashboard.empty_column'),
        'spacer'             => __('translation.dashboard.spacer'),
    ];
@endphp

<x-app-layout>

    <x-ui.page :title="__('translation.dashboard.title')">
        <x-slot:actions>
            <x-ui.button size="md" icon="layout-grid" id="btn-edit" aria-pressed="false">
                <span id="btn-edit-label">{{ __('translation.dashboard.customize') }}</span>
            </x-ui.button>
            <x-ui.button size="md" icon="plus" id="btn-add" class="hidden">
                {{ __('translation.dashboard.add_widget') }}
            </x-ui.button>
            <x-ui.button size="md" variant="danger" icon="rotate-ccw" id="btn-reset" class="hidden" :label="__('translation.dashboard.reset_layout')" />
        </x-slot:actions>

    {{-- Widget picker --}}
    <div id="picker" class="ui-card ui-card-body hidden">
        <div id="picker-list" class="flex flex-wrap gap-2">
            <button type="button" id="btn-add-spacer" class="ui-btn ui-btn-sm ui-btn-secondary border-dashed">
                <x-ui.icon name="columns-3" /> {{ __('translation.dashboard.spacer') }}
            </button>
            @foreach($hiddenWidgets as $hw)
                <button type="button" data-picker="{{ $hw['id'] }}" class="picker-btn ui-btn ui-btn-sm ui-btn-secondary">
                    <x-ui.nav-icon :name="$hw['icon']" /> {{ __($hw['title']) }}
                </button>
            @endforeach
            <p id="no-hidden" class="{{ count($hiddenWidgets) ? 'hidden' : '' }} ui-help py-1">
                {{ __('translation.dashboard.no_hidden_widgets') }}
            </p>
        </div>
    </div>

    {{-- Dashboard Rows --}}
    <div id="dw-rows">
        @foreach($rows as $row)
        <div class="dw-row" data-row-id="{{ $row['id'] }}" data-layout="{{ $row['layout'] }}">
            <div class="dw-row-bar">
                <span class="dw-row-handle cursor-grab text-fg-muted"><x-ui.icon name="grip-horizontal" /></span>
                <div class="flex items-center gap-1">
                    @foreach(['100','50-50','33-33-33','25-25-25-25','66-33','33-66'] as $preset)
                    <button type="button" data-switch-layout="{{ $preset }}" class="dw-layout-btn {{ $row['layout'] === $preset ? 'active' : '' }}" title="{{ $preset }}">
                        @if($preset === '100')
                            <span style="flex:1"></span>
                        @elseif($preset === '50-50')
                            <span style="flex:1"></span><span style="flex:1"></span>
                        @elseif($preset === '33-33-33')
                            <span style="flex:1"></span><span style="flex:1"></span><span style="flex:1"></span>
                        @elseif($preset === '25-25-25-25')
                            <span style="flex:1"></span><span style="flex:1"></span><span style="flex:1"></span><span style="flex:1"></span>
                        @elseif($preset === '66-33')
                            <span style="flex:2"></span><span style="flex:1"></span>
                        @elseif($preset === '33-66')
                            <span style="flex:1"></span><span style="flex:2"></span>
                        @endif
                    </button>
                    @endforeach
                </div>
                <button type="button" data-delete-row="{{ $row['id'] }}" class="ui-btn ui-btn-xs ui-btn-icon ui-btn-danger ml-auto"><x-ui.icon name="trash-2" /></button>
            </div>

            <div class="dw-row-inner" data-layout="{{ $row['layout'] }}">
                @foreach($row['columns'] as $colIndex => $widgets)
                <div class="dw-column" data-col="{{ $colIndex }}">
                    @foreach($widgets as $item)
                        @php $wId = $item['id']; $wWidth = $item['width'] ?? 100; @endphp
                        @if(str_starts_with($wId, 'spacer-'))
                        <div class="dw-widget dw-spacer" data-id="{{ $wId }}" data-width="{{ $wWidth }}" data-title="{{ __('translation.dashboard.spacer') }}" data-icon="columns-3">
                            <div class="dw-widget-bar">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="grip-vertical" class="text-fg-muted" />
                                    <span class="text-xs font-semibold uppercase tracking-wide text-accent">{{ __('translation.dashboard.spacer') }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @include('backend.partials.dashboard.widget-controls', ['widgetId' => $wId, 'widgetWidth' => $wWidth])
                                </div>
                            </div>
                        </div>
                        @elseif(isset($availableWidgets[$wId]))
                        @php $w = $availableWidgets[$wId]; $isTransparent = !empty($w['transparent']) || str_starts_with($wId, 'plugin-'); @endphp
                        <div class="dw-widget {{ $isTransparent ? 'dw-transparent' : 'ui-card' }}" data-id="{{ $wId }}" data-width="{{ $wWidth }}" data-title="{{ __($w['title']) }}" data-icon="{{ $w['icon'] }}">
                            <div class="dw-widget-bar">
                                <div class="flex items-center gap-2">
                                    <x-ui.icon name="grip-vertical" class="text-fg-muted" />
                                    <span class="text-xs font-semibold uppercase tracking-wide text-accent">{{ __($w['title']) }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    @include('backend.partials.dashboard.widget-controls', ['widgetId' => $wId, 'widgetWidth' => $wWidth])
                                </div>
                            </div>
                            @include($w['view'], $widgetData + ($w['plugin_data'] ?? []))
                        </div>
                        @endif
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    </x-ui.page>

    @push('scripts')
    <script @cspNonce>
    (function() {
        // Server-rendered icons so the JS does not invent markup
        var ICONS = {
            grip: @json(\App\Support\Icon::svg('grip-horizontal')),
            gripV: @json(\App\Support\Icon::svg('grip-vertical')),
            trash: @json(\App\Support\Icon::svg('trash-2')),
            close: @json(\App\Support\Icon::svg('x')),
            plus: @json(\App\Support\Icon::svg('plus')),
        };
        var saveUrl = @json(route('dashboard.layout.save'));
        var resetUrl = @json(route('dashboard.layout.reset'));
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var T = @json($dashT);
        // Safely insert texts into HTML snippets (attributes included)
        function esc(text) {
            var d = document.createElement('div');
            d.textContent = text == null ? '' : String(text);
            return d.innerHTML.replace(/"/g, '&quot;');
        }
        var container = document.getElementById('dw-rows');
        var editing = false;
        var rowSortable = null;
        var colSortables = [];
        var layouts = ['100','50-50','33-33-33','25-25-25-25','66-33','33-66'];
        var colCounts = { '100':1, '50-50':2, '33-33-33':3, '25-25-25-25':4, '66-33':2, '33-66':2 };

        // ── Edit toggle ──
        document.getElementById('btn-edit').addEventListener('click', function() {
            editing = !editing;
            document.getElementById('btn-edit-label').textContent = editing ? T.done : T.customize;
            document.getElementById('btn-add').classList.toggle('hidden', !editing);
            document.getElementById('btn-reset').classList.toggle('hidden', !editing);
            this.classList.toggle('ui-btn-primary', editing);
            this.classList.toggle('ui-btn-secondary', !editing);
            this.setAttribute('aria-pressed', editing ? 'true' : 'false');
            container.classList.toggle('editing', editing);
            if (editing) { initSortables(); } else { destroySortables(); document.getElementById('picker').classList.add('hidden'); closeAllPickers(); save(); }
        });

        function initSortables() {
            destroySortables();
            rowSortable = new Sortable(container, { animation: 200, handle: '.dw-row-handle', draggable: '.dw-row', ghostClass: 'sortable-ghost', forceFallback: true, fallbackOnBody: true });
            initColSortables();
        }
        function initColSortables() {
            colSortables.forEach(function(s) { s.destroy(); }); colSortables = [];
            container.querySelectorAll('.dw-column').forEach(function(col) {
                colSortables.push(new Sortable(col, { group: 'widgets', animation: 200, handle: '.dw-widget-bar', draggable: '.dw-widget', ghostClass: 'sortable-ghost', forceFallback: true, fallbackOnBody: true, fallbackTolerance: 3 }));
            });
        }
        function destroySortables() {
            if (rowSortable) { rowSortable.destroy(); rowSortable = null; }
            colSortables.forEach(function(s) { s.destroy(); }); colSortables = [];
        }

        // ── Widget width ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-set-width]');
            if (!btn) return;
            var widget = btn.closest('.dw-widget');
            if (!widget) return;
            var w = btn.dataset.setWidth;
            widget.dataset.width = w;
            widget.querySelectorAll('[data-set-width]').forEach(function(b) { b.classList.toggle('active', b.dataset.setWidth === w); });
        });

        // ── Layout switch ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-switch-layout]');
            if (!btn) return;
            var row = btn.closest('.dw-row');
            var inner = row.querySelector('.dw-row-inner');
            var nl = btn.dataset.switchLayout;
            if (inner.dataset.layout === nl) return;
            var oldCols = inner.querySelectorAll('.dw-column');
            var nc = colCounts[nl];
            if (nc < oldCols.length) {
                var last = oldCols[nc - 1];
                for (var i = nc; i < oldCols.length; i++) { while (oldCols[i].firstElementChild) last.appendChild(oldCols[i].firstElementChild); oldCols[i].remove(); }
            }
            for (var i = oldCols.length; i < nc; i++) { var c = document.createElement('div'); c.className = 'dw-column'; c.dataset.col = i; inner.appendChild(c); }
            inner.dataset.layout = nl; row.dataset.layout = nl;
            row.querySelectorAll('[data-switch-layout]').forEach(function(b) { b.classList.toggle('active', b.dataset.switchLayout === nl); });
            initColSortables();
        });

        // ── Delete row ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-delete-row]');
            if (!btn) return;
            if (!confirm(T.delete_row_confirm)) return;
            var row = btn.closest('.dw-row');
            row.querySelectorAll('.dw-widget').forEach(function(w) {
                if (w.dataset.id.indexOf('spacer-') !== 0) addToPicker(w.dataset.id, w.dataset.title, w.dataset.icon);
            });
            row.remove(); initColSortables();
        });

        // ── Add row ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dw-add-row-btn');
            if (!btn) return;
            var picker = btn.closest('.dw-add-row').querySelector('.dw-layout-picker');
            var open = picker.classList.contains('open');
            closeAllPickers();
            if (!open) picker.classList.add('open');
        });
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-new-row-layout]');
            if (!btn) return;
            var layout = btn.dataset.newRowLayout;
            var wrapper = btn.closest('.dw-add-row');
            closeAllPickers();
            wrapper.insertAdjacentHTML('beforebegin', buildRowHtml('row-' + Date.now(), layout));
            initColSortables();
        });
        function closeAllPickers() { document.querySelectorAll('.dw-layout-picker.open').forEach(function(p) { p.classList.remove('open'); }); }

        function buildRowHtml(id, layout) {
            var nc = colCounts[layout];
            var h = '<div class="dw-row" data-row-id="' + id + '" data-layout="' + layout + '">';
            h += '<div class="dw-row-bar"><span class="dw-row-handle cursor-grab text-fg-muted">' + ICONS.grip + '</span><div class="flex items-center gap-1">';
            layouts.forEach(function(p) { h += '<button type="button" data-switch-layout="' + p + '" class="dw-layout-btn' + (p===layout?' active':'') + '" title="' + p + '">' + layoutIconHtml(p) + '</button>'; });
            h += '</div><button type="button" data-delete-row="' + id + '" class="ui-btn ui-btn-xs ui-btn-icon ui-btn-danger ml-auto">' + ICONS.trash + '</button></div>';
            h += '<div class="dw-row-inner" data-layout="' + layout + '">';
            for (var i = 0; i < nc; i++) h += '<div class="dw-column" data-col="' + i + '"></div>';
            h += '</div></div>';
            return h;
        }
        function layoutIconHtml(p) {
            var s = {'100':[1],'50-50':[1,1],'33-33-33':[1,1,1],'25-25-25-25':[1,1,1,1],'66-33':[2,1],'33-66':[1,2]};
            return (s[p]||[1]).map(function(f){return '<span style="flex:'+f+'"></span>';}).join('');
        }

        // ── Remove widget ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-remove]');
            if (!btn) return;
            var id = btn.dataset.remove;
            var el = container.querySelector('[data-id="' + id + '"]');
            if (!el) return;
            if (id.indexOf('spacer-') === 0) { el.remove(); return; }
            addToPicker(id, el.dataset.title, el.dataset.icon);
            el.remove();
        });
        function addToPicker(id, title, icon) {
            var list = document.getElementById('picker-list');
            var nb = document.createElement('button');
            nb.type = 'button'; nb.dataset.picker = id;
            nb.className = 'picker-btn ui-btn ui-btn-sm ui-btn-secondary';
            nb.textContent = title || id;
            list.appendChild(nb);
            document.getElementById('no-hidden').classList.add('hidden');
        }

        // ── Add spacer ──
        document.getElementById('btn-add-spacer').addEventListener('click', function() {
            var id = 'spacer-' + Date.now();
            var rowId = 'row-' + Date.now();
            var rowHtml = buildRowHtml(rowId, '100');
            var firstRow = container.querySelector('.dw-row');
            if (firstRow) { firstRow.insertAdjacentHTML('beforebegin', rowHtml); }
            else { container.insertAdjacentHTML('afterbegin', rowHtml); }
            var newCol = container.querySelector('[data-row-id="' + rowId + '"] .dw-column');
            if (newCol) {
                newCol.insertAdjacentHTML('beforeend', '<div class="dw-widget dw-spacer" data-id="'+id+'" data-width="100" data-title="' + esc(T.spacer) + '" data-icon="columns-3"><div class="dw-widget-bar"><div class="flex items-center gap-2">' + ICONS.gripV + '<span class="text-xs font-semibold uppercase tracking-wide text-accent">' + esc(T.spacer) + '</span></div><div class="flex items-center gap-1">' + widthBtnsHtml(id, 100) + '</div></div></div>');
            }
            initColSortables();
        });
        function widthBtnsHtml(id, active) {
            var ws = [{v:25,l:'&#188;'},{v:33,l:'&#8531;'},{v:50,l:'&#189;'},{v:66,l:'&#8532;'},{v:100,l:'1'}];
            var h = '';
            ws.forEach(function(w) { h += '<button type="button" data-set-width="'+w.v+'" class="dw-w-btn'+(w.v==active?' active':'')+'" title="'+w.v+'%">'+w.l+'</button>'; });
            h += '<span class="mx-0.5 h-4 w-px bg-line"></span>';
            h += '<button type="button" data-remove="'+id+'" class="ui-btn ui-btn-xs ui-btn-icon ui-btn-danger">' + ICONS.close + '</button>';
            return h;
        }

        // ── Add widget from picker ──
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.picker-btn');
            if (!btn || !btn.dataset.picker) return;
            var layout = collectLayout();
            var item = { id: btn.dataset.picker, width: 100 };
            if (layout.rows.length) { var lr = layout.rows[layout.rows.length-1]; lr.columns[lr.columns.length-1].push(item); }
            else { layout.rows.push({ id:'row-'+Date.now(), layout:'100', columns:[[item]] }); }
            layout.hidden = layout.hidden.filter(function(h){return h!==btn.dataset.picker;});
            fetch(saveUrl, { method:'PUT', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(layout) })
            .then(function(){ var url=new URL(window.location); url.searchParams.set('picker','1'); window.location.href=url.toString(); });
        });

        document.getElementById('btn-add').addEventListener('click', function() { document.getElementById('picker').classList.toggle('hidden'); });
        document.getElementById('btn-reset').addEventListener('click', function() {
            if (!confirm(T.reset_confirm)) return;
            fetch(resetUrl, { method:'DELETE', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf} }).then(function(){ window.location.reload(); });
        });

        function getLastColumn() { var cols = container.querySelectorAll('.dw-column'); return cols.length ? cols[cols.length-1] : null; }

        // ── Collect layout ──
        function collectLayout() {
            var rows = [];
            container.querySelectorAll('.dw-row').forEach(function(rowEl) {
                var columns = [];
                rowEl.querySelectorAll('.dw-column').forEach(function(colEl) {
                    var widgets = [];
                    colEl.querySelectorAll('.dw-widget').forEach(function(wEl) {
                        widgets.push({ id: wEl.dataset.id, width: parseInt(wEl.dataset.width) || 100 });
                    });
                    columns.push(widgets);
                });
                rows.push({ id: rowEl.dataset.rowId, layout: rowEl.dataset.layout, columns: columns });
            });
            var hidden = [];
            document.querySelectorAll('.picker-btn[data-picker]').forEach(function(b) {
                if (b.dataset.picker.indexOf('spacer-') !== 0) hidden.push(b.dataset.picker);
            });
            return { rows: rows, hidden: hidden };
        }

        function save() {
            fetch(saveUrl, { method:'PUT', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(collectLayout()) });
        }

        // ── Auto-open picker ──
        var params = new URLSearchParams(window.location.search);
        if (params.has('picker')) {
            // Only after the module scripts: window.Sortable is set by backend/app.js,
            // which runs as a module later than this inline script
            var openPicker = function () {
                document.getElementById('btn-edit').click();
                document.getElementById('picker').classList.remove('hidden');
                params.delete('picker');
                history.replaceState(null, '', window.location.pathname + (params.toString() ? '?'+params.toString() : ''));
            };
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', openPicker);
            else openPicker();
        }

        // ── Inject add-row buttons ──
        function injectAddRowBtns() {
            container.querySelectorAll('.dw-add-row').forEach(function(el) { el.remove(); });
            var rowEls = container.querySelectorAll('.dw-row');
            var html = buildAddRowHtml();
            if (rowEls.length) rowEls[0].insertAdjacentHTML('beforebegin', html);
            rowEls.forEach(function(r) { r.insertAdjacentHTML('afterend', html); });
            if (!rowEls.length) container.insertAdjacentHTML('beforeend', html);
        }
        function buildAddRowHtml() {
            var h = '<div class="dw-add-row"><div class="text-center"><button type="button" class="dw-add-row-btn">' + ICONS.plus + ' '+T.add_row+'</button>';
            h += '<div class="dw-layout-picker">';
            layouts.forEach(function(p) { h += '<button type="button" data-new-row-layout="'+p+'" class="dw-layout-btn" title="'+p+'">'+layoutIconHtml(p)+'</button>'; });
            h += '</div></div></div>';
            return h;
        }
        injectAddRowBtns();
        new MutationObserver(function(muts) {
            var need = false;
            muts.forEach(function(m) {
                m.addedNodes.forEach(function(n) { if (n.classList && n.classList.contains('dw-row')) need=true; });
                m.removedNodes.forEach(function(n) { if (n.classList && n.classList.contains('dw-row')) need=true; });
            });
            if (need) injectAddRowBtns();
        }).observe(container, { childList: true });
    })();
    </script>
    @endpush
</x-app-layout>
