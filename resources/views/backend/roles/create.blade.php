<x-app-layout>
    <x-slot name="header">{{ __('translation.roles.create') }}</x-slot>

    <x-ui.page :title="__('translation.roles.create')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.roles.index')">{{ __('translation.navigation.roles') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="role-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="role-form" method="POST" action="{{ route('backend.roles.store') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.card :title="__('translation.roles.general')" icon="shield" tone="accent">
                <x-ui.field name="name" :label="__('translation.roles.name')" :help="__('translation.roles.name_help')">
                    <x-ui.input id="name" name="name" class="font-mono" :value="old('name')" required autofocus />
                </x-ui.field>
            </x-ui.card>

            <x-ui.card :title="__('translation.roles.permissions')" icon="key-round" tone="caution" flush>
                @include('backend.roles.partials.permission-picker', [
                    'permissions' => $permissions,
                    'selected' => old('permissions', []),
                ])
            </x-ui.card>
        </form>
    </x-ui.page>
</x-app-layout>
