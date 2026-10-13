@php $accordionId = 'accordion-'.uniqid(); @endphp
<div class="block-accordion flex flex-col gap-3 {{ $settings['class'] ?? '' }}" data-accordion id="{{ $accordionId }}">
    @if(! empty($data['items']))
        @foreach($data['items'] as $i => $item)
            <div class="site-accordion-item">
                <button
                    type="button"
                    data-panel="{{ $accordionId }}-panel-{{ $i }}"
                    aria-expanded="false"
                    aria-controls="{{ $accordionId }}-panel-{{ $i }}"
                    class="site-accordion-trigger"
                >
                    <span>{{ $item['title'] ?? '' }}</span>
                    <x-ui.icon name="chevron-down" />
                </button>
                <div id="{{ $accordionId }}-panel-{{ $i }}" role="region" class="site-accordion-panel hidden">
                    {!! \App\Helpers\HtmlHelper::clean($item['content'] ?? '') !!}
                </div>
            </div>
        @endforeach
    @else
        {!! $children !!}
    @endif
</div>

@once
<script @cspNonce>
// One item open, the others closed; the arrow rotates via CSS (aria-expanded)
function accordionToggle(btn) {
    var panel = document.getElementById(btn.getAttribute('data-panel'));
    var open = btn.getAttribute('aria-expanded') !== 'true';
    var container = btn.closest('[data-accordion]');

    if (container) {
        container.querySelectorAll('button[data-panel]').forEach(function (other) {
            if (other === btn) return;
            other.setAttribute('aria-expanded', 'false');
            var otherPanel = document.getElementById(other.getAttribute('data-panel'));
            if (otherPanel) otherPanel.classList.add('hidden');
        });
    }

    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (panel) panel.classList.toggle('hidden', !open);
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-accordion] button[data-panel]').forEach(function (btn) {
        if (btn.dataset.cspBound) return;
        btn.dataset.cspBound = '1';
        btn.addEventListener('click', function () { accordionToggle(btn); });
    });
});
</script>
@endonce
