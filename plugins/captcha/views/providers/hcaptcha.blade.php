@php $siteKey = cms()->settings()->get('captcha.hcaptcha_site_key', ''); @endphp
@if($siteKey)
<div class="captcha-hcaptcha-widget">
    <div class="h-captcha" data-sitekey="{{ $siteKey }}"></div>
</div>
<script @cspNonce>
(function () {
    if (window.__captchaLoaded_hcaptcha) return;
    window.__captchaLoaded_hcaptcha = true;

    var script = document.createElement('script');
    script.src = 'https://js.hcaptcha.com/1/api.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
})();
</script>
@endif
