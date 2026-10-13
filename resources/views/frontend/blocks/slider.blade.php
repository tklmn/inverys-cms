@php
    $slides = $data['slides'] ?? [];
    $autoplay = $data['autoplay'] ?? true;
    $interval = $data['interval'] ?? 5000;
    $showArrows = $data['show_arrows'] ?? true;
    $showDots = $data['show_dots'] ?? true;
    $height = \App\Support\BlockDesign::length($settings['height'] ?? null, '400px');
    $class = $settings['class'] ?? '';
    $sliderId = 'slider-' . uniqid();
@endphp

@if(count($slides) > 0)
<div
    id="{{ $sliderId }}"
    class="block-slider relative overflow-hidden rounded-[var(--site-radius-lg)] shadow-[var(--site-shadow-lg)] {{ $class }}"
    style="height: {{ $height }}"
>
    {{-- Slides --}}
    @foreach($slides as $index => $slide)
        <div
            data-slide="{{ $index }}"
            class="slider-slide absolute inset-0 transition-opacity duration-500 {{ $index === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' }}"
        >
            {{-- Background Image --}}
            @if(!empty($slide['image']))
                <img
                    src="{{ Str::startsWith($slide['image'], ['http://', 'https://']) ? $slide['image'] : asset($slide['image']) }}"
                    alt="{{ $slide['title'] ?? '' }}"
                    class="absolute inset-0 w-full h-full object-cover"
                    loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                >
            @endif

            {{-- Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

            {{-- Content --}}
            @if(!empty($slide['title']) || !empty($slide['text']) || !empty($slide['url']))
                <div class="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 md:p-12">
                    @if(!empty($slide['title']))
                        <h3 class="text-xl sm:text-2xl md:text-3xl font-bold text-white mb-2">
                            {{ $slide['title'] }}
                        </h3>
                    @endif
                    @if(!empty($slide['text']))
                        <p class="text-sm sm:text-base text-gray-200 max-w-2xl mb-4">
                            {{ $slide['text'] }}
                        </p>
                    @endif
                    @if(!empty($slide['url']))
                        <div>
                            <a
                                href="{{ \App\Helpers\HtmlHelper::safeUrl($slide['url']) }}"
                                class="inline-flex items-center px-4 py-2 bg-white/90 hover:bg-white text-gray-900 rounded-lg text-sm font-medium transition-colors duration-200"
                            >
                                {{ __('Learn more') }}
                                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endforeach

    @if(count($slides) > 1)
        {{-- Previous / Next Arrows --}}
        @if($showArrows)
        <button
            type="button"
            data-slider-prev
            class="absolute left-3 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center rounded-full bg-black/30 hover:bg-black/50 text-white transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-white/50"
            aria-label="{{ __('Previous slide') }}"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <button
            type="button"
            data-slider-next
            class="absolute right-3 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center rounded-full bg-black/30 hover:bg-black/50 text-white transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-white/50"
            aria-label="{{ __('Next slide') }}"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        @endif

        {{-- Dot Indicators --}}
        @if($showDots)
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-2">
            @foreach($slides as $index => $slide)
                <button
                    type="button"
                    data-dot="{{ $index }}"
                    class="w-2.5 h-2.5 rounded-full transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-white/50 {{ $index === 0 ? 'bg-white scale-110' : 'bg-white/50 hover:bg-white/75' }}"
                    aria-label="{{ __('Go to slide') }} {{ $index + 1 }}"
                ></button>
            @endforeach
        </div>
        @endif
    @endif
</div>

<script @cspNonce>
(function() {
    var sliderId = '{{ $sliderId }}';
    var total = {{ count($slides) }};
    var autoplay = {{ $autoplay ? 'true' : 'false' }};
    var interval = {{ (int) $interval }};
    var current = 0;
    var timer = null;

    function goTo(index) {
        var container = document.getElementById(sliderId);
        if (!container) return;

        container.querySelectorAll('[data-slide]').forEach(function(slide) {
            var i = parseInt(slide.getAttribute('data-slide'), 10);
            if (i === index) {
                slide.classList.remove('opacity-0', 'pointer-events-none');
                slide.classList.add('opacity-100');
            } else {
                slide.classList.remove('opacity-100');
                slide.classList.add('opacity-0', 'pointer-events-none');
            }
        });

        container.querySelectorAll('[data-dot]').forEach(function(dot) {
            var i = parseInt(dot.getAttribute('data-dot'), 10);
            if (i === index) {
                dot.classList.add('bg-white', 'scale-110');
                dot.classList.remove('bg-white/50', 'hover:bg-white/75');
            } else {
                dot.classList.remove('bg-white', 'scale-110');
                dot.classList.add('bg-white/50', 'hover:bg-white/75');
            }
        });

        current = index;
    }

    function next() {
        goTo((current + 1) % total);
        resetTimer();
    }

    function prev() {
        goTo((current - 1 + total) % total);
        resetTimer();
    }

    function startTimer() {
        timer = setInterval(function() {
            goTo((current + 1) % total);
        }, interval);
    }

    function resetTimer() {
        if (timer) { clearInterval(timer); timer = null; }
        if (autoplay) startTimer();
    }

    // Expose globally for onclick handlers
    window.sliderNext = window.sliderNext || function(id) {};
    window.sliderPrev = window.sliderPrev || function(id) {};
    window.sliderGoTo = window.sliderGoTo || function(id, index) {};

    // Register this instance
    if (!window._sliders) window._sliders = {};
    window._sliders[sliderId] = { next: next, prev: prev, goTo: goTo };

    window.sliderNext = function(id) { if (window._sliders[id]) window._sliders[id].next(); };
    window.sliderPrev = function(id) { if (window._sliders[id]) window._sliders[id].prev(); };
    window.sliderGoTo = function(id, index) { if (window._sliders[id]) window._sliders[id].goTo(index); };

    document.addEventListener('DOMContentLoaded', function() {
        var container = document.getElementById(sliderId);
        if (!container) return;

        if (autoplay && total > 1) startTimer();

        // Wire controls (was inline onclick sliderPrev/sliderNext/sliderGoTo)
        var prevBtn = container.querySelector('[data-slider-prev]');
        if (prevBtn) prevBtn.addEventListener('click', prev);
        var nextBtn = container.querySelector('[data-slider-next]');
        if (nextBtn) nextBtn.addEventListener('click', next);
        container.querySelectorAll('[data-dot]').forEach(function(dot) {
            dot.addEventListener('click', function() {
                goTo(parseInt(dot.getAttribute('data-dot'), 10));
            });
        });

        container.addEventListener('mouseenter', function() {
            if (timer) { clearInterval(timer); timer = null; }
        });
        container.addEventListener('mouseleave', function() {
            if (autoplay && total > 1) startTimer();
        });
    });
})();
</script>
@endif
