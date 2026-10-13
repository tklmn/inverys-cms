@php
    $formId = $data['form_id'] ?? null;
    $formModel = null;
    if ($formId) {
        $formModel = \Plugins\FormBuilder\Models\Form::where('id', $formId)->where('is_active', true)->first();
    }
@endphp

@if($formModel)
    {!! \Plugins\FormBuilder\Services\FormRenderer::render($formModel) !!}
@endif
