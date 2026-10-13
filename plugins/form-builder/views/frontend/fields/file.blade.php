@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $allowedTypes = !empty($field['allowed_file_types']) ? $field['allowed_file_types'] : \Plugins\FormBuilder\Services\FormValidator::SAFE_FILE_TYPES;
    $accept = implode(',', array_map(fn($t) => '.' . trim($t), explode(',', $allowedTypes)));
@endphp

<div class="form-field {{ $cssClass }}">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
        @if($required)<span class="text-red-500">*</span>@endif
    </label>
    <input type="file" name="{{ $name }}" id="{{ $name }}"
        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error($name) border-red-500 @enderror"
        @if($helpText) aria-describedby="{{ $name }}-help" @endif
        @error($name) aria-describedby="{{ $name }}-error" aria-invalid="true" @enderror
        {{ $required ? 'required' : '' }}
        accept="{{ $accept }}" />
    @if($helpText)
        <p id="{{ $name }}-help" class="mt-1 text-xs text-gray-500">{{ $helpText }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
