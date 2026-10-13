{{--
    Single-line input shared by the text, email, tel, url, number, date and
    time fields.

    @param array  $field
    @param array  $fb         field context from FormRenderer
    @param string $inputType  HTML input type
--}}
@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $placeholder = in_array($inputType, ['date', 'time'], true)
        ? ''
        : ($field['placeholder'] ?? ($inputType === 'url' ? 'https://' : ''));
    $helpText = $field['help_text'] ?? '';
    $default = $field['default_value'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $value = $fb['useOld'] ? old($name, $default) : $default;
@endphp

<div class="form-field {{ $cssClass }}">
    @include('form-builder::partials.label', ['for' => $fb['id'], 'label' => $label, 'required' => $required])
    <input type="{{ $inputType }}" name="{{ $name }}" id="{{ $fb['id'] }}" value="{{ $value }}"
        @if($placeholder !== '') placeholder="{{ $placeholder }}" @endif
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
        @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
        @if($fb['error'] !== null) aria-invalid="true" @endif
        @if($fb['autocomplete']) autocomplete="{{ $fb['autocomplete'] }}" @endif
        {{ $required ? 'required' : '' }} />
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
