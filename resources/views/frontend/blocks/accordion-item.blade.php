@php
    // Heading level comes from the accordion: the trigger sits inside <hN> (none = as before)
    $headingTag = \App\Support\BlockDesign::headingLevel($parentBlock['settings']['title_level'] ?? null, '');
    $open = ! empty($settings['open']);
@endphp
<div class="site-accordion-item {{ $settings['class'] ?? '' }}">
    @if($headingTag !== '')<{{ $headingTag }} class="site-accordion-heading">@endif
    <button
        type="button"
        id="accordion-trigger-{{ $id }}"
        data-panel="accordion-panel-{{ $id }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-controls="accordion-panel-{{ $id }}"
        class="site-accordion-trigger"
    >
        <span @pbField('title')>{{ $data['title'] ?? '' }}</span>
        <x-ui.icon name="chevron-down" />
    </button>
    @if($headingTag !== '')</{{ $headingTag }}>@endif
    <div id="accordion-panel-{{ $id }}" role="region" aria-labelledby="accordion-trigger-{{ $id }}" class="site-accordion-panel {{ $open ? '' : 'hidden' }}">
        @if(! empty($children))
            {!! $children !!}
        @endif
    </div>
</div>
