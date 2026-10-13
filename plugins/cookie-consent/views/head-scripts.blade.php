@php
    // Tracking code belongs on the website only: not in the backend and not on
    // the account pages (login, password reset, email change …), whose
    // addresses carry tokens that analytics would record with the page URL
    if (\App\Support\AccountPages::withoutPluginHooks(request())) return;

    $consentRaw = request()->cookie('cc_consent');
    $consent = $consentRaw ? json_decode($consentRaw, true) : null;
    if (!is_array($consent) || !isset($consent['ts'])) return;

    $services = json_decode(cms()->settings()->get('cc.services', '[]'), true) ?: [];
@endphp

@foreach($services as $service)
    @if(($service['position'] ?? '') === 'head' && !empty($consent[$service['category'] ?? '']))
{{-- {{ $service['name'] }} --}}
{!! \Plugins\CookieConsent\ServiceScript::render($service) !!}
    @endif
@endforeach
