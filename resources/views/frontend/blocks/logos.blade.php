@php
    // Logo strip (customers, partners); muted = grey, coloured on hover
    $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['image'] ?? null)));
    $muted = ($settings['style'] ?? 'muted') === 'muted';
@endphp
@if($items)
<div class="block-logos site-logos {{ $muted ? 'site-logos-muted' : '' }} {{ $settings['class'] ?? '' }}">
    @foreach($items as $item)
        @php
            // The strip is the page's first image: its first logo loads with high priority
            $priority = ($imagePriority ?? false) && $loop->first;
            $alt = \App\Support\BlockDesign::alt($item['alt'] ?? '', $item['decorative'] ?? false);
            // Logos without a chosen target keep opening in a new tab, as before
            $newTab = \App\Support\BlockDesign::newTab($item['target'] ?? null, true);
        @endphp
        @if(! empty($item['url']))
            <a href="{{ \App\Helpers\HtmlHelper::safeUrl($item['url']) }}"{{ \App\Support\BlockDesign::linkAttributes($newTab) }}>
                <x-responsive-image :src="$item['image']" sizes="11rem" :priority="$priority" :lazy="! ($imagePriority ?? false)" :focal="false" alt="{{ $alt }}" />{{ \App\Support\BlockDesign::newTabHint($newTab) }}
            </a>
        @else
            <x-responsive-image :src="$item['image']" sizes="11rem" :priority="$priority" :lazy="! ($imagePriority ?? false)" :focal="false" alt="{{ $alt }}" />
        @endif
    @endforeach
</div>
@endif
