@php $siteKey = cms()->settings()->get('captcha.captchafox_site_key', ''); @endphp
@if($siteKey)
<div class="captcha-captchafox-widget">
    <div data-captcha-widget data-sitekey="{{ $siteKey }}"></div>
</div>
<script @cspNonce>
(function () {
    if (window.__captchaLoaded_captchafox) return;
    window.__captchaLoaded_captchafox = true;

    var script = document.createElement('script');
    script.src = 'https://cdn.captchafox.com/api.js';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
})();
</script>
@endif
