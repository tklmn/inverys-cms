<x-mail::message>
# {{ __('translation.email_change.notice_heading') }}

{{ __('translation.email_change.notice_line', ['old' => $oldEmail, 'new' => $newEmail, 'app' => config('app.name')]) }}

{{ __('translation.email_change.notice_ok') }}

<x-mail::button :url="$url" color="error">
{{ __('translation.email_change.notice_button') }}
</x-mail::button>

{{ __('translation.email_change.notice_expires', ['days' => $days]) }}

{{ config('app.name') }}
</x-mail::message>
