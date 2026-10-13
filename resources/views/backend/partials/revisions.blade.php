{{--
    Revisions of a piece of content — for every content type from App\Support\Revisionables:
        @include('backend.partials.revisions', ['revisionable' => $post, 'revisionType' => 'post', 'revisionsAutoload' => true])
    revisionsAutoload: load immediately (otherwise on clicking the data-tab="revisions" tab
    or with ?tab=revisions in the URL).
--}}
@php
    $revisionRoute = ['type' => $revisionType, 'id' => $revisionable->getKey()];
    $revisionsAutoload = $revisionsAutoload ?? false;
@endphp
@php
    $revT = [
        'no_revisions' => __('translation.pages.revisions.no_revisions'),
        'restore_title' => __('translation.pages.revisions.restore_title'),
        'restore_text' => __('translation.pages.revisions.restore_text', ['date' => ':date']),
        'restore_this' => __('translation.pages.revisions.restore_this'),
        'delete_confirm' => __('translation.pages.revisions.delete_confirm'),
        'loading' => __('translation.pages.revisions.loading'),
        'no_changes' => __('translation.pages.revisions.no_changes'),
        'compare' => __('translation.pages.revisions.compare'),
        'restore' => __('translation.pages.revisions.restore'),
        'preview' => __('translation.pages.revisions.preview'),
        'preview_translation' => __('translation.pages.revisions.preview_translation'),
        'more' => __('translation.pages.revisions.more'),
        'afterwards' => __('translation.pages.revisions.afterwards'),
        'before_restore' => __('translation.pages.revisions.before_restore'),
        'against_current' => __('translation.pages.revisions.against_current'),
        'against_next' => __('translation.pages.revisions.against_next'),
        // Singular/plural resolved up front, the script fills in :count
        'changes' => trans_choice('translation.pages.revisions.changes', 2, ['count' => ':count']),
        'changes_one' => trans_choice('translation.pages.revisions.changes', 1, ['count' => ':count']),
        'blocks_added' => __('translation.pages.revisions.blocks_added'),
        'blocks_removed' => __('translation.pages.revisions.blocks_removed'),
        'blocks_changed' => __('translation.pages.revisions.blocks_changed'),
        'blocks_reordered' => __('translation.pages.revisions.blocks_reordered'),
        'blocks_empty' => __('translation.pages.revisions.blocks_empty'),
        'blocks_settings' => __('translation.pages.revisions.blocks_settings'),
        'error' => __('translation.script.error'),
        'delete' => __('translation.delete'),
    ];
@endphp

<div id="revisions-list" class="space-y-2">
    <p class="text-fg-muted text-sm py-8 text-center">{{ __('translation.pages.revisions.loading') }}</p>
</div>

