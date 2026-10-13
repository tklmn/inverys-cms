@php
    $slides = array_values(array_filter($data['slides'] ?? [], 'is_array'));
    // Older sliders without the option played automatically; new ones start switched off
    $autoplay = (bool) ($data['autoplay'] ?? true);
    $interval = $data['interval'] ?? 5000;
    $showArrows = $data['show_arrows'] ?? true;
    $showDots = $data['show_dots'] ?? true;
    $height = \App\Support\BlockDesign::length($settings['height'] ?? null, '400px');
    $class = $settings['class'] ?? '';
    $sliderId = 'slider-' . uniqid();
    $total = count($slides);
    $rotating = $autoplay && $total > 1;
    $label = trim((string) ($data['aria_label'] ?? '')) ?: __('translation.frontend.slider_label');
    $titleTag = \App\Support\BlockDesign::headingLevel($settings['title_level'] ?? null, 'h3');
    $control = 'absolute z-10 w-10 h-10 flex items-center justify-center rounded-full bg-black/30 hover:bg-black/50 text-white transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-white/50';
@endphp

@if($total > 0)
<div
    id="{{ $sliderId }}"
    data-slider
    data-autoplay="{{ $rotating ? 'true' : 'false' }}"
    data-interval="{{ (int) $interval }}"
    role="region"
    aria-roledescription="{{ __('translation.frontend.slider_carousel') }}"
    aria-label="{{ $label }}"
    class="block-slider relative overflow-hidden rounded-[var(--site-radius-lg)] shadow-[var(--site-shadow-lg)] {{ $class }}"
    style="height: {{ $height }}"
