<x-dynamic-layout>
    <x-slot name="header">{{ __('translation.users.public_profile') }}</x-slot>

    <x-ui.page :title="$user->full_name ?: $user->name" :lead="$user->job_title">
        <x-slot:actions>
            @if (auth()->id() === $user->id)
                <x-ui.button variant="primary" size="md" icon="pencil" :href="route('profile.edit')">{{ __('translation.users.edit_profile') }}</x-ui.button>
            @endif
        </x-slot:actions>

        <div class="ui-split">
            <div class="ui-split-main">
                <x-ui.card :title="__('translation.users.profile_information')" icon="user-round" tone="accent">
                    <x-ui.facts>
                        <x-ui.fact :label="__('translation.users.first_name')">{{ $user->first_name ?: '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.users.last_name')">{{ $user->last_name ?: '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.email')">{{ $user->email }}</x-ui.fact>
                        @if($user->phone)
                            <x-ui.fact :label="__('translation.users.phone')">{{ $user->phone }}</x-ui.fact>
                        @endif
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
                                <x-ui.badge :tone="$role->name === 'admin' ? 'accent' : 'neutral'">{{ $role->label }}</x-ui.badge>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </div>

            <aside class="ui-split-aside">
                <x-ui.card>
                    <div class="flex flex-col items-center gap-3 text-center">
                        <x-ui.avatar :user="$user" size="lg" />
                        <div class="min-w-0">
                            <p class="font-semibold text-fg">{{ $user->name }}</p>
                            @if($user->job_title)
                                <p class="ui-help">{{ $user->job_title }}</p>
                            @endif
                        </div>
                        @if($user->is_active)
                            <x-ui.badge tone="positive">{{ __('translation.users.active') }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="critical">{{ __('translation.users.inactive') }}</x-ui.badge>
                        @endif
                    </div>

                    <x-ui.facts class="mt-4 border-t border-line pt-4">
                        <x-ui.fact :label="__('translation.users.last_login')">{{ $user->last_login_at ? format_datetime($user->last_login_at) : '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.created_at')">{{ format_date($user->created_at) }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.updated_at')">{{ format_date($user->updated_at) }}</x-ui.fact>
                    </x-ui.facts>
                </x-ui.card>
            </aside>
        </div>
    </x-ui.page>
</x-dynamic-layout>
