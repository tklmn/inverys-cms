@props([
    'title' => null,
    'href' => null,
    'icon' => 'file',
    'image' => null,
    'alt' => '',
])

{{--
    Tile for image overviews. Preview, label, and below them a separate
    bar for the actions — deliberately not placed over the image.
--}}
@php
    // SVG graphics and logos often have transparency and no margin — show in full, on a checkerboard
    $contain = $image && str_ends_with(strtolower((string) parse_url($image, PHP_URL_PATH)), '.svg');
@endphp
<div {{ $attributes->merge(['class' => 'ui-tile']) }}>
    @if($href)
        <a href="{{ $href }}" class="ui-tile-preview">
            @if($image)
                <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy" @if($contain) class="is-contain" @endif>
            @else
                <x-ui.icon :name="$icon" />
            @endif
        </a>
    @else
        <span class="ui-tile-preview">
            @if($image)
                <img src="{{ $image }}" alt="{{ $alt }}" loading="lazy" @if($contain) class="is-contain" @endif>
            @else
                <x-ui.icon :name="$icon" />
            @endif
        </span>
    @endif

    <div class="ui-tile-body">
        @if($title)<span class="ui-tile-title" title="{{ $title }}">{{ $title }}</span>@endif
        @isset($meta)<span class="ui-tile-meta">{{ $meta }}</span>@endisset
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="ui-tile-actions">{{ $actions }}</div>
    @endisset
</div>
