@php
    // Only for the page builder in the backend — the website needs neither script nor album list
    if (! request()->is('backend', 'backend/*')) return;
@endphp
<script @cspNonce>
(function () {
    var albums = @json($albums ?? []);

    // As a list of pairs: in an object the numeric IDs would come before the empty entry
    var albumOptions = [['', '{{ __("gallery::gallery.select_album") }}']]; for (var id in albums) {
        albumOptions.push([id, albums[id]]);
    }

    window.__inverysPluginBlockTypes = window.__inverysPluginBlockTypes || [];
    window.__inverysPluginBlockTypes.push({
        slug: 'gallery', name: '{{ __("gallery::gallery.albums") }}', category: 'content', icon: 'bi-images', defaults: {
            data: { album_id: '' }, settings: { class: '' } }
    });

    window.__inverysBlockSettingsRenderers = window.__inverysBlockSettingsRenderers || {};
    window.__inverysBlockSettingsRenderers['gallery'] = function (wrap, block, data, settings, onChange, builder) { wrap.appendChild(builder.selectField(
            '{{ __("gallery::gallery.album") }}', data.album_id || '', albumOptions,
            function (v) { data.album_id = v; onChange(); }
        ));
    };
})();
</script>
