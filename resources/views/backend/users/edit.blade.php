<x-app-layout>
    <x-slot name="header">{{ __('translation.users.edit') }}</x-slot>

    <x-ui.page :title="__('translation.users.edit').': '.$user->name">
        <x-slot:actions>
            <x-ui.button size="md" icon="eye" :href="route('backend.users.show', $user)">{{ __('translation.view') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="user-form">{{ __('translation.update') }}</x-ui.button>
        </x-slot:actions>

        @include('backend.users.partials.update-user-information-form')
    </x-ui.page>
</x-app-layout>
