@php
    $formSlug = $form->slug;
    // Key of this copy on the page (slug, from the second copy slug-2 …) — keeps ids unique
    $instance = $instance ?? $formSlug;
    $isActive = $isActive ?? true;
    $errorSummary = $errorSummary ?? [];
    $hasRequired = $hasRequired ?? false;
    $accessibleName = $accessibleName ?? $form->name;

    $successMsg = $isActive ? session('form_success_' . $formSlug) : null;
    $formError = $isActive ? session('form_error_' . $formSlug) : null;
    $showConfirmation = !empty($form->settings['show_confirmation']);

    // After a redirect the visitor lands on the message: focus moves there
    $focusId = null;
    if ($successMsg) {
        $focusId = 'form-success-' . $instance;
    } elseif (!empty($errorSummary)) {
        $focusId = 'form-errors-' . $instance;
    } elseif ($formError) {
        $focusId = 'form-error-' . $instance;
    }
@endphp

<div class="form-builder-form {{ $form->settings['css_class'] ?? '' }}" id="form-wrap-{{ $instance }}">
    @if($successMsg)
        {{-- Success state — form is hidden; focus moves here (tabindex) so it is announced --}}
        <div class="form-builder-success site-alert site-alert-success" role="status" id="form-success-{{ $instance }}" tabindex="-1">
            <span class="site-alert-icon"><x-ui.icon name="circle-check" /></span>
            <div class="site-alert-body">
                <p class="site-alert-title">{{ __('form-builder::forms.submission_success') }}</p>
                <p class="site-alert-text">{{ $successMsg }}</p>
            </div>
        </div>
    @else
        @if($formError)
            <div class="site-alert site-alert-danger mb-5" role="alert" id="form-error-{{ $instance }}" tabindex="-1">
                <span class="site-alert-icon"><x-ui.icon name="circle-x" /></span>
                <div class="site-alert-body"><p class="site-alert-text !mt-0">{{ $formError }}</p></div>
            </div>
        @endif

        @if(!empty($errorSummary))
            {{-- Error summary: receives focus after a failed submit, every entry links to its field --}}
            <div class="form-builder-errors site-alert site-alert-danger mb-5" id="form-errors-{{ $instance }}" tabindex="-1">
                <span class="site-alert-icon"><x-ui.icon name="circle-alert" /></span>
                <div class="site-alert-body">
                    <p class="site-alert-title">{{ trans_choice('form-builder::forms.error_summary_title', count($errorSummary)) }}</p>
                    <ul class="site-alert-text list-disc ps-4">
                        @foreach($errorSummary as $entry)
                            <li>
                                <a href="#{{ $entry['id'] }}">{{ str_contains($entry['message'], $entry['label']) ? $entry['message'] : $entry['label'] . ': ' . $entry['message'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Confirmation preview (hidden by default, shown via JS) --}}
        @if($showConfirmation)
        <div id="form-confirm-{{ $instance }}" class="hidden">
            <div class="site-alert mb-5">
                <span class="site-alert-icon"><x-ui.icon name="info" /></span>
                <div class="site-alert-body">
                    <p class="site-alert-title" id="form-confirm-title-{{ $instance }}" tabindex="-1">{{ __('form-builder::forms.confirmation_title') }}</p>
                    <p class="site-alert-text">{{ __('form-builder::forms.confirmation_text') }}</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6" id="form-confirm-data-{{ $instance }}"></div>
            <div class="flex items-center gap-3">
                <button type="button" id="form-confirm-back-{{ $instance }}" class="site-btn site-btn-secondary">
                    <x-ui.icon name="pencil" /> {{ __('form-builder::forms.edit_submission') }}
                </button>
                <button type="button" id="form-confirm-submit-{{ $instance }}" class="site-btn site-btn-primary">
                    <x-ui.icon name="check" /> {{ __('form-builder::forms.confirm_submit') }}
                </button>
            </div>
        </div>
        @endif

        <form action="{{ route('forms.submit', $form->slug) }}" method="POST" enctype="multipart/form-data"
              id="form-{{ $instance }}" aria-label="{{ $accessibleName }}" @if($showConfirmation) data-confirm="1" @endif>
            @csrf
            {{-- Which copy of the form was sent — errors and old input are shown only there --}}
            <input type="hidden" name="_fb_instance" value="{{ $instance }}" />

            {{-- Honeypot: invisible, outside the tab order and hidden from assistive technology --}}
            @if($form->settings['honeypot'] ?? true)
            <div style="display:none" aria-hidden="true">
                <input type="text" name="_honeypot" value="" tabindex="-1" autocomplete="off" />
            </div>
            @endif

            @if($hasRequired)
                <p class="form-static-text form-builder-required-note mb-4 text-sm">{{ __('form-builder::forms.required_note') }}</p>
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
            var instance = @json($instance);
            var form = document.getElementById('form-' + instance);
            var confirmWrap = document.getElementById('form-confirm-' + instance);
            var confirmTitle = document.getElementById('form-confirm-title-' + instance);
            var confirmData = document.getElementById('form-confirm-data-' + instance);
            var backBtn = document.getElementById('form-confirm-back-' + instance);
            var submitBtn = document.getElementById('form-confirm-submit-' + instance);
            var items = @json($form->fields ?? []);

            if (!form || !confirmWrap) return;

            function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

            function getFieldValue(formData, f) {
                var name = f.name || '';
                if (!name || f.type === 'hidden') return null;
                if (f.type === 'file') {
                    var fi = form.querySelector('[name="' + name + '"]');
                    return fi && fi.files.length ? fi.files[0].name : '—';
                }
                // The hidden "0" comes first, the checked box adds "1"
                if (f.type === 'checkbox') return formData.getAll(name).indexOf('1') !== -1 ? '✓' : '✗';
                if (f.type === 'multi-select' || f.type === 'checkbox-group') {
                    var vals = formData.getAll(name + '[]');
                    return vals.length ? vals.join(', ') : '—';
                }
                return formData.get(name) || '—';
            }

            function renderFieldPreview(f, formData) {
                var val = getFieldValue(formData, f);
                if (val === null) return null;
                var d = document.createElement('div');
                d.className = 'border-b border-gray-100 pb-2';
                d.innerHTML = '<div class="text-xs font-medium text-gray-500 mb-0.5">' +
                    esc(f.label || f.name) + (f.required ? ' <span class="text-red-600" aria-hidden="true">*</span>' : '') +
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
                // Move focus to the new step so keyboard and screen reader users land on it
                confirmWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (confirmTitle) confirmTitle.focus({ preventScroll: true });
            });

            backBtn.addEventListener('click', function() {
                confirmWrap.classList.add('hidden');
                form.style.display = '';
                var first = form.querySelector('input:not([type="hidden"]):not([tabindex="-1"]), select, textarea');
                if (first) first.focus();
            });

            submitBtn.addEventListener('click', function() {
                form.dataset.confirmed = '1';
                form.submit();
            });
        })();
        </script>
        @endif
    @endif

    @if($focusId)
    <script @cspNonce>
    (function () {
        var target = document.getElementById(@json($focusId));
        if (target) target.focus();
    })();
    </script>
    @endif
</div>
