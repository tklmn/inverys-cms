<x-dynamic-layout>
    <x-slot name="header">{{ __('translation.users.edit_profile') }}</x-slot>

    <x-ui.page :title="__('translation.users.edit_profile')" :lead="__('translation.users.profile_update_info')">
        <x-slot:actions>
            <x-ui.button size="md" icon="eye" :href="route('profile.show', $user)">{{ __('translation.users.public_profile') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="profile-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        {{--
            Two independent columns: on the left everything about the person (one form)
            and access, on the right appearance and account — no empty area next to
            the long person card.
        --}}
        <div class="ui-split">
            <div class="ui-split-main">
                @include('profile.partials.update-profile-information-form')

                <x-ui.card :title="__('translation.users.password_update')" icon="lock" tone="teal">
                    @include('profile.partials.update-password-form')
                </x-ui.card>

                <x-ui.card :title="__('auth.two_factor.profile_title')" icon="shield-check" tone="positive">
                    @include('profile.partials.two-factor-setup')
                </x-ui.card>
            </div>

            <div class="ui-split-aside">
                <x-ui.card :title="__('translation.users.appearance')" icon="palette" tone="violet">
                    @include('profile.partials.theme-section')
                </x-ui.card>

                {{-- Destructive area deliberately set apart --}}
                <x-ui.card :title="__('translation.users.delete_account')" icon="trash-2" tone="critical">
                    @include('profile.partials.delete-user-form')
                </x-ui.card>
            </div>
        </div>
    </x-ui.page>

    @include('profile.partials.delete-avatar-form')
</x-dynamic-layout>
