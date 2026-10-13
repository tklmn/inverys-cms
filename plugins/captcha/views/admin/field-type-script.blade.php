@php
    // Only for the form builder in the backend — head hooks also run on the website
    if (! request()->is('backend', 'backend/*')) return;
@endphp
<script @cspNonce>
(function () {
    // Register captcha field type for form-builder palette
    window.__fbPluginFieldTypes = window.__fbPluginFieldTypes || [];
    window.__fbPluginFieldTypes.push({
        type: 'captcha', icon: 'shield', label: '{{ __("captcha::captcha.field_label") }}' });

    // Register captcha field creator (used by FormBuilder.addField)
    window.__fbFieldCreators = window.__fbFieldCreators || {};
    window.__fbFieldCreators['captcha'] = function (uid) { return {
            id: uid(),
            type: 'captcha', label: '{{ __("captcha::captcha.field_label") }}', name: '_captcha', width: 'full', provider: '', };
    };

    // Available providers for the dropdown
    var captchaProviders = @json(collect(\Plugins\Captcha\Services\CaptchaService::availableProviders())->map(fn($label, $slug) => ['slug' => $slug, 'label' => __($label)])->values()); // Register captcha field settings renderer
    window.__fbFieldSettingsRenderers = window.__fbFieldSettingsRenderers || {};
    window.__fbFieldSettingsRenderers['captcha'] = function (wrap, field, builder) { // Provider dropdown
        var label = document.createElement('label'); label.className = 'block text-sm font-medium text-fg mb-1'; label.textContent = '{{ __("captcha::captcha.select_provider") }}'; wrap.appendChild(label);

        var select = document.createElement('select'); select.className = 'ui-select'; // Default option (use global)
        var defaultOpt = document.createElement('option'); defaultOpt.value = ''; defaultOpt.textContent = '{{ __("captcha::captcha.use_global_default") }}'; select.appendChild(defaultOpt);

        captchaProviders.forEach(function (p) {
            var opt = document.createElement('option'); opt.value = p.slug;
            opt.textContent = p.label;
            if (field.provider === p.slug) opt.selected = true;
            select.appendChild(opt);
        });

        select.addEventListener('change', function () { field.provider = select.value;
            builder.sync();
            builder.scheduleAutoSave();
        });

        wrap.appendChild(select);

        // Info hint
        var info = document.createElement('p'); info.className = 'mt-2 text-xs text-fg-muted'; info.textContent = '{{ __("captcha::captcha.field_info") }}'; wrap.appendChild(info);
    };
})();
</script>
