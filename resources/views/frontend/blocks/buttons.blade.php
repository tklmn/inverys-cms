@php
    // Several buttons side by side — wrapping instead of squeezed into columns
    $styles = ['primary', 'secondary', 'outline', 'ghost', 'light', 'danger'];
    $size = match($settings['size'] ?? '') { 'lg' => 'site-btn-lg', 'sm' => 'site-btn-sm', default => '' };
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    // Keys are preserved: the editor preview edits items.N.text
    $items = array_filter($data['items'] ?? [], fn ($item) => filled($item['text'] ?? null));
@endphp
@if($items)
<div class="block-buttons site-btn-group {{ $align }} {{ $settings['class'] ?? '' }}">
    @foreach($items as $i => $item)
        @php
            $style = in_array($item['style'] ?? '', $styles, true) ? $item['style'] : 'primary';
            $icon = \App\Support\ContentIcons::has($item['icon'] ?? null) ? $item['icon'] : null;
            $url = \App\Helpers\HtmlHelper::safeUrl($item['url'] ?? '#');
            // Buttons without a chosen target keep the old rule: external links open in a new tab
            $external = str_starts_with($url, 'http') && ! str_starts_with($url, url('/'));
            $newTab = \App\Support\BlockDesign::newTab($item['target'] ?? null, $external);
        @endphp
        <a href="{{ $url }}" class="site-btn site-btn-{{ $style }} {{ $size }}"{{ \App\Support\BlockDesign::linkAttributes($newTab, $item['aria_label'] ?? null) }}>
            <span @pbField('items.'.$i.'.text')>{{ $item['text'] }}</span>{{ \App\Support\BlockDesign::newTabHint($newTab) }}
            @if($icon)<x-ui.icon :name="$icon" />@endif
        </a>
    @endforeach
</div>
@endif
