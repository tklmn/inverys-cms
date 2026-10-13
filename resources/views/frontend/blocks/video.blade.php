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

    $title = trim((string) ($data['title'] ?? ''));
    $autoplay = (bool) ($settings['autoplay'] ?? false);
    // A video that starts by itself can always be paused (WCAG 2.2.2)
    $controls = $autoplay || ($settings['controls'] ?? true);
    $poster = \App\Support\BlockDesign::mediaUrl($data['poster'] ?? '');
    // Captions (WebVTT) for the own video file — platforms bring their own
    $captions = \App\Support\BlockDesign::mediaUrl($data['captions'] ?? '');
    $captionsLang = \App\Support\BlockDesign::lang($settings['captions_srclang'] ?? '') ?: str_replace('_', '-', app()->getLocale());
    $captionsLabel = trim((string) ($data['captions_label'] ?? '')) ?: __('translation.frontend.video_captions');
    // Transcript: text below the video and/or a link to it
    $transcript = trim((string) ($data['transcript'] ?? ''));
    $transcriptUrl = ! empty($data['transcript_url']) ? \App\Helpers\HtmlHelper::safeUrl($data['transcript_url']) : null;
@endphp

@if(!empty($url))
<div class="block-video {{ $settings['class'] ?? '' }}">
    @if($embed)
        <div class="relative w-full overflow-hidden rounded-[var(--site-radius-lg)] shadow-[var(--site-shadow-lg)]" style="aspect-ratio: 16 / 9;">
            <iframe
                class="absolute inset-0 w-full h-full"
                src="{{ $embed }}"
                title="{{ $title !== '' ? $title : __('Video') }}"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen
            ></iframe>
        </div>
    @else
        <video
            class="w-full rounded-[var(--site-radius-lg)]"
            {{ $controls ? 'controls' : '' }}
            {{ $autoplay ? 'autoplay muted' : '' }}
            @if($title !== '') aria-label="{{ $title }}" @endif
            @if($poster !== '') poster="{{ $poster }}" @endif
        >
            <source src="{{ \App\Helpers\HtmlHelper::safeUrl($url) }}">
            @if($captions !== '')
                <track kind="captions" src="{{ $captions }}" srclang="{{ $captionsLang }}" label="{{ $captionsLabel }}" default>
            @endif
        </video>
    @endif
    @if($transcript !== '')
        <details class="site-video-transcript mt-3">
            <summary class="cursor-pointer font-medium text-[var(--site-heading)]">{{ __('translation.frontend.video_transcript') }}</summary>
            <div class="site-prose prose dark:prose-invert max-w-none mt-2">
                @foreach(preg_split('/\R{2,}/', $transcript) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>
        </details>
    @endif
    @if($transcriptUrl)
        <p class="mt-2 text-sm"><a href="{{ $transcriptUrl }}" class="site-link-arrow">{{ __('translation.frontend.video_transcript_link') }} <x-ui.icon name="arrow-right" /></a></p>
    @endif
</div>
@endif
