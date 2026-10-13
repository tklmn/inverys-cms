<x-app-layout>
    <x-slot name="header">{{ __('translation.roles.edit') }}</x-slot>

    <x-ui.page :title="__('translation.roles.edit').': '.$role->label">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.roles.index')">{{ __('translation.navigation.roles') }}</x-ui.button>
            <x-ui.button size="md" icon="eye" :href="route('backend.roles.show', $role)">{{ __('translation.view') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="role-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="role-form" method="POST" action="{{ route('backend.roles.update', $role->id) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('translation.roles.general')" icon="shield" tone="accent">
                {{-- System roles: name locked, the code depends on it --}}
                <x-ui.field
                    name="name"
                    :label="__('translation.roles.name')"
                    :help="$role->is_system ? __('translation.roles.system_name_locked') : __('translation.roles.name_help')"
                >
                    <x-ui.input id="name" name="name" class="font-mono" :value="old('name', $role->name)" :required="! $role->is_system" :disabled="$role->is_system" />
                </x-ui.field>
            </x-ui.card>

            <x-ui.card :title="__('translation.roles.permissions')" icon="key-round" tone="caution" flush>
                @include('backend.roles.partials.permission-picker', [
                    'adminRole' => $role->name === 'admin',
                    'permissions' => $permissions,
                    'selected' => old('permissions', $role->permissions->pluck('id')->toArray()),
                ])
            </x-ui.card>
        </form>
    </x-ui.page>
</x-app-layout>
