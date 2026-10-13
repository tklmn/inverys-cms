<x-mail::message>
# {{ __('translation.email_change.confirm_heading') }}

{{ __('translation.email_change.confirm_line', ['email' => $newEmail, 'app' => config('app.name')]) }}

<x-mail::button :url="$url">
{{ __('translation.email_change.confirm_button') }}
</x-mail::button>

{{ __('translation.email_change.confirm_expires', ['hours' => $hours]) }}

{{ __('translation.email_change.confirm_ignore') }}

{{ config('app.name') }}
</x-mail::message>
