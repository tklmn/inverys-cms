@php
    $fb ??= \Plugins\FormBuilder\Services\FormRenderer::fieldContext($field);
    $name = $field['name'] ?? '';
    $label = $field['label'] ?? $name;
    $required = !empty($field['required']);
    $helpText = $field['help_text'] ?? '';
    $cssClass = $field['css_class'] ?? '';
    $options = $field['options'] ?? [];
    $selected = $fb['useOld'] ? old($name, []) : [];
    if (!is_array($selected)) $selected = [$selected];
@endphp

{{--
    A group of options: fieldset + legend give every checkbox the question as
    context. "required" on a checkbox would force every single box, so the
    requirement is announced as text in the legend instead.
--}}
<div class="form-field {{ $cssClass }}">
    <fieldset id="{{ $fb['id'] }}">
        <legend class="block text-sm font-medium text-gray-700 mb-2">
            {{ $label }}
            @if($required)<span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">({{ __('form-builder::forms.required_hint') }})</span>@endif
        </legend>
        <div class="space-y-2">
            @foreach($options as $idx => $option)
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="{{ $name }}[]" id="{{ $fb['id'] }}-{{ $idx }}"
                        value="{{ $option['value'] ?? '' }}"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 {{ $fb['error'] !== null ? 'border-red-500' : '' }}"
                        @if($fb['describedBy']) aria-describedby="{{ $fb['describedBy'] }}" @endif
                        @if($fb['error'] !== null) aria-invalid="true" @endif
                        {{ in_array($option['value'] ?? '', $selected) ? 'checked' : '' }} />
                    <label for="{{ $fb['id'] }}-{{ $idx }}" class="text-sm text-gray-700">
                        {{ $option['label'] ?? $option['value'] ?? '' }}
                    </label>
                </div>
            @endforeach
        </div>
    </fieldset>
    @include('form-builder::partials.messages', ['fb' => $fb, 'helpText' => $helpText])
</div>
