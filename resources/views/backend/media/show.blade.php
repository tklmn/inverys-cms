<x-app-layout>
    <x-slot name="header">{{ __('translation.media.media_details') }}</x-slot>

    <x-ui.page :title="$media->name" :lead="$media->file_name">
        <x-slot:actions>
            @permission('edit-media')
                <x-ui.button variant="primary" size="md" icon="pencil" :href="route('backend.media.edit', $media)">
                    {{ __('translation.edit') }}
                </x-ui.button>
            @endpermission
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.media.index')">{{ __('translation.back') }}</x-ui.button>
        </x-slot:actions>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Preview --}}
            <x-ui.card class="self-start lg:col-span-1 lg:sticky lg:top-4">
                <div class="ui-media-stage">
                    @if(str_contains($media->mime_type, 'image'))
                        <a href="{{ $media->url }}" target="_blank" rel="noopener noreferrer" class="block w-full">
                            <img src="{{ $media->url }}" alt="{{ $media->alt_text }}" class="h-auto max-h-80 w-full object-contain">
                        </a>
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
                            <x-ui.button
                                icon="{{ $media->type === 'pdf' ? 'eye' : 'download' }}"
                                :href="$media->url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $media->type === 'pdf' ? __('translation.media.view_pdf') : __('translation.media.download_file') }}
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </x-ui.card>

            {{-- Details --}}
            <div class="flex flex-col gap-4 lg:col-span-2">
                <x-ui.card :title="__('translation.media.media_details')" icon="info" tone="accent">
                    <x-ui.facts>
                        <x-ui.fact :label="__('translation.media.file_name')">{{ $media->file_name }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.file_type')">{{ $media->mime_type }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.file_size')">{{ $media->formatted_size }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.uploaded_by')">{{ $media->user->name ?? '—' }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.upload_date')">{{ format_datetime($media->created_at) }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.last_modified')">{{ format_datetime($media->updated_at) }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.media.alt_text')">
                            @if($media->is_decorative)
                                {{ __('translation.media.is_decorative') }}
                            @elseif($media->needsAltText())
                                <x-ui.badge tone="caution">{{ __('translation.media.alt_missing') }}</x-ui.badge>
                            @else
                                {{ $media->alt_text ?: '—' }}
                            @endif
                        </x-ui.fact>
                        <x-ui.fact :label="__('translation.media.description')">{{ $media->description ?: '—' }}</x-ui.fact>
                        @if(str_starts_with((string) $media->mime_type, 'image/'))
                            <x-ui.fact :label="__('translation.media.focal_point')">
                                {{ $media->hasFocalPoint()
                                    ? __('translation.media.focal_position', ['x' => round((float) ($media->focal_x ?? 50), 1), 'y' => round((float) ($media->focal_y ?? 50), 1)])
                                    : __('translation.media.focal_center') }}
                            </x-ui.fact>
                        @endif
                    </x-ui.facts>
                </x-ui.card>

                <x-ui.card :title="__('translation.media.direct_url')" icon="external-link" tone="teal">
                    <div class="flex items-center gap-2">
                        <x-ui.input type="text" value="{{ $media->url }}" readonly class="font-mono text-xs" data-csp="media-url-input" />
                        <x-ui.button icon="copy" data-csp="media-url-copy">{{ __('translation.media.copy') }}</x-ui.button>
                    </div>
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
                        <form id="delete-form-media-{{ $media->id }}" action="{{ route('backend.media.destroy', $media) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <x-ui.button
                                variant="danger"
                                size="md"
                                icon="trash-2"
                                data-delete-modal="{{ json_encode(['formId' => 'delete-form-media-'.$media->id, 'subtitle' => (! empty($usages) ? __('translation.media.delete_warning_in_use', ['count' => count($usages)]) : __('This action cannot be undone.'))]) }}"
                            >{{ __('translation.delete') }}</x-ui.button>
                        </form>
                    </div>
                @endpermission
            </div>
        </div>
    </x-ui.page>

    <script @cspNonce>
        document.querySelectorAll('[data-csp="media-url-input"]').forEach(function (el) {
            el.addEventListener('click', function () { el.select(); });
        });

        document.querySelectorAll('[data-csp="media-url-copy"]').forEach(function (el) {
            el.addEventListener('click', function () {
                navigator.clipboard.writeText(@json($media->url));
                var original = el.textContent;
                el.textContent = @json(__('translation.media.copied'));
                setTimeout(function () { el.textContent = original; }, 2000);
            });
        });
    </script>
</x-app-layout>
