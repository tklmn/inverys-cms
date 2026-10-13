@php
    $surface = \App\Support\BlockDesign::surface($settings['style'] ?? '');
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    $anchor = \App\Support\BlockDesign::anchor($settings['anchor'] ?? '');
    $bg = \App\Support\BlockDesign::background($settings);
@endphp
<section @if($anchor) id="{{ $anchor }}" @endif class="block-section {{ $settings['padding'] ?? 'py-12' }} {{ $surface }} {{ $bg['class'] }} {{ $align }} {{ $settings['background'] ?? '' }} {{ $settings['class'] ?? '' }}">
    @if($bg['src'])<img src="{{ $bg['src'] }}" alt="" class="site-bg-media" loading="lazy">@endif
    {!! $children !!}
</section>
