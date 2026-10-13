@php $siteKey = cms()->settings()->get('captcha.friendly_captcha_site_key', ''); @endphp
@if($siteKey)
<div class="captcha-friendly-captcha-widget">
    <div class="frc-captcha" data-sitekey="{{ $siteKey }}"></div>
</div>
<script @cspNonce>
(function () {
    if (window.__captchaLoaded_friendlyCaptcha) return;
    window.__captchaLoaded_friendlyCaptcha = true;

    var module = document.createElement('script');
    module.type = 'module';
    module.src = 'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.14/widget.module.min.js';
    document.head.appendChild(module);

    var nomodule = document.createElement('script');
    nomodule.noModule = true;
    nomodule.src = 'https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.14/widget.min.js';
    document.head.appendChild(nomodule);
})();
</script>
@endif
