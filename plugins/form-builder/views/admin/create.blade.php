<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.create_form') }}</x-slot>

    <x-ui.page :title="__('form-builder::forms.create_form')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.forms.index')">
                {{ __('form-builder::forms.forms') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="form-create-form">{{ __('translation.create') }}</x-ui.button>
        </x-slot:actions>

        <form id="form-create-form" action="{{ route('backend.forms.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            @include('form-builder-admin::partials.form-fields', ['form' => null])

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.forms.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>

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
            // Invalid draft — start empty
        }

        new FormBuilder(container, {
            fields: existing,
            inputName: 'fields',
            hiddenInput: input,
            draftUrl: @json(route('backend.forms.store-draft')),
            csrf: @json(csrf_token()),
            t: @json(__('form-builder::forms')),
        });
    });
    </script>
    @endpush
</x-app-layout>
