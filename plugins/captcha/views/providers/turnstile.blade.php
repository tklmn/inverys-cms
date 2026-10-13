@php $siteKey = cms()->settings()->get('captcha.turnstile_site_key', ''); @endphp
@if($siteKey)
<div class="captcha-turnstile-widget">
    <div class="cf-turnstile" data-sitekey="{{ $siteKey }}"></div>
</div>
<script @cspNonce>
(function () {
    if (window.__captchaLoaded_turnstile) return;
    window.__captchaLoaded_turnstile = true;

    var script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
})();
</script>
@endif
