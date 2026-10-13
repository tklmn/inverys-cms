<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.create_post') }}</x-slot>

    <x-ui.page :title="__('blog::blog.create_post')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.index')">
                {{ __('blog::blog.posts') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="post-form">{{ __('translation.create') }}</x-ui.button>
        </x-slot:actions>

        @include('blog-admin::partials.post-form', ['post' => null, 'categories' => $categories])
    </x-ui.page>
</x-app-layout>
