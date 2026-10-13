<x-mail::message>
# {{ __('auth.two_factor.email_greeting') }}

{{ __('auth.two_factor.email_line1') }}

<x-mail::panel>
<strong style="font-size: 24px; letter-spacing: 4px;">{{ $code }}</strong>
</x-mail::panel>

{{ __('auth.two_factor.email_line2', ['minutes' => $lifetime]) }}

{{ __('auth.two_factor.email_warning') }}

{{ __('auth.two_factor.email_regards') }}<br>
{{ config('app.name') }}
</x-mail::message>
