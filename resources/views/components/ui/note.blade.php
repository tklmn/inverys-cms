@props([
    'tone' => 'neutral',
    'title' => null,
    'icon' => null,
])

@php
    $icons = [
        'neutral' => 'info',
        'accent' => 'info',
        'positive' => 'circle-check',
        'caution' => 'triangle-alert',
        'critical' => 'circle-alert',
    ];

    $classes = 'ui-note'.($tone !== 'neutral' ? ' ui-note-'.$tone : '');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} role="{{ $tone === 'critical' ? 'alert' : 'status' }}">
    <span class="ui-note-icon"><x-ui.icon :name="$icon ?? ($icons[$tone] ?? 'info')" /></span>
    <div class="ui-note-body">
        @if($title)<p class="ui-note-title">{{ $title }}</p>@endif
        <div class="{{ $title ? 'ui-note-text' : '' }}">{{ $slot }}</div>
    </div>
</div>
