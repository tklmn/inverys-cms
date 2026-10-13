<div class="flex items-center gap-1">
    {{-- Quick search (ui/command.js), Ctrl/⌘ + K: menu items instantly, plus pages, content and settings from backend.search --}}
    <button
        type="button"
        class="ui-cmd-trigger"
        data-command-open
        data-command-search="{{ route('backend.search') }}"
        aria-label="{{ __('translation.script.search') }}"
        aria-keyshortcuts="Control+K Meta+K"
    >
        <x-ui.icon name="search" />
        <span class="ui-cmd-trigger-label">{{ __('translation.script.search') }}</span>
        <kbd class="ui-kbd" data-command-key>{{ __('translation.script.search_shortcut') }}</kbd>
    </button>

    {{-- Search engines blocked: easy to forget before going live, hence visible here --}}
    @permission('manage-seo-settings')
        @unless(\App\Support\SeoSettings::allowIndexing())
            <a href="{{ route('backend.settings.seo') }}" class="ui-badge ui-badge-critical ui-badge-plain me-1" title="{{ __('translation.seo.indexing_blocked_title') }}" aria-label="{{ __('translation.seo.indexing_blocked_title') }}" data-indexing-blocked>
                <x-ui.icon name="shield-off" />
                <span class="hidden sm:inline">{{ __('translation.seo.indexing_blocked_badge') }}</span>
            </a>
        @endunless
    @endpermission

    <x-ui.theme-toggle />

    {{-- Notifications: per user, so here instead of in the navigation --}}
    @permission('show-notifications')
    <div class="relative" id="notification-wrapper">
        <button id="notification-bell" class="ui-btn ui-btn-md ui-btn-icon ui-btn-ghost relative" aria-label="{{ __('translation.notifications.title') }}" aria-haspopup="true" aria-expanded="false">
            <x-ui.icon name="bell" />
            <span id="notification-badge" class="ui-notify-badge hidden"></span>
        </button>
        <div id="notification-dropdown" class="ui-notify hidden">
            <div class="ui-notify-head">
                <h2 class="ui-card-title">{{ __('translation.notifications.title') }}</h2>
                <x-ui.button variant="ghost" size="xs" icon="check-check" id="notification-mark-all">{{ __('translation.notifications.mark_all_read') }}</x-ui.button>
            </div>
            <div id="notification-list" class="ui-notify-list">
                <div class="ui-empty py-8">
                    <span class="ui-empty-icon"><x-ui.icon name="loader-circle" class="animate-spin" /></span>
                </div>
            </div>
            <a href="{{ route('backend.notifications.index') }}" class="ui-notify-foot">
                {{ __('translation.notifications.view_all') }}
                <x-ui.icon name="arrow-right" />
            </a>
        </div>
    </div>
    @endpermission

    {{-- Frontend --}}
    <a href="{{ url('/') }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-md ui-btn-icon ui-btn-ghost" title="{{ __('translation.dashboard.frontend') }}" aria-label="{{ __('translation.dashboard.frontend') }}">
        <x-ui.icon name="external-link" />
    </a>

    <div class="mx-1 h-5 w-px bg-line"></div>

    {{-- Account: profile picture or initials as in the user list; the name gives way on phones --}}
    <a href="{{ route('backend.profile') }}" class="ui-account" title="{{ Auth::user()->email }}" aria-label="{{ __('translation.profile') }}">
        <x-ui.avatar :user="Auth::user()" size="sm" />
        <span class="ui-account-name">{{ Auth::user()->name }}</span>
    </a>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="ui-btn ui-btn-md ui-btn-icon ui-btn-ghost" title="{{ __('translation.logout') }}" aria-label="{{ __('translation.logout') }}">
            <x-ui.icon name="log-out" />
        </button>
    </form>
</div>

