<x-app-layout>
    <x-slot name="header">{{ __('translation.permissions.view') }}</x-slot>

    <x-ui.page :title="\App\Support\PermissionGroup::permissionLabel($permission->name)" :lead="$permission->name">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.permissions.index')">{{ __('translation.navigation.permissions') }}</x-ui.button>
            @permission('edit-permission')
                <x-ui.button
                    variant="primary"
                    size="md"
                    icon="pencil"
                    :href="route('backend.permissions.edit', $permission)"
                    :disabled="$permission->is_system"
                    :disabled-reason="__('translation.permissions.system_locked')"
                >
                    {{ __('translation.edit') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        @php($group = \App\Support\PermissionGroup::of($permission->name))

        <x-ui.card :title="__('translation.details')" :icon="\App\Support\PermissionGroup::icon($group)" tone="accent">
            <x-ui.facts>
                <x-ui.fact :label="__('translation.permissions.name')">
                    <code class="font-mono text-xs">{{ $permission->name }}</code>
                </x-ui.fact>
                <x-ui.fact :label="__('translation.permissions.group')">
                    {{ \App\Support\PermissionGroup::label($group) }}
                </x-ui.fact>
                <x-ui.fact :label="__('translation.roles.type')">
                    {{ $permission->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom') }}
                </x-ui.fact>
                <x-ui.fact :label="__('translation.permissions.used_by')">
                    <span class="flex flex-wrap gap-1">
                        @forelse($permission->roles as $role)
                            <x-ui.badge :tone="$role->name === 'admin' ? 'accent' : 'neutral'">{{ $role->label }}</x-ui.badge>
                        @empty
                            <span class="text-fg-muted">{{ __('translation.permissions.unused') }}</span>
                        @endforelse
                    </span>
                </x-ui.fact>
            </x-ui.facts>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
