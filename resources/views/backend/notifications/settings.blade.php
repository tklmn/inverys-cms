<x-app-layout>
    <x-slot name="header">{{ __('translation.notifications.settings_title') }}</x-slot>

    @php
        $types = [
            'log_change' => ['translation.notifications.type_log_change', 'translation.notifications.type_log_change_hint'],
            'laravel_update' => ['translation.notifications.type_laravel_update', 'translation.notifications.type_laravel_update_hint'],
            'cms_update' => ['translation.notifications.type_cms_update', 'translation.notifications.type_cms_update_hint'],
            'http_error' => ['translation.notifications.type_http_error', 'translation.notifications.type_http_error_hint'],
            'failed_login' => ['translation.notifications.type_failed_login', 'translation.notifications.type_failed_login_hint'],
        ];
    @endphp

    <x-ui.page :title="__('translation.notifications.settings_title')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.notifications.index')">{{ __('translation.notifications.title') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="notification-settings">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="notification-settings" method="POST" action="{{ route('backend.notifications.settings.save') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.card :title="__('translation.notifications.user_preferences')" icon="bell" tone="accent">
                <div class="flex flex-col">
                    {{-- Without the required permission: visible but disabled (system messages) --}}
                    @foreach($types as $key => [$label, $hint])
                        @php($allowed = auth()->user()->hasPermission(\App\Services\NotificationService::TYPE_PERMISSIONS[$key] ?? 'show-system'))
                        <x-ui.switch-row
                            :name="$key"
                            :label="__($label)"
                            :hint="$allowed ? __($hint) : __('translation.notifications.type_not_allowed')"
                            :checked="$allowed && $userSettings[$key] === 'on'"
                            :disabled="! $allowed"
                        />
                    @endforeach
                </div>
            </x-ui.card>

            @permission('manage-notification-settings')
                {{-- Applies to everyone: which status codes produce a notification at all --}}
                <x-ui.card :title="__('translation.notifications.http_status_codes_label')" icon="triangle-alert" tone="critical">
                    <p class="ui-help mb-2">{{ __('translation.notifications.http_status_codes_hint') }}</p>

                    <div class="ui-choices">
                        @foreach($allStatusCodes as $code => $label)
                            <x-ui.switch-row
                                name="http_codes[]"
                                multiple
                                :bleed="false"
                                :label="(string) $code"
                                :hint="__($label)"
                                :on-value="$code"
                                :checked="in_array($code, $enabledCodes)"
                                :input-id="'http_code_'.$code"
                                title-class="font-mono"
                            />
                        @endforeach
                    </div>
                </x-ui.card>
            @endpermission

        </form>
    </x-ui.page>
</x-app-layout>
