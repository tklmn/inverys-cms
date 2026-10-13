{{--
    New page (direct call, e.g. from the dashboard). The page list
    opens the same form as a dialog. After creating, it goes to the editor.
--}}
<x-app-layout>
    <x-slot name="header">
        {{ __('translation.pages.create') }}
    </x-slot>

    <x-ui.page :title="__('translation.pages.add_new')" :lead="__('translation.pages.new_page_intro')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.pages.index')">{{ __('translation.pages.title') }}</x-ui.button>
        </x-slot:actions>

        <x-ui.card>
            @include('backend.pages.partials.new-page-form', ['parentOptions' => $parentOptions, 'starters' => $starters, 'idPrefix' => 'create-page'])
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
