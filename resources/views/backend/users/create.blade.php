<x-app-layout>
    <x-slot name="header">{{ __('translation.users.create') }}</x-slot>

    <x-ui.page :title="__('translation.users.create')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="user-form">{{ __('translation.create') }}</x-ui.button>
        </x-slot:actions>

        @include('backend.users.partials.update-user-information-form')
    </x-ui.page>
</x-app-layout>
