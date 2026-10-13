@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? 'https://';
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
@endphp

<div class="form-field {{ $cssClass }}">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $label }}
        @if($required)<span class="text-red-500">*</span>@endif
    </label>
    <input type="url" name="{{ $name }}" id="{{ $name }}" value="{{ old($name, $default) }}"
        placeholder="{{ $placeholder }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm @error($name) border-red-500 @enderror"
        @if($helpText) aria-describedby="{{ $name }}-help" @endif
        @error($name) aria-describedby="{{ $name }}-error" aria-invalid="true" @enderror
        {{ $required ? 'required' : '' }} />
    @if($helpText)
        <p id="{{ $name }}-help" class="mt-1 text-xs text-gray-500">{{ $helpText }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
