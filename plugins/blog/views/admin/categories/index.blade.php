<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.categories_management') }}</x-slot> <x-ui.page :title="__('blog::blog.categories_management')"> <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.index')"> {{ __('blog::blog.posts') }} </x-ui.button>

            @permission('create-blog-category') <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.blog.categories.create')"> {{ __('blog::blog.create_category') }} </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form method="GET" action="{{ route('backend.blog.categories.index') }}" class="flex w-full flex-wrap items-center gap-2"> <x-ui.input
                        icon="search" type="search" name="search" value="{{ request('search') }}" class="w-full sm:w-64" :placeholder="__('blog::blog.search_categories')" :aria-label="__('translation.search')" />
                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button> </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($categories as $category)
                    <x-ui.list-row
                        :title="$category->name" icon="tag" :href="auth()->user()->hasPermission('edit-blog-category') ? route('backend.blog.categories.edit', $category) : null" >
                        <x-slot:meta>
                            <span>/{{ $category->slug }}</span>
                            <x-ui.translation-status :model="$category" />
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('blog::blog.category_posts_count')"> {{ $category->posts_count }}
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('edit-blog-category') <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.blog.categories.edit', $category)" /> @endpermission

                            @permission('delete-blog-category') <form id="delete-form-blog-category-{{ $category->id }}" action="{{ route('backend.blog.categories.destroy', $category) }}" method="POST"> @csrf
                                    @method('DELETE') <x-ui.button
                                        variant="danger" icon="trash-2" :label="__('translation.delete')" :disabled="$category->posts_count > 0" :disabled-reason="__('blog::blog.category_in_use')" data-delete-modal="{{ json_encode(['formId' => 'delete-form-blog-category-'.$category->id]) }}" />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="tag">{{ __('blog::blog.no_categories_found') }}</x-ui.empty> @endforelse
            </x-ui.list>

            @if($categories->hasPages())
                <x-slot:foot>{{ $categories->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
