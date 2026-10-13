@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $default = $field['default_value'] ?? '';
@endphp

<input type="hidden" name="{{ $name }}" value="{{ $fb['useOld'] ? old($name, $default) : $default }}" />
