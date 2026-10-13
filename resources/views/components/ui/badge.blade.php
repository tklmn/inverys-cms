@props([
    'tone' => 'neutral',
    'dot' => true,
])

@php
    $classes = collect([
        'ui-badge',
        $tone !== 'neutral' ? 'ui-badge-'.$tone : null,
        $dot ? null : 'ui-badge-plain',
    ])->filter()->implode(' ');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
