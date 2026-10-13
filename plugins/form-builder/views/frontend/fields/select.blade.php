@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $placeholder = $field['placeholder'] ?? '';
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $options = $field['options'] ?? [];
    $value = $fb['useOld'] ? old($name, $default) : $default;
@endphp

<div class="form-field {{ $cssClass }}">
    @include('form-builder::partials.label', ['for' => $fb['id'], 'label' => $label, 'required' => $required])
    <select name="{{ $name }}" id="{{ $fb['id'] }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
        @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
        @if($fb['error'] !== null) aria-invalid="true" @endif
        @if($fb['autocomplete']) autocomplete="{{ $fb['autocomplete'] }}" @endif
        {{ $required ? 'required' : '' }}>
        {{-- Empty choice with readable text instead of a bare dash --}}
        <option value="">{{ $placeholder !== '' ? $placeholder : __('form-builder::forms.please_select') }}</option>
        @foreach($options as $option)
            <option value="{{ $option['value'] ?? '' }}" {{ $value == ($option['value'] ?? '') ? 'selected' : '' }}>
                {{ $option['label'] ?? $option['value'] ?? '' }}
            </option>
        @endforeach
    </select>
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
