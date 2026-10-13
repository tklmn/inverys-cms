{{-- Success message after submitting — in the style of the alert block (site-alert) --}}
<div class="form-builder-success site-alert site-alert-success" role="status">
    <span class="site-alert-icon"><x-ui.icon name="circle-check" /></span>
    <div class="site-alert-body">
        <p class="site-alert-title">{{ __('form-builder::forms.submission_success') }}</p>
        @if(!empty($message))
            <p class="site-alert-text">{{ $message }}</p>
        @endif
    </div>
</div>
