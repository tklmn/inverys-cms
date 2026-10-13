@php $siteKey = cms()->settings()->get('captcha.recaptcha_v3_site_key', ''); @endphp
@if($siteKey)
<div class="captcha-recaptcha-v3-widget">
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response-{{ $fieldId ?? 'default' }}" />
</div>
<script @cspNonce>
(function () {
    if (window.__captchaLoaded_recaptchaV3) return;
    window.__captchaLoaded_recaptchaV3 = true;

    var siteKey = @json($siteKey);
    var script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey;
    script.onload = function () {
        var form = document.querySelector('#g-recaptcha-response-{{ $fieldId ?? "default" }}');
        if (!form) return;
        var parentForm = form.closest('form');
        if (parentForm) {
            parentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute(siteKey, { action: 'submit' }).then(function (token) {
                        document.getElementById('g-recaptcha-response-{{ $fieldId ?? "default" }}').value = token;
                        parentForm.submit();
                    });
                });
            }, { once: true });
        }
    };
    document.head.appendChild(script);
})();
</script>
@endif
