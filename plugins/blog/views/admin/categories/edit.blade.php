<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.edit_category') }}</x-slot>

    @php($i18n = \App\Support\EditLocale::form($category))

    <x-ui.page :title="__('blog::blog.edit_category')" :lead="$category->getOriginalValue('name')">
        <x-slot:actions>
            <x-ui.locale-switch :model="$category" />
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.categories.index')">
                {{ __('blog::blog.categories') }}
            </x-ui.button>
        </x-slot:actions>

        <form action="{{ route('backend.blog.categories.update', $category) }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')
            @if($i18n->locale)
                <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
            @endif

            <x-ui.translation-note :form="$i18n" />

            <x-ui.card>
                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('blog::blog.category_name')">
                        <x-ui.input id="name" name="name" :value="$i18n->value('name')" :placeholder="$i18n->placeholder('name')" :required="! $i18n->shared" autofocus />
                    </x-ui.field>

                    <x-ui.field name="slug" :label="__('blog::blog.category_slug')">
                        <x-ui.input id="slug" name="slug" :value="$i18n->value('slug')" :placeholder="$i18n->placeholder('slug')" />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('blog::blog.category_description')" class="ui-grid-full" optional>
                        <x-ui.textarea id="description" name="description" rows="4" :placeholder="$i18n->placeholder('description')">{{ $i18n->value('description') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.blog.categories.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.update') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
