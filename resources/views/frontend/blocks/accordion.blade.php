@php
    $accordionId = 'accordion-'.uniqid();
    $headingTag = \App\Support\BlockDesign::headingLevel($settings['title_level'] ?? null, '');
@endphp
<div class="block-accordion flex flex-col gap-3 {{ $settings['class'] ?? '' }}" data-accordion id="{{ $accordionId }}" @if(! empty($settings['allow_multiple'])) data-allow-multiple @endif>
    @if(! empty($data['items']))
        @foreach($data['items'] as $i => $item)
            <div class="site-accordion-item">
                @if($headingTag !== '')<{{ $headingTag }} class="site-accordion-heading">@endif
                <button
                    type="button"
                    id="{{ $accordionId }}-trigger-{{ $i }}"
                    data-panel="{{ $accordionId }}-panel-{{ $i }}"
                    aria-expanded="false"
                    aria-controls="{{ $accordionId }}-panel-{{ $i }}"
                    class="site-accordion-trigger"
                >
                    <span>{{ $item['title'] ?? '' }}</span>
                    <x-ui.icon name="chevron-down" />
                </button>
                @if($headingTag !== '')</{{ $headingTag }}>@endif
                <div id="{{ $accordionId }}-panel-{{ $i }}" role="region" aria-labelledby="{{ $accordionId }}-trigger-{{ $i }}" class="site-accordion-panel hidden">
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
// One item open, the others closed (unless the accordion allows several);
// the arrow rotates via CSS (aria-expanded)
function accordionToggle(btn) {
    var panel = document.getElementById(btn.getAttribute('data-panel'));
    var open = btn.getAttribute('aria-expanded') !== 'true';
    var container = btn.closest('[data-accordion]');

    if (container && open && !container.hasAttribute('data-allow-multiple')) {
        container.querySelectorAll('button[data-panel]').forEach(function (other) {
            // Only this accordion's own items, not those of a nested one
            if (other === btn || other.closest('[data-accordion]') !== container) return;
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
