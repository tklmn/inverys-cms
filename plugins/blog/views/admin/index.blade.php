<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.posts') }}</x-slot>

    <x-ui.page :title="__('blog::blog.posts')">
        <x-slot:actions>
            @permission('show-blog-category')
                <x-ui.button size="md" icon="tag" :href="route('backend.blog.categories.index')">
                    {{ __('blog::blog.categories') }}
                </x-ui.button>
            @endpermission

            @permission('create-post')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.blog.create')">
                    {{ __('blog::blog.create_post') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form method="GET" action="{{ route('backend.blog.index') }}" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-64"
                        :placeholder="__('blog::blog.search_posts')"
                        :aria-label="__('translation.search')"
                    />

                    <x-ui.select name="status" data-autosubmit class="w-auto" :aria-label="__('blog::blog.status')">
                        <option value="">{{ __('blog::blog.status') }}</option>
                        <option value="published" @selected(request('status') === 'published')>{{ __('blog::blog.published') }}</option>
                        <option value="draft" @selected(request('status') === 'draft')>{{ __('blog::blog.draft') }}</option>
                        <option value="scheduled" @selected(request('status') === 'scheduled')>{{ __('blog::blog.scheduled') }}</option>
                    </x-ui.select>

                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button>
                </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($posts as $post)
                    <x-ui.list-row
                        :title="$post->title"
                        :href="auth()->user()->hasPermission('edit-post') ? route('backend.blog.edit', $post) : null"
                    >
                        {{-- Featured image as thumbnail, otherwise the post icon --}}
                        <x-slot:lead>
                            @if($post->featured_image)
                                <img src="{{ asset('storage/'.$post->featured_image) }}" alt="" loading="lazy" class="h-full w-full rounded-md object-cover">
                            @else
                                <x-ui.nav-icon name="file-text" />
                            @endif
                        </x-slot:lead>

                        <x-slot:meta>
                            <span>/{{ $post->slug }}</span>
                            <x-ui.translation-status :model="$post" />
                            <span>{{ $post->author?->full_name ?? '—' }}</span>
                            @if($post->categories->isNotEmpty())
                                <span>{{ $post->categories->pluck('name')->join(', ') }}</span>
                            @endif
                        </x-slot:meta>

                        <x-slot:cols>
                            {{-- Short date in the column, time in the tooltip --}}
                            <x-ui.list-col :label="__('blog::blog.published_at')" priority="low">
                                @if($post->published_at)
                                    <span class="whitespace-nowrap" title="{{ format_datetime($post->published_at) }}">{{ format_date($post->published_at) }}</span>
                                @else
                                    —
                                @endif
                            </x-ui.list-col>

                            <x-ui.list-col>
                                @if($post->isPublished())
                                    <x-ui.badge tone="positive">{{ __('blog::blog.published') }}</x-ui.badge>
                                @elseif($post->isScheduled())
                                    <x-ui.badge tone="caution">{{ __('blog::blog.scheduled') }}</x-ui.badge>
                                @else
                                    <x-ui.badge>{{ __('blog::blog.draft') }}</x-ui.badge>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            {{-- View in the frontend; drafts and scheduled posts only as a preview with edit permission --}}
                            @if($post->isPublished())
                                <x-ui.button variant="ghost" icon="external-link" :label="__('blog::blog.view_post')" :href="route('blog.show', $post->slug)" target="_blank" rel="noopener noreferrer" />
                            @elseif(auth()->user()->hasPermission('edit-post'))
                                <x-ui.button variant="ghost" icon="eye" :label="__('blog::blog.preview')" :href="route('blog.show', $post->slug)" target="_blank" rel="noopener noreferrer" />
                            @else
                                <x-ui.button variant="ghost" icon="eye" :label="__('blog::blog.preview')" :disabled="true" :disabled-reason="__('blog::blog.preview_unavailable')" />
                            @endif

                            @permission('edit-post')
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.blog.edit', $post)" />
                            @endpermission

                            @permission('delete-post')
                                <form id="delete-form-post-{{ $post->id }}" action="{{ route('backend.blog.destroy', $post) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-form-post-'.$post->id]) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="file-text">{{ __('blog::blog.no_posts') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($posts->hasPages())
                <x-slot:foot>{{ $posts->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
