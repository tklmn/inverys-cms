@props([
    'name',
    'strokeWidth' => 1.75,
])

@php
    $svg = \App\Support\Icon::svg($name, (string) ($attributes->get('class') ?? ''), (float) $strokeWidth);
@endphp

@if($svg)
    {!! $svg !!}
@else
    {{-- Unknown icon name: placeholder instead of a layout shift --}}
    <span {{ $attributes->merge(['class' => 'ui-icon']) }} style="width:1em;height:1em" aria-hidden="true"></span>
@endif
