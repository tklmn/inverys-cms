@php
    $content = $field['content'] ?? '';
    $cssClass = $field['css_class'] ?? '';
@endphp

@if(!empty($content))
<div class="form-static-text {{ $cssClass }} text-sm text-gray-700 leading-relaxed">
    {!! nl2br(e($content)) !!}
</div>
@endif
