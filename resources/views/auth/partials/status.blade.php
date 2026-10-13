{{-- Messages after an action (link sent, password reset …) --}}
@if (session('status') === 'verification-link-sent')
    <x-ui.note tone="positive" class="mb-6">{{ __('translation.auth.verification_link_sent_reg') }}</x-ui.note>
@elseif (session('status'))
    <x-ui.note tone="positive" class="mb-6">{{ session('status') }}</x-ui.note>
@endif

@if (session('warning'))
    <x-ui.note tone="caution" class="mb-6">{{ session('warning') }}</x-ui.note>
@endif
