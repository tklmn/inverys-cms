@props(['messages'])

{{--
    Alias for third-party plugins. New code uses <x-ui.field name="…">, which shows the field's
    errors itself.
--}}
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'flex flex-col gap-0.5']) }}>
        @foreach ((array) $messages as $message)
            <li class="ui-error"><x-ui.icon name="circle-alert" />{{ $message }}</li>
        @endforeach
    </ul>
@endif
