@php
    $fieldProvider = $field['provider'] ?? '';
    $provider = $fieldProvider ?: cms()->settings()->get('captcha.provider', 'math');
    $enabled = cms()->settings()->get('captcha.enabled', '1');
    $fieldId = $field['id'] ?? 'captcha-' . uniqid();
@endphp

@if($enabled)
    <div class="captcha-field mb-4">
        @include('captcha::providers.' . $provider, ['fieldId' => $fieldId])
    </div>
@endif
