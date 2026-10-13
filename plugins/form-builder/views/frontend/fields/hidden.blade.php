@php
    $name = $field['name'] ?? '';
    $default = $field['default_value'] ?? '';
@endphp

<input type="hidden" name="{{ $name }}" value="{{ old($name, $default) }}" />
