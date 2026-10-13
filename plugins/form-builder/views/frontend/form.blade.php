@php
    $formSlug = $form->slug;
    $successMsg = session('form_success_' . $formSlug);
    $showConfirmation = !empty($form->settings['show_confirmation']);
@endphp

<div class="form-builder-form {{ $form->settings['css_class'] ?? '' }}" id="form-wrap-{{ $formSlug }}">
    @if($successMsg)
        {{-- Success state — form is hidden --}}
        <div class="form-builder-success site-alert site-alert-success" role="status">
            <span class="site-alert-icon"><x-ui.icon name="circle-check" /></span>
            <div class="site-alert-body">
                <p class="site-alert-title">{{ __('form-builder::forms.submission_success') }}</p>
                <p class="site-alert-text">{{ $successMsg }}</p>
            </div>
        </div>
    @else
        @if(session('form_error_' . $formSlug))
            <div class="site-alert site-alert-danger mb-5" role="alert">
                <span class="site-alert-icon"><x-ui.icon name="circle-x" /></span>
                <div class="site-alert-body"><p class="site-alert-text !mt-0">{{ session('form_error_' . $formSlug) }}</p></div>
            </div>
        @endif

        @if($errors->any())
            <div class="site-alert site-alert-danger mb-5" role="alert">
                <span class="site-alert-icon"><x-ui.icon name="circle-alert" /></span>
                <ul class="site-alert-body site-alert-text !mt-0 list-disc ps-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Confirmation preview (hidden by default, shown via JS) --}}
        @if($showConfirmation)
        <div id="form-confirm-{{ $formSlug }}" class="hidden">
            <div class="site-alert mb-5">
                <span class="site-alert-icon"><x-ui.icon name="info" /></span>
                <div class="site-alert-body">
                    <p class="site-alert-title">{{ __('form-builder::forms.confirmation_title') }}</p>
                    <p class="site-alert-text">{{ __('form-builder::forms.confirmation_text') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="form-confirm-data-{{ $formSlug }}"></div>
            <div class="flex items-center gap-3">
                <button type="button" id="form-confirm-back-{{ $formSlug }}" class="site-btn site-btn-secondary">
                    <x-ui.icon name="pencil" /> {{ __('form-builder::forms.edit_submission') }}
                </button>
                <button type="button" id="form-confirm-submit-{{ $formSlug }}" class="site-btn site-btn-primary">
                    <x-ui.icon name="check" /> {{ __('form-builder::forms.confirm_submit') }}
                </button>
            </div>
        </div>
        @endif

        <form action="{{ route('forms.submit', $form->slug) }}" method="POST" enctype="multipart/form-data"
              id="form-{{ $formSlug }}" @if($showConfirmation) data-confirm="1" @endif>
            @csrf

            {{-- Honeypot --}}
            @if($form->settings['honeypot'] ?? true)
            <div style="display:none" aria-hidden="true">
                <input type="text" name="_honeypot" value="" tabindex="-1" autocomplete="off" />
            </div>
            @endif

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                {!! $fieldsHtml !!}
            </div>

            <div class="mt-6">
                <button type="submit" class="site-btn site-btn-primary site-btn-lg">
                    {{ !empty($form->settings['submit_label']) ? $form->settings['submit_label'] : __('form-builder::forms.submit') }}
                    <x-ui.icon name="send" />
                </button>
            </div>
        </form>

        @if($showConfirmation)
        <script @cspNonce>
        (function() {
            var slug = @json($formSlug);
            var form = document.getElementById('form-' + slug);
            var confirmWrap = document.getElementById('form-confirm-' + slug);
            var confirmData = document.getElementById('form-confirm-data-' + slug);
            var backBtn = document.getElementById('form-confirm-back-' + slug);
            var submitBtn = document.getElementById('form-confirm-submit-' + slug);
            var items = @json($form->fields ?? []);

            if (!form || !confirmWrap) return;

            function esc(s) { return String(s).replace(/</g, '&lt;'); }

            function getFieldValue(formData, f) {
                var name = f.name || '';
                if (!name || f.type === 'hidden') return null;
                if (f.type === 'file') {
                    var fi = form.querySelector('[name="' + name + '"]');
                    return fi && fi.files.length ? fi.files[0].name : '\u2014';
                }
                if (f.type === 'checkbox') return formData.get(name) ? '\u2713' : '\u2717';
                if (f.type === 'multi-select' || f.type === 'checkbox-group') {
                    var vals = formData.getAll(name + '[]');
                    return vals.length ? vals.join(', ') : '\u2014';
                }
                return formData.get(name) || '\u2014';
            }

            function renderFieldPreview(f, formData) {
                var val = getFieldValue(formData, f);
                if (val === null) return null;
                var d = document.createElement('div');
                d.className = 'border-b border-gray-100 pb-2';
                d.innerHTML = '<div class="text-xs font-medium text-gray-500 mb-0.5">' +
                    esc(f.label || f.name) + (f.required ? ' <span class="text-red-500">*</span>' : '') +
                    '</div><div class="text-sm text-gray-800">' + esc(val).replace(/\n/g, '<br>') + '</div>';
                return d;
            }

            function renderItems(list, container, formData) {
                for (var i = 0; i < list.length; i++) {
                    var item = list[i];
                    if (item.type === 'row') {
                        var cols = item.children || [];
                        var row = document.createElement('div');
                        row.className = 'md:col-span-2 grid grid-cols-1 md:grid-cols-' + cols.length + ' gap-4';
                        for (var c = 0; c < cols.length; c++) {
                            var colDiv = document.createElement('div');
                            colDiv.className = 'space-y-2';
                            var children = (cols[c].children || []);
                            for (var f = 0; f < children.length; f++) {
                                var el = renderFieldPreview(children[f], formData);
                                if (el) colDiv.appendChild(el);
                            }
                            row.appendChild(colDiv);
                        }
                        container.appendChild(row);
                    } else if (item.type !== 'column') {
                        var el = renderFieldPreview(item, formData);
                        if (el) {
                            var w = document.createElement('div');
                            w.className = (item.width === 'half' ? 'md:col-span-1' : 'md:col-span-2');
                            w.appendChild(el);
                            container.appendChild(w);
                        }
                    }
                }
            }

            form.addEventListener('submit', function(e) {
                if (form.dataset.confirmed) return; // Let it through on second submit
                e.preventDefault();

                // Basic HTML5 validation
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // Build confirmation preview with same layout as form
                confirmData.innerHTML = '';
                var formData = new FormData(form);
                renderItems(items, confirmData, formData);

                form.style.display = 'none';
                confirmWrap.classList.remove('hidden');
                confirmWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            backBtn.addEventListener('click', function() {
                confirmWrap.classList.add('hidden');
                form.style.display = '';
            });

            submitBtn.addEventListener('click', function() {
                form.dataset.confirmed = '1';
                form.submit();
            });
        })();
        </script>
        @endif
    @endif
</div>
