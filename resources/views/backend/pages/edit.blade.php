{{--
    Page editor (full screen).

    ┌ Bar: back · title/status · preview width · language · view · save
    ├ Structure (left): the page's blocks, adding via the dialog with search
    ├ Preview (centre): the real page in the theme (PageController::canvas)
    └ Sidebar (right): block · page · revisions

    Blocks save themselves (saveBlocks), then the preview reloads.
    "Save" saves the page properties in the "Page" tab.
    When narrow (below lg) only one area is shown at a time, switchable at the top.

    Logic: resources/js/backend/pages/editor.js, blocks: page-builder.js (mode "editor")
--}}
<x-editor-layout>
    @php
        /*
         * Multilingual: $editLocale = translation being edited, null = original.
         * Translatable fields then show the translation (empty = original applies,
         * the original is shown as placeholder); shared fields are locked.
         */
        $editLocale = \App\Support\EditLocale::current();
        $tr = $editLocale ? $page->translationsFor($editLocale) : [];
        $val = fn (string $field) => $editLocale ? old($field, $tr[$field] ?? '') : old($field, $page->getOriginalValue($field));
        $ph = fn (string $field) => $editLocale ? (string) $page->getOriginalValue($field) : null;
        $shared = $editLocale !== null;
        $locales = app(\App\Support\Locales::class);

        // Blocks: translation or original; after an error the ones entered.
        // No translation yet: the original's blocks as the starting point —
        // the website shows them anyway, they are only saved on a change
        $originalBlocks = $page->getOriginalValue('blocks') ?? [];
        $blocks = $editLocale ? (json_decode($tr['blocks'] ?? '[]', true) ?: $originalBlocks) : $originalBlocks;
        if (is_string(old('blocks'))) {
            $blocks = json_decode(old('blocks'), true) ?: $blocks;
        }
        $langQuery = $editLocale ? '?lang='.$editLocale : '';

        $builderConfig = [
            'mode' => 'editor',
            'blocks' => $blocks,
            'defaultBlocks' => $editLocale ? ($page->getOriginalValue('blocks') ?: null) : null,
            // Translation without its own blocks: only send them after a change
            'pristine' => $editLocale !== null && empty(json_decode($tr['blocks'] ?? '[]', true)),
            'saveUrl' => route('backend.pages.save-blocks', $page).$langQuery,
            'previewUrl' => route('backend.pages.preview-blocks'),
            'inputForm' => 'page-form',
            'settingsHost' => '[data-pb-settings]',
            'csrf' => csrf_token(),
            'mediaUrl' => route('backend.media.index'),
            't' => \App\Support\BuilderTexts::all($editLocale !== null),
            'icons' => \App\Support\ContentIcons::NAMES,
        ];

        // Sections of the page settings; sections with errors are open
        $sectionFields = [
            'basic' => ['title', 'subtitle', 'slug', 'parent_id', 'is_active', 'hide_in_menu', 'is_root'],
            'navigation' => ['nav_title', 'external_url', 'external_url_new_window'],
            'seo' => ['meta_title', 'meta_description', 'meta_robots', 'meta_author', 'aria_label', 'include_in_search'],
            'design' => ['template', 'header_image', 'extra_css'],
            'categories' => ['categories'],
        ];
        $errorSections = array_keys(array_filter($sectionFields, fn ($fields) => collect($fields)->contains(fn ($field) => $errors->has($field) || $errors->has($field.'.*'))));
        $open = fn (string $section) => $section === 'basic' || in_array($section, $errorSections, true);

        $isOnline = (bool) $page->is_active;
        $publicUrl = $page->is_root ? route('home') : route('page.show', $page->slug);
        $currentHomepage = \App\Models\Page::where('is_root', true)->where('id', '!=', $page->id)->first();
    @endphp

    <div
        class="pe-shell"
        data-page-editor
        data-page-id="{{ $page->id }}"
        data-canvas-url="{{ route('backend.pages.canvas', $page).$langQuery }}"
        data-pane="canvas"
        data-t-saving="{{ __('translation.pages.editor.saving') }}"
        data-t-saved="{{ __('translation.pages.editor.saved') }}"
        data-t-save-error="{{ __('translation.pages.editor.save_failed') }}"
        data-t-unsaved="{{ __('translation.pages.editor.unsaved') }}"
        data-t-session-expired="{{ __('translation.pages.editor.session_expired') }}"
        @if($errorSections) data-open-side="page" @endif
    >
        {{-- Bar --}}
        <header class="pe-bar">
            <div class="pe-bar-start">
                <x-ui.button variant="ghost" icon="arrow-left" :href="route('backend.pages.index')" :label="__('translation.pages.editor.back')" />
                <div class="min-w-0">
                    <p class="pe-title" title="{{ $page->getOriginalValue('title') }}">{{ $page->getOriginalValue('title') }}</p>
                    <p class="pe-subtitle">
                        <x-ui.badge :tone="$isOnline ? 'positive' : 'neutral'">{{ $isOnline ? __('translation.pages.editor.status_online') : __('translation.pages.editor.status_draft') }}</x-ui.badge>
                        <span class="pe-save-status" data-save-status aria-live="polite"></span>
                    </p>
                </div>
            </div>

            <div class="pe-bar-center" role="group" aria-label="{{ __('translation.pages.editor.devices') }}">
                <div class="ui-segment">
                    @foreach(['desktop' => 'monitor', 'tablet' => 'tablet', 'mobile' => 'smartphone'] as $device => $deviceIcon)
                        <button type="button" class="ui-segment-btn" data-device-btn="{{ $device }}" aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}" title="{{ __('translation.pages.editor.device_'.$device) }}">
                            <x-ui.icon :name="$deviceIcon" /><span class="sr-only">{{ __('translation.pages.editor.device_'.$device) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="pe-bar-end">
                <div class="flex items-center">
                    <x-ui.button variant="ghost" icon="undo-2" data-history="undo" disabled :label="__('translation.pages.editor.undo')" />
                    <x-ui.button variant="ghost" icon="redo-2" data-history="redo" disabled :label="__('translation.pages.editor.redo')" />
                    <x-ui.button variant="ghost" icon="keyboard" class="max-lg:hidden" data-open-shortcuts :label="__('translation.pages.editor.shortcuts')" />
                </div>
                <x-ui.locale-switch :model="$page" />
                <x-ui.button variant="ghost" icon="panel-left" class="max-lg:hidden" data-toggle-panel="structure" :label="__('translation.pages.editor.toggle_structure')" aria-pressed="true" />
                <x-ui.button variant="ghost" icon="panel-right" class="max-lg:hidden" data-toggle-panel="side" :label="__('translation.pages.editor.toggle_side')" aria-pressed="true" />
                @if($isOnline)
                    <x-ui.button size="md" icon="external-link" :href="$publicUrl" target="_blank" rel="noopener" class="max-sm:hidden">{{ __('translation.pages.editor.view_page') }}</x-ui.button>
                @else
                    <x-ui.button size="md" icon="external-link" disabled class="max-sm:hidden" :disabled-reason="__('translation.pages.editor.view_offline')">{{ __('translation.pages.editor.view_page') }}</x-ui.button>
                @endif
                <x-ui.button variant="primary" size="md" icon="check" type="submit" form="page-form" data-save-page>{{ __('translation.save') }}</x-ui.button>
            </div>
        </header>

        {{-- Narrow: one area at a time --}}
        <div class="pe-panes" role="group" aria-label="{{ __('translation.pages.editor.panes') }}">
            <div class="ui-segment w-full">
                <button type="button" class="ui-segment-btn flex-1" data-pane-btn="structure" aria-pressed="false"><x-ui.icon name="list-tree" /> {{ __('translation.pages.editor.structure') }}</button>
                <button type="button" class="ui-segment-btn flex-1" data-pane-btn="canvas" aria-pressed="true"><x-ui.icon name="eye" /> {{ __('translation.pages.editor.preview') }}</button>
                <button type="button" class="ui-segment-btn flex-1" data-pane-btn="side" aria-pressed="false"><x-ui.icon name="sliders-horizontal" /> {{ __('translation.pages.editor.settings') }}</button>
            </div>
        </div>

        <div class="pe-body">
            {{-- Structure --}}
            <aside class="pe-structure" data-pane-el="structure" aria-label="{{ __('translation.pages.editor.structure') }}">
                @if($editLocale)
                    <x-ui.note tone="accent" icon="globe" class="m-3" :title="__('translation.locales.translating_title', ['language' => $locales->native($editLocale)])">
                        {{ __('translation.locales.translating_text', ['original' => $locales->native($locales->base())]) }}
                    </x-ui.note>
                    <x-ui.translation-source :model="$page" :locale="$editLocale" class="m-3 mt-0" />
                @endif
                <div data-page-builder='@json($builderConfig)'></div>
            </aside>

            {{-- Preview --}}
            <main class="pe-canvas" id="editor-main" data-pane-el="canvas">
                <div class="pe-frame" data-device="desktop" data-canvas-frame>
                    <iframe title="{{ __('translation.pages.editor.preview') }}" data-canvas-iframe tabindex="-1"></iframe>
                    <iframe title="{{ __('translation.pages.editor.preview') }}" data-canvas-iframe tabindex="-1" hidden></iframe>
                    <div class="pe-frame-loading" data-canvas-loading><x-ui.icon name="loader-circle" class="animate-spin" /> {{ __('translation.pages.editor.loading') }}</div>
                </div>
            </main>

            {{-- Sidebar --}}
            <aside class="pe-side" data-pane-el="side" aria-label="{{ __('translation.pages.editor.settings') }}">
                <div class="ui-tabs pe-side-tabs" role="tablist">
                    <ul class="contents">
                        <li><button type="button" class="ui-tab" role="tab" data-side-tab="block" aria-selected="true"><x-ui.icon name="box" />{{ __('translation.pages.editor.tab_block') }}</button></li>
                        <li><button type="button" class="ui-tab" role="tab" data-side-tab="page" aria-selected="false" @if($errorSections) data-has-error title="{{ __('translation.pages.tab_has_errors') }}" @endif><x-ui.icon name="file-text" />{{ __('translation.pages.editor.tab_page') }}</button></li>
                        @unless($editLocale)
                            <li><button type="button" class="ui-tab" role="tab" data-side-tab="revisions" data-tab="revisions" aria-selected="false"><x-ui.icon name="history" />{{ __('translation.pages.revisions.title') }}</button></li>
                        @endunless
                    </ul>
                </div>

                <div class="pe-side-body">
                    {{-- Settings of the selected block (page-builder.js) --}}
                    <div data-side-panel="block" data-pb-settings></div>

                    {{-- Page properties --}}
                    <div data-side-panel="page" hidden>
                        <form id="page-form" action="{{ route('backend.pages.update', $page) }}" method="POST" class="pe-form" data-page-form>
                            @csrf
                            @method('PUT')
                            @if($editLocale)
                                <input type="hidden" name="_lang" value="{{ $editLocale }}">
                                <x-ui.note tone="neutral" icon="lock">{{ __('translation.locales.shared') }}</x-ui.note>
                            @endif

                            <x-ui.flash />

                            <details class="pe-section" @if($open('basic')) open @endif>
                                <summary class="pe-section-head"><x-ui.icon name="file-text" />{{ __('translation.pages.basic_info') }}@if(in_array('basic', $errorSections, true))<span class="pe-error-dot" aria-hidden="true"></span>@endif</summary>
                                <div class="pe-section-body">
                                    <x-ui.field name="title" :label="__('translation.pages.field_title')">
                                        <x-ui.input id="title" name="title" type="text" :value="$val('title')" :placeholder="$ph('title')" :required="! $editLocale" />
                                    </x-ui.field>
                                    <x-ui.field name="subtitle" :label="__('translation.pages.subtitle')">
                                        <x-ui.input id="subtitle" name="subtitle" type="text" :value="$val('subtitle')" :placeholder="$ph('subtitle')" />
                                    </x-ui.field>
                                    {{-- Slug: when editing it stays fixed until it is changed deliberately (ui/slug-field.js) --}}
                                    <x-ui.field name="slug" :label="__('translation.pages.slug')" :help="__('translation.pages.slug_help_edit')">
                                        <div class="flex flex-wrap gap-2" data-slug-field data-slug-source="title">
                                            <input type="hidden" name="edit_slug" value="0" data-slug-flag>
                                            <x-ui.input id="slug" name="slug" :value="$val('slug')" :placeholder="$ph('slug')" readonly data-slug-input class="min-w-40 flex-1" />
                                            <x-ui.button size="md" variant="ghost" icon="pencil" data-slug-edit :label="__('translation.pages.edit_slug')" />
                                            <x-ui.button size="md" variant="ghost" icon="rotate-ccw" data-slug-generate hidden :label="__('translation.pages.auto_slug')" />
                                        </div>
                                    </x-ui.field>
                                    <x-ui.field name="parent_id" :label="__('translation.pages.parent')">
                                        <x-ui.select id="parent_id" name="parent_id" :disabled="$shared">
                                            @foreach($parentOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('parent_id', $page->parent_id) == $value)>{{ $label }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </x-ui.field>
                                    <div class="flex flex-col gap-1">
                                        <x-ui.switch-row :disabled="$shared" name="is_active" :label="__('translation.pages.is_active')" :hint="__('translation.pages.is_active_hint')" :checked="(bool) old('is_active', $page->is_active)" on-value="1" off-value="0" />
                                        <x-ui.switch-row :disabled="$shared" name="hide_in_menu" :label="__('translation.pages.hide_in_menu')" :hint="__('translation.pages.hide_in_menu_hint')" :checked="(bool) old('hide_in_menu', $page->hide_in_menu)" on-value="1" off-value="0" />
                                        <x-ui.switch-row :disabled="$shared" name="is_root" :label="__('translation.pages.is_root')" :hint="$currentHomepage && ! $page->is_root ? __('translation.pages.homepage_warning', ['page' => $currentHomepage->title]) : null" :checked="(bool) old('is_root', $page->is_root)" on-value="1" off-value="0" />
                                    </div>
                                </div>
                            </details>

                            <details class="pe-section" @if($open('navigation')) open @endif>
                                <summary class="pe-section-head"><x-ui.icon name="link" />{{ __('translation.pages.navigation') }}@if(in_array('navigation', $errorSections, true))<span class="pe-error-dot" aria-hidden="true"></span>@endif</summary>
                                <div class="pe-section-body">
                                    <x-ui.field name="nav_title" :label="__('translation.pages.nav_title')">
                                        <x-ui.input id="nav_title" name="nav_title" type="text" :value="$val('nav_title')" :placeholder="$ph('nav_title')" />
                                    </x-ui.field>
                                    <x-ui.field name="external_url" :label="__('translation.pages.external_url')" :help="__('translation.pages.external_url_help')">
                                        <x-ui.input id="external_url" name="external_url" type="text" inputmode="url" :placeholder="__('translation.pages.external_url_placeholder')" :value="old('external_url', $page->getRawOriginal('external_url'))" :disabled="$shared" />
                                    </x-ui.field>
                                    <x-ui.switch-row :disabled="$shared" name="external_url_new_window" :label="__('translation.pages.external_url_new_window')" :checked="(bool) old('external_url_new_window', $page->external_url_new_window)" on-value="1" off-value="0" input-id="external_url_new_window" />
                                </div>
                            </details>

                            <details class="pe-section" @if($open('seo')) open @endif>
                                <summary class="pe-section-head"><x-ui.icon name="search" />{{ __('translation.pages.seo') }}@if(in_array('seo', $errorSections, true))<span class="pe-error-dot" aria-hidden="true"></span>@endif</summary>
                                <div class="pe-section-body">
                                    <x-ui.field name="meta_title" :label="__('translation.pages.meta_title')">
                                        <x-ui.input id="meta_title" name="meta_title" type="text" data-count-max="60" :value="$val('meta_title')" :placeholder="$ph('meta_title')" />
                                    </x-ui.field>
                                    <x-ui.field name="meta_description" :label="__('translation.pages.meta_description')">
                                        <x-ui.textarea id="meta_description" data-count-max="160" name="meta_description" rows="3" :placeholder="$ph('meta_description')">{{ $val('meta_description') }}</x-ui.textarea>
                                    </x-ui.field>
                                    <x-ui.field name="meta_robots" :label="__('translation.pages.meta_robots')">
                                        @php
                                            $robotsValue = old('meta_robots', $page->meta_robots ?? 'index, follow');
                                            $robotsOptions = ['index, follow' => 'robots_index_follow', 'noindex, follow' => 'robots_noindex_follow', 'index, nofollow' => 'robots_index_nofollow', 'noindex, nofollow' => 'robots_noindex_nofollow'];
                                        @endphp
                                        <x-ui.select id="meta_robots" name="meta_robots" :disabled="$shared">
                                            @foreach($robotsOptions as $robotsOption => $robotsKey)
                                                <option value="{{ $robotsOption }}" @selected($robotsValue === $robotsOption)>{{ __('translation.pages.'.$robotsKey) }}</option>
                                            @endforeach
                                            {{-- A custom value from older pages stays selectable --}}
                                            @unless(array_key_exists($robotsValue, $robotsOptions))
                                                <option value="{{ $robotsValue }}" selected>{{ $robotsValue }}</option>
                                            @endunless
                                        </x-ui.select>
                                    </x-ui.field>
                                    <x-ui.field name="meta_author" :label="__('translation.pages.meta_author')">
                                        <x-ui.input id="meta_author" name="meta_author" type="text" :value="old('meta_author', $page->meta_author)" :disabled="$shared" />
                                    </x-ui.field>
                                    <x-ui.field name="aria_label" :label="__('translation.pages.aria_label')">
                                        <x-ui.input id="aria_label" name="aria_label" type="text" :value="$val('aria_label')" :placeholder="$ph('aria_label')" />
                                    </x-ui.field>
                                    <x-ui.switch-row :disabled="$shared" name="include_in_search" :label="__('translation.pages.include_in_search')" :checked="(bool) old('include_in_search', $page->include_in_search)" on-value="1" off-value="0" input-id="include_in_search" />
                                </div>
                            </details>

                            <details class="pe-section" @if($open('design')) open @endif>
                                <summary class="pe-section-head"><x-ui.icon name="palette" />{{ __('translation.pages.design') }}@if(in_array('design', $errorSections, true))<span class="pe-error-dot" aria-hidden="true"></span>@endif</summary>
                                <div class="pe-section-body">
                                    <x-ui.field name="template" :label="__('translation.pages.template')">
                                        <x-ui.select id="template" name="template" :disabled="$shared">
                                            <option value="container" @selected(old('template', $page->template) == 'container')>{{ __('translation.pages.template_container') }}</option>
                                            <option value="full_width" @selected(old('template', $page->template) == 'full_width')>{{ __('translation.pages.template_full_width') }}</option>
                                        </x-ui.select>
                                    </x-ui.field>
                                    <x-ui.field name="header_image" :label="__('translation.pages.header_image')">
                                        <x-ui.image-field name="header_image" id="header_image" :value="old('header_image', $page->header_image)" picker="header-media" :placeholder="__('translation.pages.header_image_placeholder')" :disabled="$shared" />
                                    </x-ui.field>
                                    <x-ui.field name="extra_css" :label="__('translation.pages.extra_css')" optional>
                                        <x-ui.textarea id="extra_css" name="extra_css" rows="4" class="font-mono text-xs" :disabled="$shared">{{ old('extra_css', $page->extra_css) }}</x-ui.textarea>
                                    </x-ui.field>
                                </div>
                            </details>

                            <details class="pe-section" @if($open('categories')) open @endif>
                                <summary class="pe-section-head"><x-ui.icon name="tag" />{{ __('translation.pages.categories') }}</summary>
                                <div class="pe-section-body">
                                    @if($categories->isEmpty())
                                        <p class="ui-help">{{ __('translation.pages.no_categories_text') }}</p>
                                        <x-ui.button size="sm" icon="plus" :href="route('backend.page-categories.create')">{{ __('translation.pages.add_category') }}</x-ui.button>
                                    @else
                                        <div class="ui-choices">
                                            @foreach($categories as $category)
                                                <x-ui.switch-row
                                                    name="categories[]"
                                                    multiple
                                                    :bleed="false"
                                                    :on-value="$category->id"
                                                    :label="$category->name"
                                                    :checked="in_array($category->id, array_map('intval', (array) old('categories', $selectedCategories ?? [])), true)"
                                                    :disabled="$shared"
                                                />
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </details>

                            <div class="pe-form-foot">
                                <x-ui.button variant="primary" size="md" icon="check" type="submit" class="w-full justify-center">{{ __('translation.save') }}</x-ui.button>
                            </div>
                        </form>
                    </div>

                    @unless($editLocale)
                        {{-- Revisions: separate forms, hence outside #page-form --}}
                        <div data-side-panel="revisions" class="p-3" hidden>
                            @include('backend.partials.revisions', ['revisionable' => $page, 'revisionType' => 'page'])
                        </div>
                    @endunless
                </div>
            </aside>
        </div>
    </div>

    {{-- Header image from the media library --}}
    <x-ui.media-picker id="header-media" target="header_image" returns="storage_path" />

    {{-- Keyboard shortcuts ("?" key or button in the bar) --}}
    @php
        $shortcuts = [
            ['Ctrl', 'Z', 'undo'], ['Ctrl', 'Shift', 'Z', 'redo'], ['Ctrl', 'S', 'save'], ['Ctrl', 'D', 'duplicate'],
            ['Del', 'delete'], ['Alt', '↑', 'move_up'], ['Alt', '↓', 'move_down'], ['Esc', 'deselect'], ['?', 'help'],
        ];
    @endphp
    <dialog id="pe-shortcuts" class="pe-shortcuts" aria-labelledby="pe-shortcuts-title">
        <div class="pb-inserter-head">
            <h2 class="ui-card-title" id="pe-shortcuts-title">{{ __('translation.pages.editor.shortcuts') }}</h2>
            <x-ui.button variant="ghost" icon="x" data-close-shortcuts :label="__('translation.close')" />
        </div>
        <div class="pb-inserter-body">
            <dl class="pe-shortcut-list">
                @foreach($shortcuts as $shortcut)
                    @php($action = array_pop($shortcut))
                    <div class="pe-shortcut">
                        <dt>{{ __('translation.pages.editor.shortcut_'.$action) }}</dt>
                        <dd>@foreach($shortcut as $key)<kbd>{{ $key === 'Ctrl' ? __('translation.pages.editor.key_ctrl') : ($key === 'Del' ? __('translation.pages.editor.key_del') : $key) }}</kbd>@endforeach</dd>
                    </div>
                @endforeach
            </dl>
            <p class="ui-help mt-4">{{ __('translation.pages.editor.shortcuts_hint') }}</p>
        </div>
    </dialog>
</x-editor-layout>
