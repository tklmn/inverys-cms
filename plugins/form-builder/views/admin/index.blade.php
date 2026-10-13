<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.forms') }}</x-slot> <x-ui.page :title="__('form-builder::forms.forms')"> <x-slot:actions>
            @permission('create-form') <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.forms.create')"> {{ __('form-builder::forms.create_form') }} </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form method="GET" action="{{ route('backend.forms.index') }}" class="flex w-full flex-wrap items-center gap-2"> <x-ui.input
                        icon="search" type="search" name="search" value="{{ request('search') }}" class="w-full sm:w-64" :placeholder="__('form-builder::forms.search_forms')" :aria-label="__('translation.search')" />
                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button> </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($forms as $form)
                    <x-ui.list-row
                        :title="$form->name" icon="clipboard-list" :href="auth()->user()->hasPermission('edit-form') ? route('backend.forms.edit', $form) : null" >
                        <x-slot:meta>
                            <span>/{{ $form->slug }}</span>
                            <x-ui.translation-status :model="$form" />
                            <span>{{ trans_choice('form-builder::forms.field_count', count($form->getFlatFields()), ['count' => count($form->getFlatFields())]) }}</span> </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('form-builder::forms.submissions')" priority="low"> {{ $form->submissions_count ?? 0 }}
                            </x-ui.list-col>

                            <x-ui.list-col>
                                @if($form->is_active)
                                    <x-ui.badge tone="positive">{{ __('form-builder::forms.active') }}</x-ui.badge> @else
                                    <x-ui.badge>{{ __('form-builder::forms.inactive') }}</x-ui.badge> @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('show-form-submission') <x-ui.button variant="ghost" icon="inbox" :label="__('form-builder::forms.submissions')" :href="route('backend.forms.submissions.index', $form)" /> @endpermission

                            @permission('edit-form') <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.forms.edit', $form)" /> @endpermission

                            @permission('delete-form') <form id="delete-form-{{ $form->id }}" action="{{ route('backend.forms.destroy', $form) }}" method="POST"> @csrf
                                    @method('DELETE') <x-ui.button
                                        variant="danger" icon="trash-2" :label="__('translation.delete')" data-delete-modal="{{ json_encode(['formId' => 'delete-form-'.$form->id]) }}" />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    {{-- With a search, not "no forms yet" — there are just no matching ones --}}
                    <x-ui.empty :icon="request()->filled('search') ? 'search' : 'clipboard-list'">{{ request()->filled('search') ? __('translation.no_filter_results') : __('form-builder::forms.no_forms') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($forms->hasPages())
                <x-slot:foot>{{ $forms->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
