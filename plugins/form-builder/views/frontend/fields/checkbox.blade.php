@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
@endphp

<div class="form-field {{ $cssClass }}">
    <div class="flex items-start gap-2">
        <input type="hidden" name="{{ $name }}" value="0" />
        <input type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1"
            class="mt-0.5 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error($name) border-red-500 @enderror"
            @if($helpText) aria-describedby="{{ $name }}-help" @endif
            @error($name) aria-describedby="{{ $name }}-error" aria-invalid="true" @enderror
            {{ old($name, $default) ? 'checked' : '' }}
            {{ $required ? 'required' : '' }} />
        <label for="{{ $name }}" class="text-sm text-gray-700">
            {{ $label }}
            @if($required)<span class="text-red-500">*</span>@endif
        </label>
    </div>
    @if($helpText)
        <p id="{{ $name }}-help" class="mt-1 text-xs text-gray-500">{{ $helpText }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
