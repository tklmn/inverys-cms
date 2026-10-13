@php
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $options = $field['options'] ?? [];
@endphp

<div class="form-field {{ $cssClass }}">
    <span class="block text-sm font-medium text-gray-700 mb-2">
        {{ $label }}
        @if($required)<span class="text-red-500">*</span>@endif
    </span>
    <div class="space-y-2">
        @foreach($options as $idx => $option)
            <div class="flex items-center gap-2">
                <input type="radio" name="{{ $name }}" id="{{ $name }}_{{ $idx }}"
                    value="{{ $option['value'] ?? '' }}"
                    class="border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 @error($name) border-red-500 @enderror"
                    @if($helpText) aria-describedby="{{ $name }}-help" @endif
                    @error($name) aria-describedby="{{ $name }}-error" aria-invalid="true" @enderror
                    {{ old($name, $default) == ($option['value'] ?? '') ? 'checked' : '' }}
                    {{ $required ? 'required' : '' }} />
                <label for="{{ $name }}_{{ $idx }}" class="text-sm text-gray-700">
                    {{ $option['label'] ?? $option['value'] ?? '' }}
                </label>
            </div>
        @endforeach
    </div>
    @if($helpText)
        <p id="{{ $name }}-help" class="mt-1 text-xs text-gray-500">{{ $helpText }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
    @enderror
</div>
