@php
    $surface = \App\Support\BlockDesign::surface($settings['style'] ?? '');
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    $anchor = \App\Support\BlockDesign::anchor($settings['anchor'] ?? '');
    $bg = \App\Support\BlockDesign::background($settings, $data);
    // Landmark (section, aside, nav) with its name — a plain div takes no name
    $tag = \App\Support\BlockDesign::element($settings['element'] ?? '', 'section');
    $label = $tag !== 'div' ? trim((string) ($data['aria_label'] ?? '')) : '';
@endphp
<{{ $tag }} @if($anchor) id="{{ $anchor }}" @endif @if($label !== '') aria-label="{{ $label }}" @endif class="block-section {{ $settings['padding'] ?? 'py-12' }} {{ $surface }} {{ $bg['class'] }} {{ $align }} {{ $settings['background'] ?? '' }} {{ $settings['class'] ?? '' }}">
    @if($bg['src'])<x-responsive-image :src="$bg['src']" sizes="100vw" :priority="$imagePriority ?? false" alt="{{ $bg['alt'] }}" class="site-bg-media" />@endif
    {!! $children !!}
</{{ $tag }}>
