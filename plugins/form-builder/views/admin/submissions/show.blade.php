<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.submission') }} — {{ $form->name }}</x-slot>

    <x-ui.page :title="__('form-builder::forms.submission').' #'.$submission->id" :lead="$form->name">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="md" icon="arrow-left" :href="route('backend.forms.submissions.index', $form)">
                {{ __('translation.back') }}
            </x-ui.button>

            @permission('show-form-submission')
                <form action="{{ route('backend.forms.submissions.toggle-read', [$form, $submission]) }}" method="POST">
                    @csrf
                    <x-ui.button variant="secondary" size="md" :icon="$submission->is_read ? 'mail' : 'mail-open'" type="submit">
                        {{ $submission->is_read ? __('form-builder::forms.mark_unread') : __('form-builder::forms.mark_read') }}
                    </x-ui.button>
                </form>
            @endpermission

            @permission('delete-form-submission')
                <form id="delete-submission-{{ $submission->id }}" action="{{ route('backend.forms.submissions.destroy', [$form, $submission]) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" size="md" icon="trash-2" data-delete-modal="{{ json_encode(['formId' => 'delete-submission-'.$submission->id]) }}">
                        {{ __('translation.delete') }}
                    </x-ui.button>
                </form>
            @endpermission
        </x-slot:actions>

        <x-ui.card :title="__('form-builder::forms.submitted_data')" icon="inbox" tone="accent">
            <x-ui.facts>
                @foreach($form->getDataFields() as $field)
                    @php
                        $fieldName = $field['name'] ?? '';
                        $fieldType = $field['type'] ?? 'text';
                        $value = $submission->data[$fieldName] ?? '';
                    @endphp

                    @continue($fieldType === 'hidden')

                    <x-ui.fact :label="$field['label'] ?? $fieldName" @class(['ui-grid-full' => $fieldType === 'textarea'])>
                        @if($fieldType === 'file' && ! empty($submission->files[$fieldName]))
                            <a
                                href="{{ route('backend.forms.submission.file', ['formSlug' => $form->slug, 'filename' => basename($submission->files[$fieldName])]) }}"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-accent hover:underline"
                            >
                                <x-ui.icon name="paperclip" />{{ $value ?: basename($submission->files[$fieldName]) }}
                            </a>
                        @elseif($fieldType === 'textarea')
                            <span class="whitespace-pre-wrap font-normal">{{ $value }}</span>
                        @elseif(is_array($value))
                            {{ implode(', ', $value) }}
                        @else
                            {{ $value !== '' ? $value : '—' }}
                        @endif
                    </x-ui.fact>
                @endforeach
            </x-ui.facts>
        </x-ui.card>

        <x-ui.card :title="__('form-builder::forms.metadata')" icon="info" tone="teal">
            <x-ui.facts>
                <x-ui.fact :label="__('form-builder::forms.submitted_at')">{{ format_datetime($submission->created_at, true) }}</x-ui.fact>

                <x-ui.fact :label="__('form-builder::forms.read_status')">
                    @if($submission->is_read)
                        <x-ui.badge>{{ __('form-builder::forms.read') }}</x-ui.badge>
                        @if($submission->read_at)
                            <span class="ms-1 font-normal text-fg-muted">{{ format_datetime($submission->read_at) }}</span>
                        @endif
                    @else
                        <x-ui.badge tone="accent">{{ __('form-builder::forms.unread') }}</x-ui.badge>
                    @endif
                </x-ui.fact>

                <x-ui.fact :label="__('form-builder::forms.ip_address')">{{ $submission->ip_address ?? '—' }}</x-ui.fact>

                <x-ui.fact :label="__('form-builder::forms.user_agent')">
                    <span class="break-all font-normal">{{ $submission->user_agent ?? '—' }}</span>
                </x-ui.fact>
            </x-ui.facts>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
