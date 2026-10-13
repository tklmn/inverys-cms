<x-app-layout>
    <x-slot name="header">{{ __('translation.pages.page_categories') }}</x-slot>

    <x-ui.page :title="__('translation.pages.page_categories')">
        <x-slot:actions>
            @permission('create-page-category')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.page-categories.create')">
                    {{ __('translation.pages.add_category') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.list>
                @forelse($categories as $category)
                    @php($editUrl = auth()->user()->hasPermission('edit-page-category') ? route('backend.page-categories.edit', $category) : null)
                    <x-ui.list-row :title="$category->name" icon="tag" :href="$editUrl">
                        <x-slot:meta>
                            @if($category->description)<span>{{ $category->description }}</span>@endif
                            <x-ui.translation-status :model="$category" />
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.pages.title')">
                                {{ $category->pages_count }}
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('edit-page-category')
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.page-categories.edit', $category)" />
                            @endpermission

                            @permission('delete-page-category')
                                <form id="delete-form-page-category-{{ $category->id }}" action="{{ route('backend.page-categories.destroy', $category) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        data-delete-modal="{{ json_encode(array_filter([
                                            'formId' => 'delete-form-page-category-'.$category->id,
                                            // Before deleting, say how many pages lose the category
                                            'subtitle' => $category->pages_count ? trans_choice('translation.pages.category_used_by', $category->pages_count, ['count' => $category->pages_count]) : null,
                                        ])) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="tag">{{ __('translation.pages.no_categories_found') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($categories->hasPages())
                <x-slot:foot>{{ $categories->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
