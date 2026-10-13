<x-app-layout>
    <x-slot name="header">{{ __('translation.permissions.create') }}</x-slot>

    <x-ui.page :title="__('translation.permissions.create')" :lead="__('translation.permissions.name_hint')">
        <form method="POST" action="{{ route('backend.permissions.store') }}" class="flex flex-col gap-4">
            @csrf

            <x-ui.card>
                <x-ui.field name="name" :label="__('translation.permissions.name')" :help="__('translation.permissions.name_pattern')">
                    <x-ui.input id="name" name="name" :value="old('name')" class="font-mono" required autofocus />
                </x-ui.field>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.permissions.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
