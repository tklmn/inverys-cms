@php
    $fullWidth = (bool) ($settings['full_width'] ?? false);
    $surface = \App\Support\BlockDesign::surface($settings['style'] ?? '');
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    $bg = \App\Support\BlockDesign::background($settings);
    // A surface (or an image) in a narrow container becomes a rounded tile
    $boxed = ! $fullWidth && ($surface !== '' || $bg['src']);
    $anchor = \App\Support\BlockDesign::anchor($settings['anchor'] ?? '');
    $gap = \App\Support\BlockDesign::gap($settings['gap'] ?? '');
    $look = trim($surface.' '.$bg['class']);
@endphp
<div @if($anchor) id="{{ $anchor }}" @endif class="block-container {{ $fullWidth ? '' : (($settings['max_width'] ?? 'max-w-7xl').' mx-auto px-4 sm:px-6 lg:px-8') }} {{ $settings['padding'] ?? 'py-8' }} {{ $boxed ? '' : $look }} {{ $align }} {{ $settings['background'] ?? '' }} {{ $settings['class'] ?? '' }}">
    @if($boxed)
        <div class="{{ $look }} site-surface-rounded {{ $gap }} px-6 py-10 sm:px-10">
            @if($bg['src'])<img src="{{ $bg['src'] }}" alt="" class="site-bg-media" loading="lazy">@endif
            {!! $children !!}
        </div>
    @else
        @if($bg['src'])<img src="{{ $bg['src'] }}" alt="" class="site-bg-media" loading="lazy">@endif
        <div class="{{ $gap }}">
            {!! $children !!}
        </div>
    @endif
</div>