<script @cspNonce>
    // Server-rendered icons for the dynamically built list — as a
    // tinted chip like on the notifications page (ui-tint-*)
    var ICONS = {
        critical: @json('<span class="ui-chip ui-chip-sm ui-tint-critical">'.\App\Support\Icon::svg('triangle-alert').'</span>'),
        positive: @json('<span class="ui-chip ui-chip-sm ui-tint-positive">'.\App\Support\Icon::svg('circle-arrow-up').'</span>'),
        caution: @json('<span class="ui-chip ui-chip-sm ui-tint-caution">'.\App\Support\Icon::svg('shield-alert').'</span>'),
        neutral: @json('<span class="ui-chip ui-chip-sm ui-tint-neutral">'.\App\Support\Icon::svg('bell').'</span>'),
        empty: @json(\App\Support\Icon::svg('bell-off')),
    };


// Notification dropdown
(function () {
    var bell = document.getElementById('notification-bell');
    var dropdown = document.getElementById('notification-dropdown');
    var badge = document.getElementById('notification-badge');
    var list = document.getElementById('notification-list');
    var markAllBtn = document.getElementById('notification-mark-all');
    if (!bell) return;

    var csrf = '{{ csrf_token() }}';
    var open = false;

    function fetchCount() {
        fetch('{{ route("backend.notifications.unread-count") }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.count > 0) {
                    badge.textContent = d.count > 99 ? '99+' : d.count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }).catch(function () {});
    }

    function typeIcon(type) {
        if (type === 'http_error') return ICONS.critical;
        if (type === 'laravel_update' || type === 'cms_update') return ICONS.positive;
        if (type === 'failed_login') return ICONS.caution;
        return ICONS.neutral;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function loadList() {
        fetch('{{ route("backend.notifications.data") }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (items) {
                if (!items.length) {
                    list.innerHTML = '<div class="ui-empty py-8"><span class="ui-empty-icon">' + ICONS.empty + '</span><p class="ui-empty-text">{{ __("translation.notifications.no_notifications") }}</p></div>';
                    return;
                }
                var html = '';
                items.forEach(function (n) {
                    // Title, below it text and time on one line; unread = dot + tinted row
                    html += '<button type="button" class="ui-notify-item" data-id="' + esc(n.id) + '"' + (n.is_read ? '' : ' data-unread') + '>';
                    html += typeIcon(n.type);
                    html += '<span class="ui-notify-text">';
                    html += '<span class="ui-notify-title">' + esc(n.title) + '</span>';
                    html += '<span class="ui-notify-meta">' + (n.body ? '<span>' + esc(n.body) + '</span>' : '') + '<span>' + esc(n.created_at) + '</span></span>';
                    html += '</span>';
                    html += '<span class="ui-notify-dot" aria-hidden="true"></span>';
                    html += '</button>';
                });
                list.innerHTML = html;

                list.querySelectorAll('.ui-notify-item').forEach(function (el) {
                    el.addEventListener('click', function () {
                        var id = el.getAttribute('data-id');
                        fetch('{{ url("/backend/notifications") }}/' + id + '/read', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                        }).then(function () {
                            el.removeAttribute('data-unread');
                            fetchCount();
                        });
                    });
                });
            }).catch(function () {});
    }

    bell.addEventListener('click', function (e) {
        e.stopPropagation();
        open = !open;
        dropdown.classList.toggle('hidden', !open);
        bell.setAttribute('aria-expanded', String(open));
        if (open) loadList();
    });

    document.addEventListener('click', function (e) {
        if (open && !dropdown.contains(e.target) && !bell.contains(e.target)) {
            open = false;
            dropdown.classList.add('hidden');
            bell.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (open && e.key === 'Escape') {
            open = false;
            dropdown.classList.add('hidden');
            bell.setAttribute('aria-expanded', 'false');
            bell.focus();
        }
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch('{{ route("backend.notifications.mark-all-read") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            }).then(function () {
                fetchCount();
                loadList();
            });
        });
    }

    fetchCount();
}());
</script>
