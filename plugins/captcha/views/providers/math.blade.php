<div class="captcha-math-widget" id="captcha-math-{{ $fieldId ?? 'default' }}">
    <div class="mb-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('captcha::captcha.solve_challenge') }}</span>
    </div>
    <div class="flex items-center gap-3">
        <span class="captcha-challenge text-lg font-mono font-bold text-gray-800 dark:text-white">{{ __('captcha::captcha.loading') }}</span>
        <button type="button" class="captcha-refresh text-gray-400 hover:text-blue-500 transition" title="{{ __('captcha::captcha.new_challenge') }}">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
    <input type="hidden" name="_captcha_token" class="captcha-token" value="" />
    <input type="number" name="_captcha_answer" class="captcha-answer mt-2 block w-full max-w-[200px] border-gray-300 dark:border-gray-700 dark:bg-slate-800 dark:text-white rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-sm" placeholder="{{ __('captcha::captcha.your_answer') }}" required />
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
