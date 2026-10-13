@props(['label'])

{{-- A key value: label above the value. The wrapping div is valid inside
     a <dl> and turns the pair into one grid unit. --}}
<div {{ $attributes->merge(['class' => 'ui-fact']) }}>
    <dt>{{ $label }}</dt>
    <dd>{{ $slot }}</dd>
</div>
