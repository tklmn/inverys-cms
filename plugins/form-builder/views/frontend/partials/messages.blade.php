{{--
    Help text and error message of a field. Both ids are referenced together
    in the input's single aria-describedby (FormRenderer::fieldContext). The
    error is not a live region of its own: after a failed submit the error
    summary at the top of the form receives focus.

    @param array  $fb        field context from FormRenderer
    @param string $helpText
--}}
@if($helpText)
    <p id="{{ $fb['helpId'] }}" class="mt-1 text-xs text-gray-500">{{ $helpText }}</p>
@endif
@if($fb['error'] !== null)
    <p id="{{ $fb['errorId'] }}" class="mt-1 text-xs text-red-600"><span class="sr-only">{{ __('form-builder::forms.error_prefix') }}</span> {{ $fb['error'] }}</p>
@endif
