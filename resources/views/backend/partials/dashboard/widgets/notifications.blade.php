@php
    $iconMap = [
        'http_error' => 'triangle-alert',
        'failed_login' => 'shield-alert',
        'laravel_update' => 'circle-arrow-up',
        'cms_update' => 'circle-arrow-up',
        'log_change' => 'scroll-text',
    ];
@endphp

<div class="ui-card-head">
    <h3 class="ui-card-title flex items-center gap-2"><span class="ui-chip ui-chip-sm ui-tint-caution"><x-ui.icon name="bell" /></span>
        {{ __('translation.dashboard.notifications_widget') }}
        @if($unreadNotificationCount > 0)
            <x-ui.badge tone="critical">{{ $unreadNotificationCount }}</x-ui.badge>
        @endif
    </h3>
    <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.notifications.index')">
        {{ __('translation.dashboard.view_all') }}
    </x-ui.button>
</div>

<x-ui.list>
    @forelse($notifications as $n)
        <x-ui.list-row :title="$n->title" :icon="$iconMap[$n->type] ?? 'bell'">
            <x-slot:meta>
                <span>{{ $n->created_at->diffForHumans() }}</span>
            </x-slot:meta>
        </x-ui.list-row>
    @empty
        <x-ui.empty icon="bell">{{ __('translation.dashboard.no_notifications') }}</x-ui.empty>
    @endforelse
</x-ui.list>
