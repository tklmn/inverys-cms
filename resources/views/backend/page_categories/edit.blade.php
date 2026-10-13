@php
    // Multilingual: original or translation
    $i18n = \App\Support\EditLocale::form($pageCategory);
@endphp
<x-app-layout>
    <x-slot name="header">{{ __('translation.pages.edit_category') }}</x-slot>

    <x-ui.page :title="__('translation.pages.edit_category').': '.$pageCategory->getOriginalValue('name')">
        <x-slot:actions>
            <x-ui.locale-switch :model="$pageCategory" />
        </x-slot:actions>

        <form action="{{ route('backend.page-categories.update', $pageCategory) }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')
            @if($i18n->locale)
                <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
            @endif

            <x-ui.translation-note :form="$i18n" />

            <x-ui.card>
                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('translation.pages.name')">
                        <x-ui.input id="name" name="name" :value="$i18n->value('name')" :placeholder="$i18n->placeholder('name')" :required="! $i18n->shared" autofocus />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('translation.pages.category.description')" optional>
                        <x-ui.input id="description" name="description" :value="$i18n->value('description')" :placeholder="$i18n->placeholder('description')" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.page-categories.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.update') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
