@props([
    'label' => null,
    'name' => null,
    'help' => null,
    'optional' => false,
])

@php
    $error = $name ? $errors->first($name) : null;
    $id = $attributes->get('id') ?? $name;
@endphp

<div {{ $attributes->except('id')->merge(['class' => 'ui-field']) }} @if($error) data-invalid @endif>
    @if($label)
        <label class="ui-label" @if($id) for="{{ $id }}" @endif>
            {{ $label }}
            @if($optional)<span class="ui-label-optional">{{ __('translation.optional') }}</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($help && ! $error)<p class="ui-help">{{ $help }}</p>@endif

    @if($error)
        <p class="ui-error"><x-ui.icon name="circle-alert" />{{ $error }}</p>
    @endif
</div>
