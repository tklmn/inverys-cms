@php
    // Build a lookup: tabId => title from the parent's data.tabs array
    $tabTitles = collect($data['tabs'] ?? [])->pluck('title', 'id')->all();
    $firstTabId = $rawChildren[0]['id'] ?? '';
    $tabsContainerId = 'tabs-' . uniqid();
@endphp
<div class="block-tabs {{ $settings['class'] ?? '' }}" data-tabs id="{{ $tabsContainerId }}" data-active-tab="{{ $firstTabId }}">
    <div class="site-tabs-list" role="tablist">
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

<script @cspNonce>
function tabsSwitch(btn) {
    var containerId = btn.getAttribute('data-tabs-container');
    var container = document.getElementById(containerId);
    var targetId = btn.getAttribute('data-tab-target');

    if (!container) return;

    // Update tab buttons
    container.querySelectorAll('button[data-tab-target]').forEach(function(tabBtn) {
        var isActive = tabBtn.getAttribute('data-tab-target') === targetId;
        tabBtn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tabBtn.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    // Update panels
    container.querySelectorAll('[role="tabpanel"]').forEach(function(panel) {
        var panelId = panel.id.replace('tabpanel-', '');
        panel.classList.toggle('hidden', panelId !== targetId);
    });
}

// On page load, hide non-active panels for each tabs container
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-tabs]').forEach(function(container) {
        var activeTab = container.getAttribute('data-active-tab');
        container.querySelectorAll('[role="tabpanel"]').forEach(function(panel) {
            var panelId = panel.id.replace('tabpanel-', '');
            panel.classList.toggle('hidden', panelId !== activeTab);
        });
        container.querySelectorAll('button[data-tab-target]').forEach(function(btn) {
            btn.addEventListener('click', function() { tabsSwitch(btn); });
        });
    });
});

// Arrow key navigation for tabs
document.addEventListener('keydown', function(e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    var tab = document.activeElement;
    if (!tab || tab.getAttribute('role') !== 'tab') return;
    var containerId = tab.getAttribute('data-tabs-container');
    if (!containerId) return;
    var container = document.getElementById(containerId);
    if (!container) return;
    var tabs = Array.prototype.slice.call(container.querySelectorAll('button[role="tab"]'));
    var idx = tabs.indexOf(tab);
    if (idx === -1) return;
    if (e.key === 'ArrowRight') idx = (idx + 1) % tabs.length;
    else idx = (idx - 1 + tabs.length) % tabs.length;
    tabs[idx].focus();
    tabsSwitch(tabs[idx]);
    e.preventDefault();
});
</script>
