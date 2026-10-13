@props([
    'label' => null,
    'priority' => 'normal',
])

<div {{ $attributes->merge(['class' => 'ui-list-col']) }} data-priority="{{ $priority }}">
    @if($label)<span class="ui-list-col-label">{{ $label }}</span>@endif
    <span>{{ $slot }}</span>
</div>
