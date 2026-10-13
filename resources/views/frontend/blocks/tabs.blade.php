@php
    // Build a lookup: tabId => title from the parent's data.tabs array
    $tabTitles = collect($data['tabs'] ?? [])->pluck('title', 'id')->all();
    $firstTabId = $rawChildren[0]['id'] ?? '';
    $tabsContainerId = 'tabs-' . uniqid();
    $label = trim((string) ($data['aria_label'] ?? ''));
@endphp
<div class="block-tabs {{ $settings['class'] ?? '' }}" data-tabs id="{{ $tabsContainerId }}" data-active-tab="{{ $firstTabId }}">
    <div class="site-tabs-list" role="tablist" @if($label !== '') aria-label="{{ $label }}" @endif>
        @foreach(($rawChildren ?? []) as $child)
            @if(in_array($child['type'] ?? '', ['tab-item', 'tab']))
                @php
                    $tabId = $child['data']['tabId'] ?? '';
                    $title = $tabTitles[$tabId] ?? $child['data']['title'] ?? __('translation.builder.default_tab');
                    $isFirst = $child['id'] === $firstTabId;
                @endphp
                <button
                    type="button"
                    role="tab"
                    id="tab-{{ $child['id'] }}"
                    data-tab-target="{{ $child['id'] }}"
                    data-tabs-container="{{ $tabsContainerId }}"
                    aria-selected="{{ $isFirst ? 'true' : 'false' }}"
                    aria-controls="tabpanel-{{ $child['id'] }}"
                    tabindex="{{ $isFirst ? '0' : '-1' }}"
                    class="site-tab"
                >
                    {{ $title }}
                </button>
            @endif
        @endforeach
    </div>
    {!! $children !!}
</div>

@once
<script @cspNonce>
// Once per page for all tab blocks: clicks and keys are handled by delegation.
// Guarded as well, since the preview renders each block on its own.
(function () {
    if (window.inverysTabs) return;
    window.inverysTabs = true;

    function tabsOf(container) {
        return Array.prototype.filter.call(container.querySelectorAll('button[role="tab"]'), function (tab) {
            return tab.getAttribute('data-tabs-container') === container.id;
        });
    }

    function tabsSwitch(btn) {
        var container = document.getElementById(btn.getAttribute('data-tabs-container'));
        var targetId = btn.getAttribute('data-tab-target');
        if (!container) return;

        tabsOf(container).forEach(function (tab) {
            var isActive = tab.getAttribute('data-tab-target') === targetId;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            var panel = document.getElementById('tabpanel-' + tab.getAttribute('data-tab-target'));
            if (panel) panel.classList.toggle('hidden', !isActive);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tabs]').forEach(function (container) {
            var active = tabsOf(container).filter(function (tab) {
                return tab.getAttribute('data-tab-target') === container.getAttribute('data-active-tab');
            })[0];
            if (active) tabsSwitch(active);
        });
    });

    document.addEventListener('click', function (e) {
        var tab = e.target.closest ? e.target.closest('button[role="tab"][data-tabs-container]') : null;
        if (tab) tabsSwitch(tab);
    });

    // Arrow keys move between the tabs, Home/End jump to the first/last one
    document.addEventListener('keydown', function (e) {
        if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(e.key) === -1) return;
        var tab = document.activeElement;
        if (!tab || tab.getAttribute('role') !== 'tab') return;
        var container = document.getElementById(tab.getAttribute('data-tabs-container') || '');
        if (!container) return;
        var tabs = tabsOf(container);
        var idx = tabs.indexOf(tab);
        if (idx === -1) return;
        if (e.key === 'ArrowRight') idx = (idx + 1) % tabs.length;
        else if (e.key === 'ArrowLeft') idx = (idx - 1 + tabs.length) % tabs.length;
        else if (e.key === 'Home') idx = 0;
        else idx = tabs.length - 1;
        tabs[idx].focus();
        tabsSwitch(tabs[idx]);
        e.preventDefault();
    });
})();
</script>
@endonce
