<x-app-layout>
    <x-slot name="header">{{ __('translation.permissions.edit') }}</x-slot>

    <x-ui.page :title="__('translation.permissions.edit').': '.\App\Support\PermissionGroup::permissionLabel($permission->name)">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.permissions.index')">{{ __('translation.navigation.permissions') }}</x-ui.button>
            <x-ui.button
                variant="primary"
                size="md"
                icon="check"
                type="submit"
                form="permission-form"
                :disabled="$permission->is_system"
                :disabled-reason="__('translation.permissions.system_locked')"
            >{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="permission-form" method="POST" action="{{ route('backend.permissions.update', $permission) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            @if($permission->is_system)
                <x-ui.note tone="caution">{{ __('translation.permissions.system_locked') }}</x-ui.note>
            @endif

            <x-ui.card :title="__('translation.roles.general')" icon="key-round" tone="accent">
                <x-ui.field name="name" :label="__('translation.permissions.name')" :help="__('translation.permissions.name_pattern')">
                    <x-ui.input id="name" name="name" :value="old('name', $permission->name)" class="font-mono" required autofocus :disabled="$permission->is_system" />
                </x-ui.field>
            </x-ui.card>
        </form>
    </x-ui.page>
</x-app-layout>
