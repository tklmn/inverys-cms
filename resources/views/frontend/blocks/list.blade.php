@php
    $ordered = ($data['style'] ?? 'unordered') === 'ordered';
    $tag = $ordered ? 'ol' : 'ul';
@endphp
<{{ $tag }} class="block-list site-list {{ $ordered ? 'site-list-ordered' : '' }} {{ $settings['class'] ?? '' }}">
    @foreach(($data['items'] ?? []) as $item)
        <li>{{ $item }}</li>
    @endforeach
</{{ $tag }}>
