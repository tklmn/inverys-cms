<div class="site-accordion-item {{ $settings['class'] ?? '' }}">
    <button
        type="button"
        data-panel="accordion-panel-{{ $id }}"
        aria-expanded="false"
        aria-controls="accordion-panel-{{ $id }}"
        class="site-accordion-trigger"
    >
        <span @pbField('title')>{{ $data['title'] ?? '' }}</span>
        <x-ui.icon name="chevron-down" />
    </button>
    <div id="accordion-panel-{{ $id }}" role="region" class="site-accordion-panel hidden">
        @if(! empty($children))
            {!! $children !!}
        @endif
    </div>
</div>
