@props([
    'target' => null,
    'id' => null,
    'type' => 'image',
    'returns' => 'path',
    'multiple' => false,
])

{{--
    Picking an image from the media library.

    One component for the core, the page editor, the blog and the gallery:
    load images, search, click one, write the path into a field.

    `target`   — id of the input that receives the value
    `returns`  — which field of the media item is written (path or url)
    `multiple` — multi-select with a confirm button (e.g. gallery). Instead
                 of filling a field, the dialog then dispatches the
                 `media-picker:select` event with detail.items.

    Every selection dispatches `media-picker:select`, the single one too —
    anyone who wants to do more than fill a field listens for it.

    The matching trigger is any element with
    data-media-picker-open="<id>".
--}}
@php($pickerId = $id ?? 'media-picker-'.Str::random(6))

<div
    id="{{ $pickerId }}"
    class="fixed inset-0 z-[9999] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $pickerId }}-title"
    data-media-picker
    @if($target) data-target="{{ $target }}" @endif
    @if($multiple) data-multiple data-selected-label="{{ __('translation.media.selected') }}" @endif
    data-type="{{ $type }}"
    data-returns="{{ $returns }}"
    data-url="{{ route('backend.media.api.list') }}"
>
    <div class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm" data-media-picker-close></div>

    <div class="relative flex max-h-[80vh] w-full max-w-3xl flex-col rounded-xl border border-line bg-surface shadow-[var(--shadow-popover)]">
        <div class="ui-card-head">
            <h2 id="{{ $pickerId }}-title" class="ui-card-title">{{ __('translation.blocks.media_library') }}</h2>

            <div class="flex items-center gap-2">
                <x-ui.input
                    icon="search"
                    type="search"
                    class="w-48"
                    data-media-picker-search
                    :placeholder="__('translation.blocks.search')"
                    :aria-label="__('translation.blocks.search')"
                />
                <x-ui.button variant="ghost" icon="x" :label="__('translation.close')" data-media-picker-close />
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="hidden justify-center py-10" data-media-picker-loading>
                <x-ui.icon name="loader-circle" class="animate-spin text-2xl text-fg-subtle" />
            </div>

            <div class="hidden" data-media-picker-empty>
                <x-ui.empty icon="image">{{ __('translation.blocks.no_images_found') }}</x-ui.empty>
            </div>

            <div class="hidden grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-2" data-media-picker-grid></div>
        </div>

        @if($multiple)
            <div class="ui-card-foot flex items-center justify-between gap-2">
                <span class="text-sm text-fg-muted" data-media-picker-count></span>

                <div class="flex gap-2">
                    <x-ui.button variant="secondary" size="md" data-media-picker-close>{{ __('translation.cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" size="md" icon="check" data-media-picker-confirm disabled>
                        {{ __('translation.media.add_selected') }}
                    </x-ui.button>
                </div>
            </div>
        @endif
    </div>
</div>
