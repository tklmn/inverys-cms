@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $options = $field['options'] ?? [];
    $defaults = is_string($default) ? explode(',', $default) : [];
    $selected = $fb['useOld'] ? old($name, $defaults) : $defaults;
    if (!is_array($selected)) $selected = [$selected];
@endphp

<div class="form-field {{ $cssClass }}">
    @include('form-builder::partials.label', ['for' => $fb['id'], 'label' => $label, 'required' => $required])
    <select name="{{ $name }}[]" id="{{ $fb['id'] }}" multiple
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
        @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
        @if($fb['error'] !== null) aria-invalid="true" @endif
        {{ $required ? 'required' : '' }}>
        @foreach($options as $option)
            <option value="{{ $option['value'] ?? '' }}" {{ in_array($option['value'] ?? '', $selected) ? 'selected' : '' }}>
                {{ $option['label'] ?? $option['value'] ?? '' }}
            </option>
        @endforeach
    </select>
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