{{-- Comparison. The script assembles the block row colours; for Tailwind: text-positive text-critical text-accent bg-positive-soft bg-critical-soft bg-accent-soft --}}
<div id="revision-diff-modal" class="hidden fixed inset-0 z-9999 flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="diff-modal-title">
    <div class="ui-card flex w-full max-w-4xl max-h-[85vh] flex-col overflow-hidden mx-4">
        <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-line">
            <div class="flex min-w-0 items-center gap-2">
                <h4 id="diff-modal-title" class="truncate font-semibold text-fg text-sm"></h4>
                <span id="diff-modal-locale" class="ui-badge hidden"></span>
            </div>
            <x-ui.button variant="ghost" icon="x" id="revision-diff-close" :label="__('translation.close')" />
        </div>

        {{-- What to compare against: today (what restoring changes) or the next version --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-4">
            <div class="inline-flex rounded-lg border border-line p-0.5" role="group" aria-label="{{ __('translation.pages.revisions.compare') }}">
                <button type="button" data-diff-against="current" class="ui-btn ui-btn-sm ui-btn-ghost">{{ __('translation.pages.revisions.against_current') }}</button>
                <button type="button" data-diff-against="next" class="ui-btn ui-btn-sm ui-btn-ghost">{{ __('translation.pages.revisions.against_next') }}</button>
            </div>
            <p id="diff-modal-legend" class="flex items-center gap-3 text-xs text-fg-muted"></p>
        </div>

        <div id="diff-modal-body" class="min-h-0 flex-1 overflow-y-auto px-6 py-4"></div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-6 py-3">
            <a id="diff-modal-preview" href="#" target="_blank" rel="noopener" class="ui-btn ui-btn-md ui-btn-secondary">
                <x-ui.icon name="eye" /> {{ __('translation.pages.revisions.preview') }}
            </a>
            <button type="button" id="diff-modal-restore" class="ui-btn ui-btn-md ui-btn-primary">
                <x-ui.icon name="rotate-ccw" /> {{ __('translation.pages.revisions.restore_this') }}
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script @cspNonce>
    // Server-rendered icons — Blade components must not appear in
    // JS strings, their multi-line SVG breaks the string literal.
    var ICONS = {
        compare: @json(\App\Support\Icon::svg('git-compare')),
        history: @json('<span class="text-3xl text-fg-muted mb-3 block">'.\App\Support\Icon::svg('history').'</span>'),
        clock: @json('<span class="text-fg-muted">'.\App\Support\Icon::svg('clock').'</span>'),
        history_row: @json(\App\Support\Icon::svg('history')),
        restore: @json(\App\Support\Icon::svg('rotate-ccw')),
        more: @json(\App\Support\Icon::svg('ellipsis')),
        eye: @json(\App\Support\Icon::svg('eye')),
        trash: @json(\App\Support\Icon::svg('trash-2')),
        ok: @json('<span class="text-3xl text-positive mb-3 block">'.\App\Support\Icon::svg('circle-check').'</span>'),
    };

(function() {
    var listUrl = @json(route('backend.revisions.index', $revisionRoute));
    var showUrlTpl = @json(route('backend.revisions.show', $revisionRoute + ['revision' => 0]));
    var restoreUrlTpl = @json(route('backend.revisions.restore', $revisionRoute + ['revision' => 0]));
    var previewUrlTpl = @json(route('backend.revisions.preview', $revisionRoute + ['revision' => 0]));
    var deleteUrlTpl = @json(route('backend.revisions.destroy', $revisionRoute + ['revision' => 0]));
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    var T = @json($revT);
    var revisions = {};
    var shown = null;   // { id, against }

    function revUrl(tpl, id) { return tpl.replace(/\/0(\/|$)/, '/' + id + '$1'); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    // Update badge on revisions tab button
    function updateBadge(count) {
        var btn = document.querySelector('[data-tab="revisions"]');
        if (!btn) return;
        var badge = btn.querySelector('.rev-badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'rev-badge ui-tab-count';
                btn.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }

    function confirmBox(options) {
        // Backend-style dialog; without it (foreign layout) the browser's
        return typeof window.uiConfirm === 'function'
            ? window.uiConfirm(options)
            : Promise.resolve(window.confirm(options.text || options.title));
    }

    function loadRevisions() {
        fetch(listUrl, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var el = document.getElementById('revisions-list');
                updateBadge(data.count);
                revisions = {};

                if (!data.revisions.length) {
                    el.innerHTML = '<div class="py-12 text-center">' + ICONS.history + '<p class="text-fg-muted text-sm">' + esc(T.no_revisions) + '</p></div>';
                    return;
                }

                var h = '<div class="ui-list" role="list">';
                data.revisions.forEach(function(rev, i) {
                    revisions[rev.id] = rev;
                    var badges = (rev.locale ? ' <span class="ui-badge">' + esc(rev.locale) + '</span>' : '')
                      + (rev.before_restore ? ' <span class="ui-badge ui-badge-caution">' + esc(T.before_restore) + '</span>' : '');
                    var meta = '<span>' + esc(rev.created_at_diff) + '</span><span>' + esc(rev.user) + '</span>';
                    h += '<div class="ui-list-row cursor-pointer" role="listitem" data-rev-row="' + rev.id + '">'
                      + '<span class="ui-list-lead"><span class="ui-chip ui-chip-sm ui-tint-' + (i === 0 ? 'accent' : 'neutral') + '">' + ICONS.history_row + '</span></span>'
                      + '<div class="ui-list-main"><span class="ui-list-title">' + esc(rev.created_at) + badges + '</span>'
                      + '<span class="ui-list-meta">' + meta + '</span>'
                      // What was changed after this state — so the right version can be found
                      + '<span class="mt-0.5 block truncate text-xs text-fg-muted" title="' + esc(T.afterwards + ' ' + rev.summary) + '"><span class="font-medium text-fg">' + esc(T.afterwards) + '</span> ' + esc(rev.summary) + '</span></div>'
                      + '<div class="ui-list-actions">'
                      + '<button type="button" data-rev-diff="' + rev.id + '" class="ui-btn ui-btn-sm ui-btn-icon ui-btn-ghost" title="' + esc(T.compare) + '" aria-label="' + esc(T.compare) + '">' + ICONS.compare + '</button>'
                      + '<button type="button" data-rev-restore="' + rev.id + '" class="ui-btn ui-btn-sm ui-btn-icon ui-btn-ghost" title="' + esc(T.restore) + '" aria-label="' + esc(T.restore) + '">' + ICONS.restore + '</button>'
                      + '<details class="ui-menu" data-menu><summary class="ui-btn ui-btn-sm ui-btn-icon ui-btn-ghost ui-menu-trigger" title="' + esc(T.more) + '" aria-label="' + esc(T.more) + '">' + ICONS.more + '</summary>'
                      + '<ul class="ui-menu-list">'
                      + (rev.previewable
                          ? '<li><a class="ui-menu-item justify-start" href="' + revUrl(previewUrlTpl, rev.id) + '" target="_blank" rel="noopener">' + ICONS.eye + ' ' + esc(T.preview) + '</a></li>'
                          : '<li><span class="ui-menu-item justify-start text-fg-muted" title="' + esc(T.preview_translation) + '">' + ICONS.eye + ' ' + esc(T.preview) + '</span></li>')
                      + '<li><button type="button" class="ui-menu-item w-full justify-start text-critical" data-rev-delete="' + rev.id + '">' + ICONS.trash + ' ' + esc(T.delete) + '</button></li>'
                      + '</ul></details>'
                      + '</div></div>';
                });
                h += '</div>';
                el.innerHTML = h;
            });
    }

    function restoreRevision(id) {
        var rev = revisions[id] || {};
        confirmBox({
            title: T.restore_title,
            text: T.restore_text.replace(':date', rev.created_at || ''),
            confirm: T.restore_this,
            icon: 'restore',
        }).then(function (ok) {
            if (!ok) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = revUrl(restoreUrlTpl, id);
            form.innerHTML = '<input type="hidden" name="_token" value="' + esc(csrf) + '">';
            document.body.appendChild(form);
            form.submit();
        });
    }

    function renderDiff(data) {
        var body = document.getElementById('diff-modal-body');
        var legend = document.getElementById('diff-modal-legend');
        legend.innerHTML = '<span class="text-critical font-semibold">&minus;</span> ' + esc(data.from_label)
            + ' <span class="text-positive font-semibold">+</span> ' + esc(data.to_label);

        if (data.diff_count === 0) {
            body.innerHTML = '<div class="py-12 text-center">' + ICONS.ok + '<p class="text-fg-muted">' + esc(T.no_changes) + '</p></div>';
            return;
        }

        var h = '<p class="text-xs text-fg-muted mb-4">' + (data.diff_count === 1 ? T.changes_one : T.changes).replace(':count', data.diff_count) + '</p><div class="space-y-3">';
        for (var field in data.diff) {
            var d = data.diff[field];

            h += '<div class="rounded-lg border border-line overflow-hidden">'
              + '<div class="px-4 py-2 bg-surface-sunken/50 border-b border-line flex items-center justify-between">'
              + '<span class="text-xs font-semibold text-fg-muted uppercase tracking-wide">' + esc(d.label || field) + '</span>'
              + '</div>';

            if (d.kind === 'blocks') {
                // Blocks: what was added, removed or changed — with type and text excerpt
                var line = function (sign, tone, label, text) {
                    return '<div class="flex gap-2"><span class="shrink-0 text-xs font-semibold text-' + tone + ' w-6">' + sign + '</span>'
                        + '<div class="flex-1 min-w-0 text-xs rounded px-2 py-1 bg-' + tone + '-soft"><span class="font-semibold text-fg">' + esc(label) + '</span>'
                        + '<span class="text-fg-muted"> — ' + esc(text || T.blocks_empty) + '</span></div></div>';
                };
                h += '<div class="p-4 space-y-3">';
                if (d.added.length) {
                    h += '<p class="text-xs font-semibold text-fg-muted">' + esc(T.blocks_added) + '</p>';
                    d.added.forEach(function (b) { h += line('+', 'positive', b.label, b.text); });
                }
                if (d.removed.length) {
                    h += '<p class="text-xs font-semibold text-fg-muted">' + esc(T.blocks_removed) + '</p>';
                    d.removed.forEach(function (b) { h += line('&minus;', 'critical', b.label, b.text); });
                }
                if (d.changed.length) {
                    h += '<p class="text-xs font-semibold text-fg-muted">' + esc(T.blocks_changed) + '</p>';
                    d.changed.forEach(function (b) {
                        if (b.settings_only) {
                            h += line('~', 'accent', b.label, T.blocks_settings);
                        } else {
                            if (b.before) h += line('&minus;', 'critical', b.label, b.before);
                            if (b.after) h += line('+', 'positive', b.label, b.after);
                        }
                    });
                }
                if (d.reordered) {
                    h += '<p class="text-xs text-fg-muted">' + esc(T.blocks_reordered) + '</p>';
                }
                h += '</div>';
            } else {
                // Long texts (content, CSS) scroll instead of being hidden
                h += '<div class="p-4 space-y-2">';
                if (d.revision) {
                    h += '<div class="flex gap-2"><span class="shrink-0 text-xs font-semibold text-critical w-6">&minus;</span><pre class="text-xs whitespace-pre-wrap wrap-break-word text-critical bg-critical-soft rounded px-2 py-1 flex-1 max-h-60 overflow-auto">' + esc(d.revision) + '</pre></div>';
                }
                if (d.current) {
                    h += '<div class="flex gap-2"><span class="shrink-0 text-xs font-semibold text-positive w-6">+</span><pre class="text-xs whitespace-pre-wrap wrap-break-word text-positive bg-positive-soft rounded px-2 py-1 flex-1 max-h-60 overflow-auto">' + esc(d.current) + '</pre></div>';
                }
                h += '</div>';
            }
            h += '</div>';
        }
        h += '</div>';
        body.innerHTML = h;
    }

    window.showRevisionDiff = function(id, against) {
        against = against || 'current';
        shown = { id: id, against: against };
        var modal = document.getElementById('revision-diff-modal');
        var body = document.getElementById('diff-modal-body');
        var title = document.getElementById('diff-modal-title');
        var badge = document.getElementById('diff-modal-locale');
        var preview = document.getElementById('diff-modal-preview');
        var rev = revisions[id] || {};

        document.querySelectorAll('[data-diff-against]').forEach(function (btn) {
            var on = btn.getAttribute('data-diff-against') === against;
            btn.classList.toggle('ui-btn-secondary', on);
            btn.classList.toggle('ui-btn-ghost', !on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        preview.classList.toggle('hidden', !rev.previewable);
        preview.href = revUrl(previewUrlTpl, id);

        body.innerHTML = '<p class="text-center text-fg-muted py-8">' + ICONS.clock + ' ' + esc(T.loading) + '</p>';
        modal.classList.remove('hidden');

        fetch(revUrl(showUrlTpl, id) + '?against=' + against, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                title.textContent = data.created_at + (data.user ? ' — ' + data.user : '');
                badge.textContent = data.locale || '';
                badge.classList.toggle('hidden', !data.locale);
                renderDiff(data);
            })
            .catch(function() { body.innerHTML = '<p class="text-center text-critical py-8">' + esc(T.error) + '</p>'; });
    };

    window.closeRevisionDiff = function() {
        document.getElementById('revision-diff-modal').classList.add('hidden');
        shown = null;
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && shown && !document.querySelector('#global-confirm-modal[style*="flex"]')) closeRevisionDiff();
    });
    document.getElementById('revision-diff-modal').addEventListener('click', function(e) { if (e.target === this) closeRevisionDiff(); });
    document.getElementById('revision-diff-close').addEventListener('click', closeRevisionDiff);
    document.querySelectorAll('[data-diff-against]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (shown) showRevisionDiff(shown.id, btn.getAttribute('data-diff-against'));
        });
    });
    document.getElementById('diff-modal-restore').addEventListener('click', function () {
        if (shown) restoreRevision(shown.id);
    });

    // Delegated handlers for the dynamically rendered revision rows
    document.getElementById('revisions-list').addEventListener('click', function(e) {
        var restoreBtn = e.target.closest('[data-rev-restore]');
        if (restoreBtn) { restoreRevision(parseInt(restoreBtn.getAttribute('data-rev-restore'), 10)); return; }
        var delBtn = e.target.closest('[data-rev-delete]');
        if (delBtn) { delBtn.closest('details')?.removeAttribute('open'); deleteRevision(parseInt(delBtn.getAttribute('data-rev-delete'), 10)); return; }
        var diffBtn = e.target.closest('[data-rev-diff]');
        if (diffBtn) { showRevisionDiff(parseInt(diffBtn.getAttribute('data-rev-diff'), 10)); return; }
        // A click on the row itself (not on the menu or a link) opens the comparison
        if (e.target.closest('details, a, button')) return;
        var row = e.target.closest('[data-rev-row]');
        if (row) showRevisionDiff(parseInt(row.getAttribute('data-rev-row'), 10));
    });

    window.deleteRevision = function(id) {
        confirmBox({ text: T.delete_confirm, confirm: T.delete, tone: 'danger' }).then(function (ok) {
            if (!ok) return;
            fetch(revUrl(deleteUrlTpl, id), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
            }).then(function() { loadRevisions(); });
        });
    };

    var loaded = false;
    document.addEventListener('click', function(e) {
        if (e.target.closest('[data-tab="revisions"]') && !loaded) { loaded = true; loadRevisions(); }
    });
    if (@json($revisionsAutoload) || new URLSearchParams(window.location.search).get('tab') === 'revisions') { loaded = true; loadRevisions(); }
})();
</script>
@endpush
