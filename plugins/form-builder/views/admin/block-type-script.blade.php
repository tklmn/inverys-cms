@php
    // Head hooks also run on the website: only the page builder in the backend
    // needs this script, and the form list must not reach visitors
    if (! request()->is('backend', 'backend/*')) return;
@endphp
<script @cspNonce>
(function () {
    var forms = @json($forms ?? []);

    // Empty entry first — as a list of pairs; in an object the numeric IDs would come before it
    var formOptions = [['', '— {{ __("form-builder::forms.form") }} —']]; for (var id in forms) {
        formOptions.push([id, forms[id]]);
    }

    window.__inverysPluginBlockTypes = window.__inverysPluginBlockTypes || [];
    window.__inverysPluginBlockTypes.push({
        slug: 'form', name: '{{ __("form-builder::forms.form") }}', category: 'interactive', icon: 'bi-ui-checks-grid', defaults: {
            data: { form_id: '' }, settings: { class: '' } }
    });

    window.__inverysBlockSettingsRenderers = window.__inverysBlockSettingsRenderers || {};
    window.__inverysBlockSettingsRenderers['form'] = function (wrap, block, data, settings, onChange, builder) { wrap.appendChild(builder.selectField(
            '{{ __("form-builder::forms.form") }}', data.form_id || '', formOptions,
            function (v) { data.form_id = v; onChange(); }
        ));
    };
})();
</script>
