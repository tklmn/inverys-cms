<x-app-layout>
    <x-slot name="header">{{ __('translation.notifications.title') }}</x-slot>

    @php
        $typeIcons = [
            'http_error' => ['triangle-alert', 'critical'],
            'laravel_update' => ['circle-arrow-up', 'positive'],
            'cms_update' => ['circle-arrow-up', 'positive'],
            'failed_login' => ['shield-alert', 'caution'],
            'log_change' => ['scroll-text', 'accent'],
        ];
        $unread = app(\App\Services\NotificationService::class)->unreadCountFor(auth()->user());
    @endphp

    <x-ui.page :title="__('translation.notifications.title')" :lead="trans_choice('translation.notifications.unread_count', $unread, ['count' => $unread])">
        <x-slot:actions>
            @permission('manage-notification-settings')
                <x-ui.button size="md" icon="settings" :href="route('backend.notifications.settings')">
                    {{ __('translation.navigation.settings') }}
                </x-ui.button>
            @endpermission

            {{-- Disabled instead of hidden when nothing is unread --}}
            <x-ui.button
                size="md"
                icon="check-check"
                data-notifications-read-all="{{ route('backend.notifications.mark-all-read') }}"
                :disabled="$unread === 0"
                :disabled-reason="__('translation.notifications.all_read')"
            >
                {{ __('translation.notifications.mark_all_read') }}
            </x-ui.button>

            @permission('delete-notifications')
                <form id="delete-form-notifications-all" action="{{ route('backend.notifications.destroy-all') }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <x-ui.button
                        variant="danger"
                        size="md"
                        icon="trash-2"
                        :disabled="$notifications->isEmpty()"
                        :disabled-reason="__('translation.notifications.no_notifications')"
                        data-delete-modal="{{ json_encode(['formId' => 'delete-form-notifications-all', 'title' => __('translation.notifications.confirm_delete_all_title'), 'subtitle' => __('translation.notifications.confirm_delete_all')]) }}"
                    >{{ __('translation.notifications.delete_all') }}</x-ui.button>
                </form>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            @if($notifications->isEmpty())
                <x-ui.empty icon="bell" :title="__('translation.notifications.no_notifications')">
                    {{ __('translation.notifications.no_notifications_hint') }}
                </x-ui.empty>
            @else
                <x-ui.list>
                    @foreach($grouped as $date => $items)
                        <x-ui.list-group>{{ format_date_long($date) }}</x-ui.list-group>

                        @foreach($items as $notification)
                            @php
                                [$icon, $tone] = $typeIcons[$notification->type] ?? ['bell', 'neutral'];
                                $typeKey = 'translation.notifications.type_'.$notification->type;
                                // HTTP errors store the English status text — translate it here
                                $body = $notification->type === 'http_error' ? __((string) $notification->body) : $notification->body;
                            @endphp

                            <x-ui.list-row :title="$notification->title" :icon="$icon" :selected="! $notification->is_read">
                                <x-slot:meta>
                                    @unless($notification->is_read)
                                        <span class="font-medium text-accent">{{ __('translation.notifications.unread') }}</span>
                                    @endunless
                                    @if($body)<span>{{ $body }}</span>@endif
                                    <span title="{{ format_datetime($notification->created_at) }}">{{ $notification->created_at->locale(app()->getLocale())->isoFormat('LT') }}</span>
                                </x-slot:meta>

                                <x-slot:cols>
                                    <x-ui.list-col priority="low">
                                        <x-ui.badge :tone="$tone">{{ __($typeKey) !== $typeKey ? __($typeKey) : Str::headline($notification->type) }}</x-ui.badge>
                                    </x-ui.list-col>
                                </x-slot:cols>

                                <x-slot:actions>
                                    <x-ui.button
                                        variant="ghost"
                                        icon="check"
                                        :data-notification-read="route('backend.notifications.mark-read', $notification->id)"
                                        :label="__('translation.notifications.mark_read')"
                                        :disabled="$notification->is_read"
                                        :disabled-reason="__('translation.notifications.already_read')"
                                    />

                                    @permission('delete-notifications')
                                        <form id="delete-form-notification-{{ $notification->id }}" action="{{ route('backend.notifications.destroy', $notification) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button
                                                variant="danger"
                                                icon="trash-2"
                                                :label="__('translation.delete')"
                                                data-delete-modal="{{ json_encode(['formId' => 'delete-form-notification-'.$notification->id, 'title' => $notification->title]) }}"
                                            />
                                        </form>
                                    @endpermission
                                </x-slot:actions>
                            </x-ui.list-row>
                        @endforeach
                    @endforeach
                </x-ui.list>

                @if($notifications->hasPages())
                    <x-slot:foot>{{ $notifications->links() }}</x-slot:foot>
                @endif
            @endif
        </x-ui.card>
    </x-ui.page>

    {{-- Mark as read: POST to the URL in the attribute, then reload --}}
    <script @cspNonce>
    (function () {
        var headers = { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' };

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-notification-read], [data-notifications-read-all]');
            if (!button || button.disabled) return;
            var url = button.getAttribute('data-notification-read') || button.getAttribute('data-notifications-read-all');
            button.disabled = true;
            fetch(url, { method: 'POST', headers: headers }).then(function () { window.location.reload(); });
        });
    }());
    </script>
</x-app-layout>