>
    {{-- Pause/play first in the focus order (WCAG 2.2.2) --}}
    @if($rotating)
        <button
            type="button"
            data-slider-toggle
            data-label-pause="{{ __('translation.frontend.slider_pause') }}"
            data-label-play="{{ __('translation.frontend.slider_play') }}"
            class="{{ $control }} right-3 bottom-3"
            aria-label="{{ __('translation.frontend.slider_pause') }}"
        >
            <svg data-icon-pause class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5v14M15 5v14"/>
            </svg>
            <svg data-icon-play class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 5l12 7-12 7V5z"/>
            </svg>
        </button>
    @endif

    {{-- Slides; announced politely only while nothing rotates by itself --}}
    <div class="absolute inset-0" data-slider-track aria-live="{{ $rotating ? 'off' : 'polite' }}">
    @foreach($slides as $index => $slide)
        @php
            $alt = \App\Support\BlockDesign::alt($slide['alt'] ?? '', $slide['decorative'] ?? false, (string) ($slide['title'] ?? ''));
            $newTab = \App\Support\BlockDesign::newTab($slide['target'] ?? null);
        @endphp
        <div
            data-slide="{{ $index }}"
            role="group"
            aria-roledescription="{{ __('translation.frontend.slider_slide') }}"
            aria-label="{{ __('translation.frontend.slider_position', ['current' => $index + 1, 'total' => $total]) }}"
            @if($index !== 0) inert aria-hidden="true" @endif
            class="slider-slide absolute inset-0 transition-opacity duration-500 motion-reduce:transition-none {{ $index === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' }}"
        >
            {{-- Background Image --}}
            @if(!empty($slide['image']))
                {{-- First slide of the page's first image block: eager with high priority --}}
                <x-responsive-image
                    :src="$slide['image']"
                    sizes="(min-width: 80rem) 80rem, 100vw"
                    :priority="$index === 0 && ($imagePriority ?? false)"
                    alt="{{ $alt }}"
                    class="absolute inset-0 w-full h-full object-cover"
                />
            @endif

            {{-- Overlay --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

            {{-- Content --}}
            @if(!empty($slide['title']) || !empty($slide['text']) || !empty($slide['url']))
                <div class="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 md:p-12">
                    @if(!empty($slide['title']))
                        <{{ $titleTag }} class="text-xl sm:text-2xl md:text-3xl font-bold text-white mb-2">
                            {{ $slide['title'] }}
                        </{{ $titleTag }}>
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
                                class="inline-flex items-center px-4 py-2 bg-white/90 hover:bg-white text-gray-900 rounded-lg text-sm font-medium transition-colors duration-200"{{ \App\Support\BlockDesign::linkAttributes($newTab, $slide['aria_label'] ?? null) }}
                            >
                                {{ __('Learn more') }}{{ \App\Support\BlockDesign::newTabHint($newTab) }}
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
    </div>

    @if($total > 1)
        {{-- Previous / Next Arrows --}}
        @if($showArrows)
        <button
            type="button"
            data-slider-prev
            aria-controls="{{ $sliderId }}"
            class="{{ $control }} left-3 top-1/2 -translate-y-1/2"
            aria-label="{{ __('Previous slide') }}"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <button
            type="button"
            data-slider-next
            aria-controls="{{ $sliderId }}"
            class="{{ $control }} right-3 top-1/2 -translate-y-1/2"
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
                    @if($index === 0) aria-current="true" @endif
                ></button>
            @endforeach
        </div>
        @endif
    @endif
</div>

@once
<script @cspNonce>
// Once per page for every slider. No automatic rotation under "reduce motion";
// rotation pauses while the pointer or the keyboard focus is inside the slider.
(function () {
    if (window.inverysSliders) return;
    window.inverysSliders = true;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function own(container, selector) {
        return Array.prototype.filter.call(container.querySelectorAll(selector), function (el) {
            return el.closest('[data-slider]') === container;
        });
    }

    function init(container) {
        if (container.dataset.sliderBound) return;
        container.dataset.sliderBound = '1';

        var slides = own(container, '[data-slide]');
        var dots = own(container, '[data-dot]');
        var total = slides.length;
        if (total < 2) return;

        var interval = parseInt(container.getAttribute('data-interval'), 10) || 5000;
        var track = own(container, '[data-slider-track]')[0];
        var toggle = own(container, '[data-slider-toggle]')[0];
        var playing = container.getAttribute('data-autoplay') === 'true' && !reduceMotion;
        var hovered = false;
        var focused = false;
        var current = 0;
        var timer = null;

        function goTo(index) {
            slides.forEach(function (slide, i) {
                var active = i === index;
                slide.classList.toggle('opacity-100', active);
                slide.classList.toggle('opacity-0', !active);
                slide.classList.toggle('pointer-events-none', !active);
                // Hidden slides are neither focusable nor read out
                if (active) {
                    slide.removeAttribute('inert');
                    slide.removeAttribute('aria-hidden');
                } else {
                    slide.setAttribute('inert', '');
                    slide.setAttribute('aria-hidden', 'true');
                }
            });
            dots.forEach(function (dot, i) {
                var active = i === index;
                dot.classList.toggle('bg-white', active);
                dot.classList.toggle('scale-110', active);
                dot.classList.toggle('bg-white/50', !active);
                dot.classList.toggle('hover:bg-white/75', !active);
                if (active) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
            current = index;
        }

        function stop() {
            if (timer) { clearInterval(timer); timer = null; }
        }

        function start() {
            stop();
            if (playing && !hovered && !focused) {
                timer = setInterval(function () { goTo((current + 1) % total); }, interval);
            }
        }

        function setPlaying(on) {
            playing = on;
            if (track) track.setAttribute('aria-live', on ? 'off' : 'polite');
            if (toggle) {
                toggle.setAttribute('aria-label', toggle.getAttribute(on ? 'data-label-pause' : 'data-label-play'));
                var pauseIcon = toggle.querySelector('[data-icon-pause]');
                var playIcon = toggle.querySelector('[data-icon-play]');
                if (pauseIcon) pauseIcon.classList.toggle('hidden', !on);
                if (playIcon) playIcon.classList.toggle('hidden', on);
            }
            start();
        }

        function step(delta) {
            goTo((current + delta + total) % total);
            start();
        }

        var prevBtn = own(container, '[data-slider-prev]')[0];
        var nextBtn = own(container, '[data-slider-next]')[0];
        if (prevBtn) prevBtn.addEventListener('click', function () { step(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { step(1); });
        dots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                goTo(parseInt(dot.getAttribute('data-dot'), 10));
                start();
            });
        });
        if (toggle) toggle.addEventListener('click', function () { setPlaying(!playing); });

        container.addEventListener('mouseenter', function () { hovered = true; stop(); });
        container.addEventListener('mouseleave', function () { hovered = false; start(); });
        container.addEventListener('focusin', function () { focused = true; stop(); });
        container.addEventListener('focusout', function (e) {
            if (container.contains(e.relatedTarget)) return;
            focused = false;
            start();
        });

        // Instance API (previously used by inline handlers)
        if (!window._sliders) window._sliders = {};
        window._sliders[container.id] = {
            next: function () { step(1); },
            prev: function () { step(-1); },
            goTo: goTo,
        };

        setPlaying(playing);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-slider]').forEach(init);
    });
})();
</script>
@endonce
@endif
