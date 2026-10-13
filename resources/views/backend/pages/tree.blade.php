<x-app-layout>
    <x-slot name="head">
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </x-slot>
    
    @push('scripts')
    <script @cspNonce>
        // Translations for JS
        window.translations = {
            ...(window.translations || {}),
            pages: {
                saving: "{{ __('translation.pages.saving') }}",
                saved: "{{ __('translation.pages.saved') }}",
                autosave_enabled: "{{ __('translation.pages.autosave_enabled') }}",
                save_error: "{{ __('translation.pages.save_error') }}"
            }
        };
        
        // Routes for JS
        window.routes = {
            ...(window.routes || {}),
            pages: {
                updateTree: "{{ route('backend.pages.update-tree') }}"
            }
        };
    </script>
    @endpush

    <x-slot name="header">
        {{ __('translation.pages.tree') }}
    </x-slot>

    <x-ui.page :title="__('translation.pages.tree')">
        <x-slot:actions>
            <x-ui.button size="md" icon="list" :href="route('backend.pages.index')">
                {{ __('translation.pages.list_view') }}
            </x-ui.button>
            @permission('create-page')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.pages.create')">
                    {{ __('translation.pages.add_new') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.note>{{ __('translation.pages.tree_instructions') }}</x-ui.note>

        <x-ui.card flush>
            <div id="page-tree">
                @if(count($rootPages) > 0)
                    <div id="page-tree-container" class="ui-tree">
                        <ul class="page-tree-root">
                            @foreach($rootPages as $rootPage)
                                @include('backend.pages.partials.tree-node', ['page' => $rootPage])
                            @endforeach
                        </ul>
                    </div>
                    <p class="ui-help flex items-center gap-1.5 border-t border-line px-4 py-3">
                        <span class="hidden inline-flex items-center gap-1.5" id="save-status">
                            <x-ui.icon name="circle-check" class="text-positive" />
                            {{ __('translation.pages.autosave_enabled') }}
                        </span>
                    </p>
                @else
                    <x-ui.empty icon="folder-tree" :title="__('translation.pages.no_pages_found')">
                        @permission('create-page')
                            <x-slot:actions>
                                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.pages.create')">{{ __('translation.pages.create_first_page') }}</x-ui.button>
                            </x-slot:actions>
                        @endpermission
                    </x-ui.empty>
                @endif
            </div>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
