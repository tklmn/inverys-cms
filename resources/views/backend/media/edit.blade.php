@php
    // Multilingual: original or translation of alt text and description
    $i18n = \App\Support\EditLocale::form($media);
@endphp
<x-app-layout>
    <x-slot name="header">{{ __('translation.media.edit_media') }}</x-slot>

    <x-ui.page :title="__('translation.media.edit_media')" :lead="$media->file_name">
        <x-slot:actions>
            <x-ui.locale-switch :model="$media" />
            <x-ui.button size="md" icon="eye" :href="route('backend.media.show', $media)">{{ __('translation.view') }}</x-ui.button>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.media.index')">{{ __('translation.back') }}</x-ui.button>
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Preview. self-start, otherwise the grid stretches the card to the
                 height of the form column and an empty area gapes below
                 the image. Sticky so it stays visible while scrolling. --}}
            <x-ui.card class="self-start lg:sticky lg:top-4 lg:col-span-1">
                <div class="flex items-center justify-center rounded-lg bg-surface-sunken p-3">
                    @if(str_contains($media->mime_type, 'image'))
                        <img src="{{ $media->url }}" alt="{{ $media->alt_text }}" class="max-h-80 max-w-full">
                    @elseif(str_contains($media->mime_type, 'video'))
                        <video controls class="max-w-full">
                            <source src="{{ $media->url }}" type="{{ $media->mime_type }}">
                            {{ __('translation.media.browser_not_support_video') }}
                        </video>
                    @elseif(str_contains($media->mime_type, 'audio'))
                        <audio controls class="w-full">
                            <source src="{{ $media->url }}" type="{{ $media->mime_type }}">
                            {{ __('translation.media.browser_not_support_audio') }}
                        </audio>
                    @else
                        <div class="flex flex-col items-center gap-3 py-6 text-center">
                            <x-ui.icon name="file" class="text-4xl text-fg-subtle" />
                            <x-ui.button icon="external-link" :href="$media->url" target="_blank" rel="noopener noreferrer">
                                {{ __('translation.media.view_file') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            <div class="flex flex-col gap-4 lg:col-span-2">
                <form action="{{ route('backend.media.update', $media) }}" method="POST" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    @if($i18n->locale)
                        <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
                    @endif

                    <x-ui.translation-note :form="$i18n" />

                    <x-ui.card :title="__('translation.media.media_details')" icon="info" tone="accent">
                        <div class="flex flex-col gap-4">
                            <x-ui.field name="name" :label="__('translation.media.name')">
                                <x-ui.input id="name" name="name" :value="old('name', $media->name)" :required="! $i18n->shared" :disabled="$i18n->shared" />
                            </x-ui.field>

                            <x-ui.field name="alt_text" :label="__('translation.media.alt_text')" :help="__('translation.media.alt_text_help')" optional>
                                <x-ui.input id="alt_text" name="alt_text" :value="$i18n->value('alt_text')" :placeholder="$i18n->placeholder('alt_text')" />
                            </x-ui.field>

                            <x-ui.field name="description" :label="__('translation.media.description')" optional>
                                <x-ui.textarea id="description" name="description" rows="4" :placeholder="$i18n->placeholder('description')">{{ $i18n->value('description') }}</x-ui.textarea>
                            </x-ui.field>
                        </div>

                        <x-slot:foot>
                            <div class="flex justify-end">
                                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                            </div>
                        </x-slot:foot>
                    </x-ui.card>
                </form>

                <x-ui.card :title="__('translation.media.file_info')" icon="file" tone="teal">
                    <x-ui.facts>
                        <x-ui.fact :label="__('translation.media.file_name')">{{ $media->file_name }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.file_type')">{{ $media->mime_type }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.file_size')">{{ $media->formatted_size }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.upload_date')">{{ format_datetime($media->created_at) }}</x-ui.fact>
                    </x-ui.facts>
                </x-ui.card>

                <x-ui.card :title="__('translation.media.usage')" icon="link" tone="violet" flush>
                    <x-ui.list>
                        @forelse($usages as $usage)
                            <x-ui.list-row :title="$usage['label']" icon="link" :href="$usage['url'] ?: null" />
                        @empty
                            <x-ui.empty icon="link">{{ __('translation.media.not_in_use') }}</x-ui.empty>
                        @endforelse
                    </x-ui.list>
                </x-ui.card>

                @permission('delete-media')
                    <div class="flex justify-end">
                        <form id="delete-form-media-edit-{{ $media->id }}" action="{{ route('backend.media.destroy', $media) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <x-ui.button
                                variant="danger"
                                size="md"
                                icon="trash-2"
                                data-delete-modal="{{ json_encode(['formId' => 'delete-form-media-edit-'.$media->id, 'subtitle' => (! empty($usages) ? __('translation.media.delete_warning_in_use', ['count' => count($usages)]) : __('This action cannot be undone.'))]) }}"
                            >{{ __('translation.delete') }}</x-ui.button>
                        </form>
                    </div>
                @endpermission
            </div>
        </div>
    </x-ui.page>
</x-app-layout>
