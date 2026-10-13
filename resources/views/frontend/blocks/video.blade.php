@php
    $url = $data['url'] ?? '';
    $embed = null;

    // YouTube (watch, youtu.be, embed) → privacy-friendly nocookie embed
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    // Vimeo
    elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        $embed = 'https://player.vimeo.com/video/' . $m[1];
    }
@endphp

@if(!empty($url))
<div class="block-video {{ $settings['class'] ?? '' }}">
    @if($embed)
        <div class="relative w-full overflow-hidden rounded-[var(--site-radius-lg)] shadow-[var(--site-shadow-lg)]" style="aspect-ratio: 16 / 9;">
            <iframe
                class="absolute inset-0 w-full h-full"
                src="{{ $embed }}"
                title="{{ $data['title'] ?? __('Video') }}"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            ></iframe>
        </div>
    @else
        <video
            class="w-full rounded-[var(--site-radius-lg)]"
            {{ ($settings['controls'] ?? true) ? 'controls' : '' }}
            {{ ($settings['autoplay'] ?? false) ? 'autoplay muted' : '' }}
            @if(!empty($data['poster'])) poster="{{ \App\Helpers\HtmlHelper::safeUrl($data['poster']) }}" @endif
        >
            <source src="{{ \App\Helpers\HtmlHelper::safeUrl($url) }}">
        </video>
    @endif
</div>
@endif
