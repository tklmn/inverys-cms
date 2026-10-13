@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $allowedTypes = !empty($field['allowed_file_types']) ? $field['allowed_file_types'] : \Plugins\FormBuilder\Services\FormValidator::SAFE_FILE_TYPES;
    $accept = implode(',', array_map(fn($t) => '.' . trim($t), explode(',', $allowedTypes)));
@endphp

<div class="form-field {{ $cssClass }}">
    @include('form-builder::partials.label', ['for' => $fb['id'], 'label' => $label, 'required' => $required])
    <input type="file" name="{{ $name }}" id="{{ $fb['id'] }}"
        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
        @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
        @if($fb['error'] !== null) aria-invalid="true" @endif
        {{ $required ? 'required' : '' }}
        accept="{{ $accept }}" />
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
