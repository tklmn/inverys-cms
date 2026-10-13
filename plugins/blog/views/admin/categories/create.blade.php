<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.create_category') }}</x-slot>

    <x-ui.page :title="__('blog::blog.create_category')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.categories.index')">
                {{ __('blog::blog.categories') }}
            </x-ui.button>
        </x-slot:actions>

        <form action="{{ route('backend.blog.categories.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-ui.card>
                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('blog::blog.category_name')">
                        <x-ui.input id="name" name="name" :value="old('name')" required autofocus />
                    </x-ui.field>

                    <x-ui.field name="slug" :label="__('blog::blog.category_slug')" optional>
                        <x-ui.input id="slug" name="slug" :value="old('slug')" :placeholder="__('blog::blog.category_slug_auto')" />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('blog::blog.category_description')" class="ui-grid-full" optional>
                        <x-ui.textarea id="description" name="description" rows="4">{{ old('description') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.blog.categories.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
