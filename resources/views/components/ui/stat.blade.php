@props([
    'label' => null,
    'value' => null,
    'icon' => null,
    'href' => null,
    'delta' => null,
    'trend' => null,
    'tone' => 'accent',
])

@php
    $tag = $href ? 'a' : 'div';
    $classes = 'ui-card ui-stat ui-tone-'.$tone.($href ? ' ui-card-link' : '');
    $deltaClass = match ($trend) {
        'up' => 'ui-stat-delta ui-stat-delta-up',
        'down' => 'ui-stat-delta ui-stat-delta-down',
        default => 'ui-stat-delta',
    };
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
    {{-- Label on the left, coloured icon chip on the right; `tone` distinguishes
         the metrics without implying good/bad --}}
    <span class="ui-stat-head">
        <span class="ui-stat-label">{{ $label }}</span>
        @if($icon)<span class="ui-chip ui-tint-{{ $tone }}"><x-ui.nav-icon :name="$icon" /></span>@endif
    </span>
    <span class="ui-stat-value">{{ $value }}</span>
    @if($delta)
        <span class="{{ $deltaClass }}">
            @if($trend)<x-ui.icon :name="$trend === 'up' ? 'trending-up' : 'trending-down'" />@endif
            {{ $delta }}
        </span>
    @endif
</{{ $tag }}>
