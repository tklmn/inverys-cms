<x-app-layout>
    <x-slot name="header">{{ __('activity-log::activity.settings_title') }}</x-slot>

    <x-ui.page :title="__('activity-log::activity.settings_title')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.activity-log.index')">
                {{ __('activity-log::activity.title') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="activity-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="activity-settings-form" action="{{ route('backend.activity-log.settings.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('activity-log::activity.retention')" icon="history">
                <div class="ui-grid">
                    <x-ui.field
                        name="retention_days"
                        :label="__('activity-log::activity.retention_days')"
                        :help="__('activity-log::activity.retention_days_help')"
                    >
                        <x-ui.input id="retention_days" name="retention_days" type="number" min="0" max="3650" :value="old('retention_days', $retention_days)" required />
                    </x-ui.field>

                    <x-ui.field
                        name="max_entries"
                        :label="__('activity-log::activity.max_entries')"
                        :help="__('activity-log::activity.max_entries_help')"
                    >
                        <x-ui.input id="max_entries" name="max_entries" type="number" min="0" max="1000000" :value="old('max_entries', $max_entries)" required />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
