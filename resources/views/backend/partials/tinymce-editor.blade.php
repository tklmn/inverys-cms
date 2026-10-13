{{-- Reusable TinyMCE WYSIWYG editor with media library integration --}}
{{-- @param string $name    - Form field name (e.g. 'content') --}}
{{-- @param string $id      - Element ID (defaults to $name) --}}
{{-- @param string $value   - Initial HTML content --}}
{{-- @param string $editorLabel - Optional label --}}
{{-- @param int    $height  - Editor height in px (default 400) --}}
@php
    /*
     * @include shares the variable scope of the calling view. A name such
     * as $label would collide with the loop variable of the parent page
     * select — the editor would get its last value as label. Hence a
     * unique name.
     */
    $id = $id ?? $name;
    $value = $value ?? '';
    $height = $height ?? 400;
    $editorLabel = $editorLabel ?? null;
@endphp

<div class="tinymce-editor-field">
    @if(! empty($editorLabel))
        <x-input-label :for="$id" :value="$editorLabel" class="mb-2" />
    @endif

    <textarea id="{{ $id }}" name="{{ $name }}">{{ $value }}</textarea>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>

<script @cspNonce>
(function () {
    var editorId = '{{ $id }}';
    var height   = {{ $height }};
    function tryInit() {
        if (typeof window.initTinyMCE === 'function') {
            window.initTinyMCE('#' + editorId, { min_height: height });
        } else {
            setTimeout(tryInit, 50);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
