@php($captchaId = 'captcha-math-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($fieldId ?? 'default')))
{{--
    Accessible: the answer input is labelled, the task is linked as its
    description and announced again (aria-live) when a new one is loaded.
--}}
<div class="captcha-math-widget" id="{{ $captchaId }}">
    <div class="mb-2">
        <label for="{{ $captchaId }}-answer" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('captcha::captcha.solve_challenge') }}</label>
    </div>
    <div class="flex items-center gap-3">
        <span id="{{ $captchaId }}-challenge" class="captcha-challenge text-lg font-mono font-bold text-gray-800 dark:text-white" aria-live="polite">{{ __('captcha::captcha.loading') }}</span>
        <button type="button" class="captcha-refresh text-gray-400 hover:text-blue-500 transition" title="{{ __('captcha::captcha.new_challenge') }}" aria-label="{{ __('captcha::captcha.new_challenge') }}">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
        </button>
    </div>
    <input type="hidden" name="_captcha_token" class="captcha-token" value="" />
    <input type="number" name="_captcha_answer" id="{{ $captchaId }}-answer" aria-describedby="{{ $captchaId }}-challenge" autocomplete="off" class="captcha-answer mt-2 block w-full max-w-[200px] border-gray-300 dark:border-gray-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm" placeholder="{{ __('captcha::captcha.your_answer') }}" required />
</div>

<script @cspNonce>
(function () {
    if (window.__captchaMathInit) return;
    window.__captchaMathInit = true;

    function loadChallenge(widget) {
        var challengeUrl = '{{ route("captcha.challenge") }}';
        fetch(challengeUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                widget.querySelector('.captcha-challenge').textContent = data.challenge;
                widget.querySelector('.captcha-token').value = data.token;
                widget.querySelector('.captcha-answer').value = '';
            });
    }

    document.querySelectorAll('.captcha-math-widget').forEach(function (widget) {
        loadChallenge(widget);
        widget.querySelector('.captcha-refresh').addEventListener('click', function () {
            loadChallenge(widget);
        });
    });
})();
</script>
