@php
    // The backend renders all head hooks too — tracking code belongs in the frontend only
    if (request()->is('backend', 'backend/*')) return;

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
