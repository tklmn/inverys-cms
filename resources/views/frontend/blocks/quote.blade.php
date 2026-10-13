@php($avatar = ! empty($data['image']) ? (Str::startsWith($data['image'], ['http://', 'https://']) ? $data['image'] : asset($data['image'])) : null)
{{-- Quote/testimonial: text on top, person below (name bold, role underneath) — cards in a row equally tall --}}
<figure class="block-quote site-quote {{ $settings['class'] ?? '' }}">
    <x-ui.icon name="quote" class="site-quote-mark" />
    <blockquote>
        <p class="site-quote-text"@pbField('text')>{{ $data['text'] ?? '' }}</p>
    </blockquote>
    @if(! empty($data['author']))
        <figcaption class="site-quote-footer {{ $avatar ? 'site-quote-person' : '' }}">
            @if($avatar)
                <img src="{{ $avatar }}" alt="" class="site-quote-avatar" loading="lazy">
            @endif
            <span class="site-quote-who">
                <span class="site-quote-author"@pbField('author')>{{ $data['author'] }}</span>
                @if(! empty($data['cite']))
                    <cite class="site-quote-cite">{{ $data['cite'] }}</cite>
                @endif
            </span>
        </figcaption>
    @endif
</figure>
