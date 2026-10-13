<x-app-layout>
    <x-slot name="header">{{ __('translation.pages.add_category') }}</x-slot>

    <x-ui.page :title="__('translation.pages.add_category')">
        <form action="{{ route('backend.page-categories.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-ui.card>
                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('translation.pages.name')">
                        <x-ui.input id="name" name="name" :value="old('name')" required autofocus />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('translation.pages.category.description')" optional>
                        <x-ui.input id="description" name="description" :value="old('description')" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.page-categories.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
