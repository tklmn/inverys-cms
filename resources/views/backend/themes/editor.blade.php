<x-app-layout>
    <x-slot name="header">
        {{ __('translation.theme_editor.title') }}: {{ __($theme['name']) }}
    </x-slot>

    <x-ui.page :title="__('translation.theme_editor.title').': '.__($theme['name'])">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.themes.index')">
                {{ __('Themes') }}
            </x-ui.button>
        </x-slot:actions>

        <div class="ui-card flex flex-col overflow-hidden md:h-[calc(100vh-14rem)] md:flex-row">
                {{-- File tree: narrow on top (limited height), from md on to the left --}}
                <div class="max-h-56 w-full shrink-0 overflow-y-auto border-b border-line md:max-h-none md:w-64 md:border-b-0 md:border-r">
                    <div class="p-3">
                        <h3 class="text-xs font-semibold text-fg-muted uppercase tracking-wider mb-3">
                            {{ __('translation.theme_editor.files') }}
                        </h3>
                        <div id="file-tree" class="text-sm">
                            @include('backend.themes.partials.file-tree', ['nodes' => $fileTree, 'depth' => 0])
                        </div>
                    </div>
                </div>

                {{-- Editor --}}
                <div class="relative flex min-h-[28rem] min-w-0 flex-1 flex-col">
                    {{-- Toolbar --}}
                    <div class="flex items-center justify-between px-4 py-2 border-b border-line bg-surface-sunken">
                        <span id="current-file-path" class="text-sm font-mono text-fg-muted truncate"></span>
                        <div class="flex items-center gap-2 shrink-0">
                            <span id="editor-status" class="text-xs text-fg-muted"></span>
                            <button id="reset-btn" disabled style="display:none"
                                    class="ui-btn ui-btn-sm ui-btn-secondary">
                                <x-ui.icon name="rotate-ccw" /> {{ __('translation.theme_editor.reset') }}
                            </button>
                            <button id="save-btn" disabled
                                    class="ui-btn ui-btn-sm ui-btn-primary">
                                <x-ui.icon name="save" /> {{ __('translation.save') ?? __('Save') }}
                            </button>
                        </div>
                    </div>

                    {{-- Textarea --}}
                    {{-- data-code-editor: CodeMirror replaces the field (ui/code-editor.js);
                         if the script does not load, it stays a plain textarea --}}
                    <textarea id="editor-textarea"
                              data-code-editor
                              data-language="text"
                              class="flex-1 w-full font-mono text-sm leading-relaxed p-4 bg-surface-sunken text-fg border-0 resize-none focus:ring-0 focus:outline-none"
                              disabled
                              spellcheck="false"
                              placeholder="{{ __('translation.theme_editor.select_file') }}"></textarea>

                    {{-- Empty state over the editor until a file is loaded — an empty
                         editor with line "1" would look like an error --}}
                    <div id="editor-empty" class="ui-editor-empty">
                        <span class="ui-empty-icon"><x-ui.icon name="file-code" /></span>
                        <p class="ui-empty-title">{{ __('translation.theme_editor.select_file') }}</p>
                        <p class="ui-empty-text">{{ __('translation.theme_editor.select_file_hint') }}</p>
                    </div>
                </div>
        </div>
    </x-ui.page>

    @php
        $editorTranslations = [
            'saving' => __('translation.theme_editor.saving'),
            'saved' => __('translation.theme_editor.saved'),
            'error' => __('translation.theme_editor.save_error'),
            'unsaved' => __('translation.theme_editor.unsaved_changes'),
            'reset_confirm' => __('translation.theme_editor.reset_confirm'),
        ];
    @endphp

    @push('scripts')
    <script @cspNonce>
    (function() {
        var loadUrl = @json(route('backend.themes.editor.load', $theme['slug']));
        var saveUrl = @json(route('backend.themes.editor.save', $theme['slug']));
        var resetUrl = @json(route('backend.themes.editor.reset', $theme['slug']));
        var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        var T = @json($editorTranslations);

        var textarea = document.getElementById('editor-textarea');
        var saveBtn = document.getElementById('save-btn');
        var resetBtn = document.getElementById('reset-btn');
        var pathDisplay = document.getElementById('current-file-path');
        var statusBar = document.getElementById('editor-status');
        var emptyState = document.getElementById('editor-empty');
        var currentPath = null;
        var currentLanguage = 'text';
        var isDirty = false;

        // Set content — via the code editor if it is loaded, otherwise directly
        function setContent(content, language) {
            if (textarea.codeEditor) {
                textarea.dispatchEvent(new CustomEvent('code-editor:load', {
                    detail: { content: content, language: language, disabled: false }
                }));
            } else {
                textarea.value = content;
                textarea.disabled = false;
            }
        }

        // Ctrl/Cmd+S from the code editor
        textarea.addEventListener('code-editor:save', function() {
            if (!saveBtn.disabled) saveBtn.click();
        });

        // File tree: click to load
        document.querySelectorAll('[data-file-path]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                if (isDirty && !confirm(T.unsaved)) return;
                loadFile(this.dataset.filePath);
                document.querySelectorAll('[data-file-path]').forEach(function(n) {
                    n.classList.remove('bg-accent-soft', 'text-accent');
                });
                this.classList.add('bg-accent-soft', 'text-accent');
            });
        });

        // Folder toggle
        document.querySelectorAll('[data-folder-toggle]').forEach(function(el) {
            el.addEventListener('click', function() {
                var children = this.nextElementSibling;
                if (children) children.classList.toggle('hidden');
                // Open/closed: both SVGs are in the markup, this only toggles them
                this.querySelectorAll('.folder-icon').forEach(function(icon) {
                    icon.classList.toggle('hidden');
                });
            });
        });

        function loadFile(path) {
            statusBar.textContent = '';
            fetch(loadUrl + '?path=' + encodeURIComponent(path), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    statusBar.textContent = data.error;
                    return;
                }
                currentLanguage = data.language || 'text';
                setContent(data.content, currentLanguage);
                if (emptyState) emptyState.hidden = true;
                saveBtn.disabled = false;
                currentPath = path;
                pathDisplay.textContent = path;
                isDirty = false;
                // Show reset button only if an .orig exists
                if (data.has_original) {
                    resetBtn.style.display = '';
                    resetBtn.disabled = false;
                } else {
                    resetBtn.style.display = 'none';
                }
                statusBar.textContent = data.language || '';
            })
            .catch(function(e) {
                statusBar.textContent = T.error;
            });
        }

        textarea.addEventListener('input', function() { isDirty = true; });

        saveBtn.addEventListener('click', function() {
            if (!currentPath) return;
            statusBar.textContent = T.saving;
            saveBtn.disabled = true;
            fetch(saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ path: currentPath, content: textarea.value })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                statusBar.textContent = data.ok ? T.saved : (data.error || T.error);
                saveBtn.disabled = false;
                if (data.ok) isDirty = false;
            })
            .catch(function() {
                statusBar.textContent = T.error;
                saveBtn.disabled = false;
            });
        });

        // Ctrl+S / Cmd+S to save
        textarea.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (!saveBtn.disabled) saveBtn.click();
            }
            // Tab inserts 4 spaces
            if (e.key === 'Tab') {
                e.preventDefault();
                var start = this.selectionStart;
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(this.selectionEnd);
                this.selectionStart = this.selectionEnd = start + 4;
                isDirty = true;
            }
        });

        // Reset handler
        resetBtn.addEventListener('click', function() {
            if (!currentPath || !confirm(T.reset_confirm)) return;
            resetBtn.disabled = true;
            fetch(resetUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ path: currentPath })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    setContent(data.content, currentLanguage);
                    isDirty = false;
                    statusBar.textContent = data.message;
                    resetBtn.style.display = 'none';
                } else {
                    statusBar.textContent = data.error || T.error;
                }
                resetBtn.disabled = false;
            })
            .catch(function() {
                statusBar.textContent = T.error;
                resetBtn.disabled = false;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (isDirty) { e.preventDefault(); e.returnValue = ''; }
        });
    })();
    </script>
    @endpush
</x-app-layout>
