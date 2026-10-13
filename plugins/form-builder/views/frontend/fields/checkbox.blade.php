@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $checked = $fb['useOld'] ? old($name, $default) : $default;
@endphp

<div class="form-field {{ $cssClass }}">
    <div class="flex items-start gap-2">
        <input type="hidden" name="{{ $name }}" value="0" />
        <input type="checkbox" name="{{ $name }}" id="{{ $fb['id'] }}" value="1"
            class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
            @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
            @if($fb['error'] !== null) aria-invalid="true" @endif
            {{ $checked ? 'checked' : '' }}
            {{ $required ? 'required' : '' }} />
        <label for="{{ $fb['id'] }}" class="text-sm text-gray-700">
            {{ $label }}
            @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
        </label>
    </div>
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
