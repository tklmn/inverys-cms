@props([
    'name',
    'value' => '#000000',
    'field' => null,
])

{{--
    Colour picker: swatch and hex value, linked in both directions.

    Used by the cookie settings and the theme customizer. The linking is
    handled by resources/js/backend/ui/color-field.js for all occurrences
    together.
--}}
<div {{ $attributes->merge(['class' => 'ui-color']) }} data-color-field>
    <input
        type="color"
        name="{{ $name }}"
        id="{{ $field ?? $name }}"
        value="{{ $value }}"
        class="ui-color-swatch"
        @if($field) data-field="{{ $field }}" @endif
        data-color-source
    >

    <input
        type="text"
        value="{{ $value }}"
        class="ui-color-text"
        spellcheck="false"
        pattern="#[0-9a-fA-F]{6}"
        aria-label="{{ __('translation.hex_value') }}"
        @if($field) data-field-text="{{ $field }}" @endif
        data-color-mirror
    >
</div>
