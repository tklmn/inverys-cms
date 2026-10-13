<x-app-layout>
    <x-slot name="header">{{ __('translation.users.view') }}</x-slot>

    <x-ui.page :title="$user->name" :lead="$user->job_title">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.users.index')">{{ __('translation.navigation.users') }}</x-ui.button>
            @permission('edit-user')
                <x-ui.button variant="primary" size="md" icon="pencil" :href="route('backend.users.edit', $user)">
                    {{ __('translation.edit') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <div class="ui-split">
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.card :title="__('translation.users.profile_information')" icon="user-round" tone="accent">
                    <x-ui.facts>
                        <x-ui.fact :label="__('translation.users.first_name')">{{ $user->first_name ?: '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.users.last_name')">{{ $user->last_name ?: '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.users.phone')">{{ $user->phone ?: '—' }}</x-ui.fact>
                        @if($user->bio)
                            <x-ui.fact :label="__('translation.users.bio')">{{ $user->bio }}</x-ui.fact>
                        @endif
                    </x-ui.facts>
                </x-ui.card>

                <x-ui.card :title="__('translation.users.roles')" icon="shield" tone="caution">
                    @if($user->roles->isEmpty())
                        <x-ui.empty icon="shield">{{ __('translation.users.no_roles') }}</x-ui.empty>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($user->roles as $role)
                                <x-ui.badge :tone="$role->name === 'admin' ? 'accent' : 'neutral'">
                                    {{ $role->label }}@if($role->is_system) · {{ __('translation.roles.system_tag') }}@endif
                                </x-ui.badge>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            {{-- Person at a glance: picture, status, security --}}
            <aside class="flex flex-col gap-4">
                <x-ui.card>
                    <div class="flex flex-col items-center gap-3 text-center">
                        <x-ui.avatar :user="$user" size="lg" />
                        <div class="min-w-0">
                            <p class="font-semibold text-fg">{{ $user->name }}</p>
                            <p class="ui-help break-all">{{ $user->email }}</p>
                        </div>
                        @if($user->is_active)
                            <x-ui.badge tone="positive">{{ __('translation.users.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="critical">{{ __('translation.users.inactive') }}</x-ui.badge>
                        @endif
                    </div>

                    <x-ui.facts class="mt-4 border-t border-line pt-4">
                        <x-ui.fact :label="__('translation.users.last_login')">
                            {{ $user->last_login_at ? format_datetime($user->last_login_at) : '—' }}
                        </x-ui.fact>
                        <x-ui.fact :label="__('translation.users.email_verified')">
                            @if($user->email_verified_at)
                                <x-ui.badge tone="positive">{{ format_date($user->email_verified_at) }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="caution">{{ __('translation.users.not_verified') }}</x-ui.badge>
                            @endif
                        </x-ui.fact>
                        <x-ui.fact :label="__('translation.users.two_factor')">
                            @if(method_exists($user, 'hasTotpEnabled') && $user->hasTotpEnabled())
                                <x-ui.badge tone="positive">{{ __('translation.users.two_factor_app') }}</x-ui.badge>
                            @else
                                <x-ui.badge>{{ __('translation.users.two_factor_email') }}</x-ui.badge>
                            @endif
                        </x-ui.fact>
                        <x-ui.fact :label="__('translation.created_at')">{{ format_date($user->created_at) }}</x-ui.fact>
                    </x-ui.facts>
                </x-ui.card>
            </aside>
        </div>
    </x-ui.page>
</x-app-layout>
