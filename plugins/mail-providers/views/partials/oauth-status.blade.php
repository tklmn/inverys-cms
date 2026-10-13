@php
    $hasTokens = $tokenManager->hasTokens($provider);
    $isExpired = $hasTokens && $tokenManager->isExpired($provider);
    $userEmail = $settings["mailproviders.{$provider}.user_email"] ?? $defaults["{$provider}.user_email"] ?? '';
@endphp

{{-- Connection status of an OAuth mailbox: state on the left, action on the right --}}
<div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-surface-sunken p-3">
    <div class="flex min-w-0 items-center gap-3">
        @if($hasTokens && ! $isExpired)
            <span class="ui-chip ui-tint-positive"><x-ui.icon name="circle-check" /></span>
        @elseif($isExpired)
            <span class="ui-chip ui-tint-caution"><x-ui.icon name="triangle-alert" /></span>
        @else
            <span class="ui-chip ui-tint-neutral"><x-ui.icon name="link" /></span>
        @endif

        <div class="min-w-0">
            <p class="text-sm font-medium text-fg">
                @if($hasTokens && ! $isExpired)
                    {{ __('mail-providers::mail-providers.oauth_status_connected') }}
                @elseif($isExpired)
                    {{ __('mail-providers::mail-providers.oauth_status_expired') }}
                @else
                    {{ __('mail-providers::mail-providers.oauth_status_disconnected') }}
                @endif
            </p>
            @if($userEmail)
                <p class="truncate text-xs text-fg-muted">{{ $userEmail }}</p>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @if($hasTokens)
            @if($isExpired)
                <x-ui.button variant="primary" size="sm" icon="refresh-cw" data-csp="oauth-authorize-btn-{{ $provider }}">
                    {{ __('mail-providers::mail-providers.oauth_reconnect') }}
                </x-ui.button>
            @endif
            <x-ui.button variant="danger" size="sm" icon="x" data-csp="oauth-disconnect-btn-{{ $provider }}">
                {{ __('mail-providers::mail-providers.oauth_disconnect') }}
            </x-ui.button>
        @else
            <x-ui.button variant="primary" size="sm" icon="link" data-csp="oauth-authorize-btn-{{ $provider }}">
                {{ __('mail-providers::mail-providers.oauth_connect') }}
            </x-ui.button>
        @endif
    </div>
</div>

@push('oauth-forms') @if($hasTokens)
        <form id="oauth-disconnect-{{ $provider }}" action="{{ route('backend.mail-providers.oauth.disconnect', ['provider' => $provider]) }}" method="POST" class="hidden">@csrf</form> @endif
    <form id="oauth-authorize-{{ $provider }}" action="{{ route('backend.mail-providers.oauth.authorize', ['provider' => $provider]) }}" method="POST" class="hidden">@csrf</form>
@endpush <script @cspNonce>
(function () {
    document.querySelectorAll('[data-csp="oauth-disconnect-btn-{{ $provider }}"]').forEach(function (el) { el.addEventListener('click', function () { document.getElementById('oauth-disconnect-{{ $provider }}').submit(); });
    });
    document.querySelectorAll('[data-csp="oauth-authorize-btn-{{ $provider }}"]').forEach(function (el) { el.addEventListener('click', function () { document.getElementById('oauth-authorize-{{ $provider }}').submit(); });
    });
})();
</script>
