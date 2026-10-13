@php
    $fullWidth = (bool) ($settings['full_width'] ?? false);
    $surface = \App\Support\BlockDesign::surface($settings['style'] ?? '');
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    $bg = \App\Support\BlockDesign::background($settings, $data);
    // A surface (or an image) in a narrow container becomes a rounded tile
    $boxed = ! $fullWidth && ($surface !== '' || $bg['src']);
    $anchor = \App\Support\BlockDesign::anchor($settings['anchor'] ?? '');
    $gap = \App\Support\BlockDesign::gap($settings['gap'] ?? '');
    $look = trim($surface.' '.$bg['class']);
    // Landmark (section, aside, nav) with its name — a plain div takes no name
    $tag = \App\Support\BlockDesign::element($settings['element'] ?? '', 'div');
    $label = $tag !== 'div' ? trim((string) ($data['aria_label'] ?? '')) : '';
@endphp
<{{ $tag }} @if($anchor) id="{{ $anchor }}" @endif @if($label !== '') aria-label="{{ $label }}" @endif class="block-container {{ $fullWidth ? '' : (($settings['max_width'] ?? 'max-w-7xl').' mx-auto px-4 sm:px-6 lg:px-8') }} {{ $settings['padding'] ?? 'py-8' }} {{ $boxed ? '' : $look }} {{ $align }} {{ $settings['background'] ?? '' }} {{ $settings['class'] ?? '' }}">
    @if($boxed)
        <div class="{{ $look }} site-surface-rounded {{ $gap }} px-6 py-10 sm:px-10">
            @if($bg['src'])<x-responsive-image :src="$bg['src']" :sizes="$fullWidth ? '100vw' : null" :priority="$imagePriority ?? false" alt="{{ $bg['alt'] }}" class="site-bg-media" />@endif
            {!! $children !!}
        </div>
    @else
        @if($bg['src'])<x-responsive-image :src="$bg['src']" :sizes="$fullWidth ? '100vw' : null" :priority="$imagePriority ?? false" alt="{{ $bg['alt'] }}" class="site-bg-media" />@endif
        <div class="{{ $gap }}">
            {!! $children !!}
        </div>
    @endif
</{{ $tag }}>
