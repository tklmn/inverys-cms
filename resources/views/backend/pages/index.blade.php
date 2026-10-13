<x-app-layout>
    <x-slot name="header">{{ __('translation.pages.title') }}</x-slot>

    <x-ui.page :title="__('translation.pages.list')">
        <x-slot:actions>
            <x-ui.button size="md" icon="folder-tree" :href="route('backend.pages.tree')">
                {{ __('translation.pages.tree_view') }}
            </x-ui.button>
            <x-ui.button size="md" icon="tag" :href="route('backend.page-categories.index')">
                {{ __('translation.pages.page_categories') }}
            </x-ui.button>
            @permission('create-page')
                <x-ui.button variant="primary" size="md" icon="plus" type="button" data-open-modal="new-page">
                    {{ __('translation.pages.add_new') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form action="{{ route('backend.pages.index') }}" method="GET" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        id="page-search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-64"
                        :placeholder="__('translation.search')"
                        :aria-label="__('translation.search')"
                    />

                    <x-ui.select name="status" id="page-status" class="w-auto" :aria-label="__('translation.pages.status')">
                        <option value="">{{ __('translation.pages.all_statuses') }}</option>
                        <option value="active" @selected(request('status') === 'active')>{{ __('translation.pages.active') }}</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>{{ __('translation.pages.inactive') }}</option>
                        <option value="trashed" @selected(request('status') === 'trashed')>{{ __('translation.pages.trashed') }}</option>
                    </x-ui.select>

                    <x-ui.select name="menu" id="page-menu" class="w-auto" :aria-label="__('translation.pages.menu_visible')">
                        <option value="">{{ __('translation.pages.all_menu_states') }}</option>
                        <option value="visible" @selected(request('menu') === 'visible')>{{ __('translation.pages.menu_visible') }}</option>
                        <option value="hidden" @selected(request('menu') === 'hidden')>{{ __('translation.pages.menu_hidden') }}</option>
                    </x-ui.select>

                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button>
                </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($pages as $page)
                    @include('backend.pages.partials.table-row', ['page' => $page, 'level' => 0])
                @empty
                    {{-- With a filter: no "Create first page" — there are pages, just none that match --}}
                    @if(collect(['search', 'status', 'menu'])->contains(fn ($key) => request()->filled($key)))
                        <x-ui.empty icon="search" :title="__('translation.pages.no_pages_found')">{{ __('translation.no_filter_results') }}</x-ui.empty>
                    @else
                        <x-ui.empty icon="file-text" :title="__('translation.pages.no_pages_found')">
                            <x-slot:actions>
                                @permission('create-page')
                                    <x-ui.button variant="primary" size="md" icon="plus" type="button" data-open-modal="new-page">
                                        {{ __('translation.pages.create_first_page') }}
                                    </x-ui.button>
                                @endpermission
                            </x-slot:actions>
                        </x-ui.empty>
                    @endif
                @endforelse
            </x-ui.list>

            @if($pages->hasPages())
                <x-slot:foot>{{ $pages->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>

    @permission('create-page')
        {{-- New page: after an input error the dialog is open again --}}
        <x-modal name="new-page" maxWidth="2xl" :show="$errors->any() && old('starter') !== null" focusable>
            <div class="flex flex-col gap-4 p-5 sm:p-6">
                <div>
                    <h2 class="ui-card-title" id="modal-new-page-title">{{ __('translation.pages.add_new') }}</h2>
                    <p class="ui-help mt-1">{{ __('translation.pages.new_page_intro') }}</p>
                </div>
                @include('backend.pages.partials.new-page-form', ['parentOptions' => $parentOptions, 'starters' => $starters, 'idPrefix' => 'new-page', 'cancel' => new \Illuminate\Support\HtmlString('<button type="button" class="ui-btn ui-btn-md ui-btn-secondary" data-close-modal>'.e(__('translation.cancel')).'</button>')])
            </div>
        </x-modal>
    @endpermission
</x-app-layout>
