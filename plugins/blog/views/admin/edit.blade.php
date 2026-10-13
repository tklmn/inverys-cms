<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.edit_post') }}</x-slot>

    <x-ui.page :title="__('blog::blog.edit_post')" :lead="$post->getOriginalValue('title')">
        <x-slot:actions>
            <x-ui.locale-switch :model="$post" />
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.index')">
                {{ __('blog::blog.posts') }}
            </x-ui.button>
            {{-- Frontend address; when unpublished, a preview with a notice --}}
            <x-ui.button size="md" :icon="$post->isPublished() ? 'external-link' : 'eye'" :href="route('blog.show', $post->getOriginalValue('slug'))" target="_blank" rel="noopener noreferrer">
                {{ $post->isPublished() ? __('blog::blog.view_post') : __('blog::blog.preview') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="post-form">{{ __('translation.update') }}</x-ui.button>
        </x-slot:actions>

        @include('blog-admin::partials.post-form', ['post' => $post, 'categories' => $categories])

        {{-- Earlier versions: compare, view, restore (as for pages) --}}
        <x-ui.card :title="__('translation.pages.revisions.title')" icon="history" tone="caution">
            @include('backend.partials.revisions', ['revisionable' => $post, 'revisionType' => 'post', 'revisionsAutoload' => true])
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
