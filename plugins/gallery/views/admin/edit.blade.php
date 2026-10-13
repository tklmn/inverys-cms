<x-app-layout>
    <x-slot name="header">{{ __('gallery::gallery.edit_album') }}: {{ $album->name }}</x-slot>

    @php
        $albumSettings = $album->settings ?? [];

        // Multilingual: original or translation
        $i18n = \App\Support\EditLocale::form($album);
        $captions = json_decode($i18n->translated('captions') ?? '[]', true) ?: [];

        // Three states: use default, on, off — hence a select instead of a switch
        $triState = [
            'lightbox' => 'gallery::gallery.lightbox',
            'show_captions' => 'gallery::gallery.show_captions',
            'lazy_loading' => 'gallery::gallery.lazy_loading',
            'pagination' => 'gallery::gallery.pagination',
        ];

        // "Use default" names the value that then actually applies
        $defaultLabel = fn (string $value) => __('gallery::gallery.use_global_default').' ('.$value.')';
    @endphp

    <x-ui.page :title="__('gallery::gallery.edit_album')" :lead="$album->getOriginalValue('name')">
        <x-slot:actions>
            <x-ui.locale-switch :model="$album" />
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.gallery.index')">
                {{ __('gallery::gallery.albums') }}
            </x-ui.button>

            {{-- One save for details and settings; the form sits below --}}
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="gallery-album-form">
                {{ __('translation.save') }}
            </x-ui.button>
        </x-slot:actions>

        <form id="gallery-album-form" action="{{ route('backend.gallery.update', $album) }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')
            @if($i18n->locale)
                <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
            @endif

            <x-ui.translation-note :form="$i18n" />

            <x-ui.card :title="__('gallery::gallery.album_details')" icon="images">
                <div class="flex flex-col gap-4">
                    <div class="ui-grid">
                        <x-ui.field name="name" :label="__('gallery::gallery.album_name')">
                            <x-ui.input id="name" name="name" :value="$i18n->value('name')" :placeholder="$i18n->placeholder('name')" :required="! $i18n->shared" />
                        </x-ui.field>

                        <x-ui.field name="slug" :label="__('gallery::gallery.slug')">
                            <x-ui.input id="slug" name="slug" :value="old('slug', $album->slug)" :disabled="$i18n->shared" />
                        </x-ui.field>
                    </div>

                    <x-ui.field name="description" :label="__('gallery::gallery.description')" optional>
                        <x-ui.textarea id="description" name="description" rows="3" :placeholder="$i18n->placeholder('description')">{{ $i18n->value('description') }}</x-ui.textarea>
                    </x-ui.field>

                    <x-ui.switch-row
                        :disabled="$i18n->shared"
                        name="is_active"
                        :label="__('gallery::gallery.active')"
                        :checked="old('is_active', $album->is_active ? '1' : '0') == '1'"
                        on-value="1"
                        off-value="0"
                    />
                </div>
            </x-ui.card>

            @unless($i18n->locale)
            <x-ui.card :title="__('gallery::gallery.album_settings')" icon="sliders-horizontal" tone="teal">
                <p class="mb-4 text-sm text-fg-muted">{{ __('gallery::gallery.album_settings_hint') }}</p>

                <div class="ui-grid">
                    <x-ui.field name="settings_columns" :label="__('gallery::gallery.columns')">
                        <x-ui.select id="settings_columns" name="settings_columns">
                            <option value="">{{ $defaultLabel($globalDefaults['columns']) }}</option>
                            @foreach([2, 3, 4, 5, 6] as $col)
                                <option value="{{ $col }}" @selected(($albumSettings['columns'] ?? '') == (string) $col)>{{ $col }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="settings_gap" :label="__('gallery::gallery.gap')">
                        <x-ui.select id="settings_gap" name="settings_gap">
                            <option value="">{{ $defaultLabel(__('gallery::gallery.gap_' . $globalDefaults['gap'])) }}</option>
                            @foreach([2, 4, 6, 8] as $gapVal)
                                <option value="{{ $gapVal }}" @selected(($albumSettings['gap'] ?? '') == (string) $gapVal)>{{ __('gallery::gallery.gap_' . $gapVal) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="settings_aspect_ratio" :label="__('gallery::gallery.aspect_ratio')">
                        <x-ui.select id="settings_aspect_ratio" name="settings_aspect_ratio">
                            <option value="">{{ $defaultLabel(__('gallery::gallery.ratio_' . $globalDefaults['aspect_ratio'])) }}</option>
                            @foreach(['cover' => 'ratio_cover', 'contain' => 'ratio_contain', 'auto' => 'ratio_auto'] as $arVal => $arKey)
                                <option value="{{ $arVal }}" @selected(($albumSettings['aspect_ratio'] ?? '') === $arVal)>{{ __('gallery::gallery.' . $arKey) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="settings_thumbnail_size" :label="__('gallery::gallery.thumbnail_size')">
                        <x-ui.select id="settings_thumbnail_size" name="settings_thumbnail_size">
                            <option value="">{{ $defaultLabel(__('gallery::gallery.size_' . $globalDefaults['thumbnail_size'])) }}</option>
                            @foreach(['small' => 'size_small', 'medium' => 'size_medium', 'large' => 'size_large'] as $sVal => $sKey)
                                <option value="{{ $sVal }}" @selected(($albumSettings['thumbnail_size'] ?? '') === $sVal)>{{ __('gallery::gallery.' . $sKey) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @foreach($triState as $key => $labelKey)
                        <x-ui.field :name="'settings_'.$key" :label="__($labelKey)">
                            <x-ui.select :id="'settings_'.$key" :name="'settings_'.$key">
                                <option value="">{{ $defaultLabel((string) $globalDefaults[$key] === '1' ? __('gallery::gallery.enabled') : __('gallery::gallery.disabled')) }}</option>
                                <option value="1" @selected(($albumSettings[$key] ?? '') === '1')>{{ __('gallery::gallery.enabled') }}</option>
                                <option value="0" @selected(($albumSettings[$key] ?? '') === '0')>{{ __('gallery::gallery.disabled') }}</option>
                            </x-ui.select>
                        </x-ui.field>
                    @endforeach

                    <x-ui.field name="settings_images_per_page" :label="__('gallery::gallery.images_per_page')" :help="__('gallery::gallery.images_per_page_hint')">
                        <x-ui.input
                            id="settings_images_per_page"
                            name="settings_images_per_page"
                            type="number"
                            min="0"
                            max="200"
                            :value="$albumSettings['images_per_page'] ?? ''"
                            :placeholder="$defaultLabel($globalDefaults['images_per_page'])"
                        />
                    </x-ui.field>
                </div>
            </x-ui.card>
            @endunless

            @if($i18n->locale)
                {{-- Captions of the translation; the images themselves apply to all languages --}}
                <x-ui.card :title="__('gallery::gallery.captions_heading')" icon="images" tone="violet" flush>
                    <x-ui.list>
                        @forelse($album->images as $image)
                            <x-ui.list-row :title="$image->name">
                                <x-slot:lead>
                                    <img src="{{ $image->thumbnail_url }}" alt="" class="h-9 w-9 rounded-md object-cover">
                                </x-slot:lead>
                                <x-slot:cols>
                                    <div class="w-full min-w-64">
                                        <x-ui.input
                                            :name="'captions['.$image->id.']'"
                                            :value="old('captions.'.$image->id, $captions[(string) $image->id] ?? '')"
                                            :placeholder="$image->pivot->caption ?: __('gallery::gallery.caption_placeholder')"
                                            :aria-label="__('gallery::gallery.caption_placeholder')"
                                        />
                                    </div>
                                </x-slot:cols>
                            </x-ui.list-row>
                        @empty
                            <x-ui.empty icon="image">{{ __('gallery::gallery.no_images') }}</x-ui.empty>
                        @endforelse
                    </x-ui.list>
                </x-ui.card>
            @endif
        </form>

        {{-- Album images — original only; they apply to all languages --}}
        @unless($i18n->locale)
        <x-ui.card flush icon="image" tone="violet">
            <x-slot:title>{{ __('gallery::gallery.images') }}</x-slot:title>

            <x-slot:head>
                <x-ui.button variant="secondary" size="sm" icon="images" data-media-picker-open="gallery-picker">
                    {{ __('gallery::gallery.choose_from_library') }}
                </x-ui.button>

                {{-- A label so the click opens the hidden file dialog --}}
                <label class="ui-btn ui-btn-sm ui-btn-secondary cursor-pointer">
                    <x-ui.icon name="upload" />
                    {{ __('gallery::gallery.upload_images') }}
                    <input type="file" id="upload-images-input" multiple accept="image/*" class="sr-only">
                </label>
            </x-slot:head>

            <div class="ui-tiles" id="gallery-images-grid">
                @foreach($album->images as $image)
                    <x-ui.tile
                        class="gallery-image-item"
                        data-media-id="{{ $image->id }}"
                        :title="$image->name"
                        :image="$image->thumbnail_url"
                        :alt="$image->alt_text"
                    >
                        <x-slot:meta>
                            <span>{{ $image->formatted_size }}</span>
                        </x-slot:meta>

                        <input
                            type="text"
                            class="ui-tile-caption"
                            value="{{ $image->pivot->caption ?? '' }}"
                            placeholder="{{ __('gallery::gallery.caption_placeholder') }}"
                            aria-label="{{ __('gallery::gallery.caption_placeholder') }}"
                            data-caption="{{ $image->id }}"
                        >

                        <x-slot:actions>
                            <x-ui.button variant="ghost" size="xs" icon="grip-vertical" class="me-auto cursor-grab" :label="__('gallery::gallery.reorder')" data-drag-handle />
                            <x-ui.button variant="ghost" size="xs" icon="pencil" :label="__('translation.edit')" :href="route('backend.media.edit', $image)" />
                            <x-ui.button variant="danger" size="xs" icon="x" :label="__('gallery::gallery.remove_image')" data-remove-image="{{ $image->id }}" />
                        </x-slot:actions>
                    </x-ui.tile>
                @endforeach
            </div>

            <x-ui.empty icon="image" id="gallery-empty-state" @class(['hidden' => $album->images->isNotEmpty()])>
                {{ __('gallery::gallery.no_images') }}
            </x-ui.empty>
        </x-ui.card>
        @endunless
    </x-ui.page>

    @unless($i18n->locale)
    <x-ui.media-picker id="gallery-picker" multiple />

    @push('scripts')
    <script @cspNonce>
    document.addEventListener('DOMContentLoaded', function () {
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var grid = document.getElementById('gallery-images-grid');
        var emptyState = document.getElementById('gallery-empty-state');

        var urls = {
            upload: @json(route('backend.media.ajax-upload')),
            add: @json(route('backend.gallery.images.add', $album)),
            reorder: @json(route('backend.gallery.images.reorder', $album)),
            caption: @json(route('backend.gallery.images.caption', [$album, '__ID__'])),
            remove: @json(route('backend.gallery.images.remove', [$album, '__ID__'])),
        };

        var T = {
            reordered: @json(__('gallery::gallery.images_reordered')),
            captionSaved: @json(__('gallery::gallery.caption_saved')),
            removed: @json(__('gallery::gallery.image_removed')),
            confirmRemove: @json(__('gallery::gallery.confirm_remove_image')),
            uploadFailed: @json(__('gallery::gallery.upload_failed')),
            actionFailed: @json(__('gallery::gallery.action_failed')),
        };

        function fail(text) {
            toast(text || T.actionFailed, 'critical');
        }

        function send(method, url, body) {
            var headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' };
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
            return fetch(url, { method: method, headers: headers, body: body });
        }

        function toast(text, tone) {
            if (window.uiToast) window.uiToast(text, tone || 'positive');
        }

        function refreshEmpty() {
            emptyState.classList.toggle('hidden', !!grid.querySelector('.gallery-image-item'));
        }

        function addImages(ids) {
            if (!ids.length) return;
            send('POST', urls.add, { media_ids: ids }).then(function (res) {
                if (res.ok) location.reload();
                else fail();
            }).catch(function () { fail(); });
        }

        // Drag the handle to reorder
        if (window.Sortable) {
            window.Sortable.create(grid, {
                handle: '[data-drag-handle]',
                animation: 150,
                onEnd: function () {
                    var order = Array.prototype.map.call(grid.querySelectorAll('.gallery-image-item'), function (el) {
                        return parseInt(el.dataset.mediaId, 10);
                    });
                    send('POST', urls.reorder, { order: order }).then(function (res) {
                        if (res.ok) toast(T.reordered);
                        else fail();
                    }).catch(function () { fail(); });
                },
            });
        }

        // Remove an image from the album
        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove-image]');
            if (!btn) return;

            // Takes effect immediately, so ask first; the file stays in the media library
            if (!window.confirm(T.confirmRemove)) return;

            var item = btn.closest('.gallery-image-item');
            send('DELETE', urls.remove.replace('__ID__', btn.dataset.removeImage)).then(function (res) {
                if (!res.ok) {
                    fail();
                    return;
                }
                item.remove();
                refreshEmpty();
                toast(T.removed);
            }).catch(function () { fail(); });
        });

        // Save the caption shortly after typing stops
        var captionTimers = {};
        grid.addEventListener('input', function (e) {
            var input = e.target.closest('[data-caption]');
            if (!input) return;

            var id = input.dataset.caption;
            clearTimeout(captionTimers[id]);
            captionTimers[id] = setTimeout(function () {
                send('PUT', urls.caption.replace('__ID__', id), { caption: input.value }).then(function (res) {
                    if (res.ok) toast(T.captionSaved);
                    else fail();
                }).catch(function () { fail(); });
            }, 600);
        });

        // Selection from the media library
        document.getElementById('gallery-picker').addEventListener('media-picker:select', function (e) {
            addImages(e.detail.items.map(function (item) { return item.id; }));
        });

        // Upload new images and add them straight to the album
        document.getElementById('upload-images-input').addEventListener('change', function (e) {
            var files = Array.prototype.slice.call(e.target.files);
            if (!files.length) return;

            // Each file separately: a rejected file (size, type) reports its
            // name and reason, the rest still end up in the album
            Promise.all(files.map(function (file) {
                var fd = new FormData();
                fd.append('file', file);
                return send('POST', urls.upload, fd)
                    .then(function (r) { return r.json().catch(function () { return {}; }); })
                    .catch(function () { return {}; })
                    .then(function (r) {
                        if (!(r.success && r.id)) {
                            fail(T.uploadFailed.replace(':name', file.name) + (r.message ? ' ' + r.message : ''));
                        }
                        return r;
                    });
            })).then(function (results) {
                addImages(results.filter(function (r) { return r.success && r.id; }).map(function (r) { return r.id; }));
            });

            e.target.value = '';
        });
    });
    </script>
    @endpush
    @endunless
</x-app-layout>
