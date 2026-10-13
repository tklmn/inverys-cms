@props(['value' => null])

{{--
    Alias for third-party plugins. New code uses <x-ui.field label="…">, which renders label,
    help text and error message together.
--}}
<label {{ $attributes->merge(['class' => 'ui-label']) }}>{{ $value ?? $slot }}</label>
