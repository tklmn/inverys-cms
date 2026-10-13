@if(! empty($data['src']))
@php
    $plain = ($settings['style'] ?? '') === 'plain';
    // Aspect ratio and corners as options — the image is cropped to fit
    $ratio = ['16-9' => 'aspect-video', '4-3' => 'aspect-[4/3]', '1-1' => 'aspect-square', '3-4' => 'aspect-[3/4]'][$settings['ratio'] ?? ''] ?? '';
    $corners = ['round' => 'site-image-round', 'square' => 'site-image-square'][$settings['corners'] ?? ''] ?? '';
    $link = ! empty($data['url']) ? \App\Helpers\HtmlHelper::safeUrl($data['url']) : null;
    $fullWidth = ($settings['width'] ?? 'full') === 'full';
@endphp
<figure class="block-image {{ $plain ? 'site-image-plain' : '' }} {{ $ratio ? 'site-image-cover' : '' }} {{ $corners }} {{ $settings['class'] ?? '' }}">
    @if($link)<a href="{{ $link }}" class="block">@endif
    {{-- srcset, width/height and loading come from the media library (first image of the page: high priority) --}}
    <x-responsive-image
        :src="$data['src']"
        :sizes="$fullWidth ? null : 'intrinsic'"
        :priority="$imagePriority ?? false"
        alt="{{ \App\Support\BlockDesign::alt($data['alt'] ?? $data['caption'] ?? '', $settings['decorative'] ?? false) }}"
        class="{{ $fullWidth ? 'w-full' : '' }} {{ $ratio }} rounded-[var(--site-radius-lg)] border border-[var(--site-border)] shadow-[var(--site-shadow)]"
    />
    @if($link)</a>@endif
    @if(! empty($data['caption']))
        <figcaption class="mt-3 text-center text-sm text-[var(--site-subtle)]">
            {{ $data['caption'] }}
        </figcaption>
    @endif
</figure>
@endif
