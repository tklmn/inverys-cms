<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.edit_form') }}</x-slot>

    @php($i18n = \App\Support\EditLocale::form($form))

    <x-ui.page :title="__('form-builder::forms.edit_form')" :lead="$form->name">
        <x-slot:actions>
            <x-ui.locale-switch :model="$form" />

            @permission('show-form-submission')
                <x-ui.button size="md" icon="inbox" :href="route('backend.forms.submissions.index', $form)">
                    {{ __('form-builder::forms.submissions') }}
                </x-ui.button>
            @endpermission

            <x-ui.button size="md" icon="arrow-left" :href="route('backend.forms.index')">
                {{ __('form-builder::forms.forms') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="form-edit-form">{{ __('translation.update') }}</x-ui.button>
        </x-slot:actions>

        <form id="form-edit-form" action="{{ route('backend.forms.update', $form) }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            @if($i18n->locale)
                {{-- Translation: only the texts, the field list applies to all languages --}}
                <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
                <x-ui.translation-note :form="$i18n" />
                @include('form-builder-admin::partials.translation-table', ['form' => $form, 'locale' => $i18n->locale])
            @else
                @include('form-builder-admin::partials.form-fields', ['form' => $form])
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.forms.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.update') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>

    @if(! $i18n->locale)
    @push('scripts')
    @include('form-builder-admin::_form-builder-js')
    <script @cspNonce>
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('form-builder-container');
        var input = document.getElementById('fields-input');
        var existing = [];

        try {
            existing = JSON.parse(input.value);
        } catch (e) {
            // Invalid field data — start empty
        }

        new FormBuilder(container, {
            fields: existing,
            inputName: 'fields',
            hiddenInput: input,
            saveUrl: @json(route('backend.forms.save-fields', $form)),
            csrf: @json(csrf_token()),
            t: @json(__('form-builder::forms')),
        });
    });
    </script>
    @endpush
    @endif
</x-app-layout>
