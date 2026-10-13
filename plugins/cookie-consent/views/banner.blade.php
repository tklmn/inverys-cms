@php
    $cc = cms()->settings()->getByPrefix('cc.');
    if (($cc['enabled'] ?? '0') !== '1') return;

    $consentRaw = request()->cookie('cc_consent');
    $consent = $consentRaw ? json_decode($consentRaw, true) : null;
    $hasConsented = is_array($consent) && isset($consent['ts']);

    $bannerText = $cc['banner_text'] ?? '';
    if (!$bannerText) {
        $bannerText = __('cookie-consent::cc.banner_default_text');
    }

    $bgColor = $cc['bg_color'] ?? '#1e293b';
    $textColor = $cc['text_color'] ?? '#f1f5f9';
    $btnBg = $cc['btn_bg_color'] ?? '#6366f1';
    $btnText = $cc['btn_text_color'] ?? '#ffffff';
    $position = $cc['position'] ?? 'bottom';
    $lifetime = (int) ($cc['cookie_lifetime'] ?? 365);
    $showReopen = ($cc['show_reopen_btn'] ?? '1') === '1';
    $reopenPosition = $cc['reopen_position'] ?? 'left';

    $privacySlug = $cc['privacy_page_slug'] ?? '';
    $imprintSlug = $cc['imprint_page_slug'] ?? '';
    // Page slugs only: otherwise url() passes "javascript://…" through unchanged
    $isSlug = fn ($slug) => (bool) preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', (string) $slug);
    $privacyUrl = $isSlug($privacySlug) ? url($privacySlug) : '';
    $imprintUrl = $isSlug($imprintSlug) ? url($imprintSlug) : '';

    $services = json_decode($cc['services'] ?? '[]', true) ?: [];
    $byCategory = collect($services)->groupBy('category');

    $categories = [
        'necessary'  => ['label' => __('cookie-consent::cc.category_necessary'),  'desc' => __('cookie-consent::cc.category_necessary_desc'),  'locked' => true],
        'functional' => ['label' => __('cookie-consent::cc.category_functional'), 'desc' => __('cookie-consent::cc.category_functional_desc'), 'locked' => false],
        'statistics' => ['label' => __('cookie-consent::cc.category_statistics'), 'desc' => __('cookie-consent::cc.category_statistics_desc'), 'locked' => false],
        'marketing'  => ['label' => __('cookie-consent::cc.category_marketing'),  'desc' => __('cookie-consent::cc.category_marketing_desc'),  'locked' => false],
    ];
@endphp

{{-- Consent Banner --}}
<div id="cc-banner" style="display:none;" data-position="{{ $position }}">
    <div id="cc-backdrop"></div>

    <div id="cc-panel" role="dialog" aria-modal="true" aria-labelledby="cc-banner-title">
        {{-- Header --}}
        <div class="cc-header">
            <span class="cc-icon"><x-ui.icon name="cookie" /></span>
            <h3 id="cc-banner-title" class="cc-title">{{ __('cookie-consent::cc.banner_heading') }}</h3>
        </div>

        {{-- Text --}}
        <p class="cc-text">{{ $bannerText }}</p>

        @if($privacyUrl || $imprintUrl)
        <div class="cc-links">
            @if($privacyUrl)
                <a href="{{ $privacyUrl }}" target="_blank">{{ __('cookie-consent::cc.privacy_policy') }}</a>
            @endif
            @if($privacyUrl && $imprintUrl) <span class="cc-link-sep">&middot;</span> @endif
            @if($imprintUrl)
                <a href="{{ $imprintUrl }}" target="_blank">{{ __('cookie-consent::cc.imprint') }}</a>
            @endif
        </div>
        @endif

        {{-- Options panel (hidden by default) --}}
        <div id="cc-options" class="cc-options">
            {{-- Categories --}}
            <div class="cc-categories" id="cc-categories">
                @foreach($categories as $catKey => $catMeta)
                    <div class="cc-category">
                        <div class="cc-category-info">
                            <span class="cc-category-label">{{ $catMeta['label'] }}</span>
                            <span class="cc-category-desc">{{ $catMeta['desc'] }}</span>
                            @if($byCategory->has($catKey))
                                <span class="cc-category-services">{{ $byCategory[$catKey]->pluck('name')->implode(', ') }}</span>
                            @endif
                        </div>
                        <div class="cc-category-toggle">
                            @if($catMeta['locked'])
                                <span class="cc-always-on">{{ __('cookie-consent::cc.always_active') }}</span>
                            @else
                                <label class="cc-switch">
                                    <input type="checkbox" id="cc-toggle-{{ $catKey }}"
                                           aria-label="{{ $catMeta['label'] }}"
                                           {{ $hasConsented && !empty($consent[$catKey]) ? 'checked' : '' }}>
                                    <span class="cc-slider"></span>
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Service details --}}
            @if($services)
            <div class="cc-details-section">
                <button type="button" id="cc-show-details" class="cc-details-toggle" aria-expanded="false" aria-controls="cc-details">
                    <span>{{ __('cookie-consent::cc.show_details') }}</span>
                    <x-ui.icon name="chevron-down" class="cc-chevron" />
                </button>
                <div id="cc-details" class="cc-details-content">
                    @foreach(['functional', 'statistics', 'marketing'] as $catKey)
                        @if($byCategory->has($catKey))
                        <div class="cc-detail-group">
                            <strong>{{ $categories[$catKey]['label'] }}</strong>
                            <ul>
                                @foreach($byCategory[$catKey] as $svc)
                                    <li>{{ $svc['name'] }}{{ $svc['description'] ? ' — ' . $svc['description'] : '' }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Buttons --}}
        <div class="cc-buttons">
            <button type="button" id="cc-accept-all" class="cc-btn cc-btn-primary">
                {{ __('cookie-consent::cc.accept_all') }}
            </button>
            <button type="button" id="cc-necessary-only" class="cc-btn cc-btn-secondary">
                {{ __('cookie-consent::cc.necessary_only') }}
            </button>
            <button type="button" id="cc-save-selection" class="cc-btn cc-btn-primary" style="display:none;">
                {{ __('cookie-consent::cc.save_selection') }}
            </button>
            <button type="button" id="cc-toggle-options" class="cc-btn cc-btn-outline" aria-expanded="false" aria-controls="cc-options">
                {{ __('cookie-consent::cc.options') }}
            </button>
        </div>
    </div>
</div>

{{-- Re-open button --}}
@if($showReopen)
<button type="button" id="cc-reopen" aria-label="{{ __('cookie-consent::cc.reopen_label') }}">
    <x-ui.icon name="shield-check" />
</button>
@endif

<style>
/* ── Cookie Consent Banner ── */
:root {
    --cc-bg: {{ e($bgColor) }};
    --cc-text: {{ e($textColor) }};
    --cc-btn-bg: {{ e($btnBg) }};
    --cc-btn-text: {{ e($btnText) }};
    --cc-radius: 16px;
    --cc-radius-sm: 10px;
}

#cc-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    backdrop-filter: blur(0);
    -webkit-backdrop-filter: blur(0);
    z-index: 9998;
    pointer-events: none;
    transition: background 0.4s ease, backdrop-filter 0.4s ease, -webkit-backdrop-filter 0.4s ease;
}
#cc-banner.cc-visible #cc-backdrop {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    pointer-events: auto;
}

#cc-panel {
    position: fixed;
    z-index: 9999;
    background: var(--cc-bg);
    color: var(--cc-text);
    font-family: inherit;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
    opacity: 0;
    transition: opacity 0.35s ease, transform 0.35s ease;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* ── Position variants ── */
@php $pos = $position; @endphp
@if($pos === 'bottom')
/* Bottom: floating card with a gap to the edge, centred and no wider than the content */
#cc-panel {
    bottom: 1rem; left: 1rem; right: 1rem;
    margin-inline: auto;
    max-width: 60rem;
    border-radius: var(--cc-radius);
    padding: 1.5rem 1.75rem 1.375rem;
    max-height: calc(100vh - 2rem);
    transform: translateY(20px);
}
#cc-banner.cc-visible #cc-panel { opacity: 1; transform: translateY(0); }
@elseif($pos === 'modal')
#cc-panel {
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) scale(0.96);
    border-radius: var(--cc-radius);
    padding: 2rem;
    max-width: 480px;
    width: calc(100% - 2rem);
    max-height: 85vh;
}
#cc-banner.cc-visible #cc-panel { opacity: 1; transform: translate(-50%, -50%) scale(1); }
@else {{-- corner --}}
#cc-panel {
    bottom: 1.25rem; left: 1.25rem;
    border-radius: var(--cc-radius);
    padding: 1.5rem;
    max-width: 400px;
    width: calc(100% - 2.5rem);
    max-height: 85vh;
    transform: translateY(12px);
}
#cc-banner.cc-visible #cc-panel { opacity: 1; transform: translateY(0); }
@endif

/* ── Header ── */
.cc-header {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 0.75rem;
}
.cc-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: color-mix(in oklab, var(--cc-btn-bg) 28%, transparent);
    color: color-mix(in oklab, var(--cc-btn-bg) 40%, white);
    flex-shrink: 0;
}
.cc-icon svg { width: 20px; height: 20px; }
#cc-reopen svg { width: 18px; height: 18px; }
.cc-title {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.01em;
}

/* ── Text & links ── */
.cc-text {
    font-size: 0.875rem;
    line-height: 1.65;
    opacity: 0.8;
    margin: 0 0 0.375rem 0;
}
.cc-links {
    margin-bottom: 1rem;
    font-size: 0.75rem;
}
.cc-links a {
    color: var(--cc-text);
    opacity: 0.6;
    text-decoration: none;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    transition: opacity 0.2s;
}
.cc-links a:hover { opacity: 1; }
.cc-link-sep { opacity: 0.3; margin: 0 0.375rem; }

/* ── Options panel (hidden by default) ── */
.cc-options {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.35s ease, margin 0.35s ease;
    margin: 0;
}
.cc-options > * {
    overflow: hidden;
}
.cc-options.cc-open {
    grid-template-rows: 1fr;
    margin-bottom: 0.75rem;
}

/* ── Categories ── */
.cc-categories {
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: rgba(255, 255, 255, 0.04);
    border-radius: var(--cc-radius-sm);
    overflow: hidden;
}
.cc-category {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.02);
    transition: background 0.15s;
}
.cc-category:hover { background: rgba(255, 255, 255, 0.06); }
.cc-category-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}
.cc-category-label {
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: -0.005em;
}
.cc-category-desc {
    font-size: 0.6875rem;
    opacity: 0.5;
    line-height: 1.4;
}
.cc-category-services {
    font-size: 0.625rem;
    opacity: 0.35;
    margin-top: 1px;
}
.cc-category-toggle { flex-shrink: 0; }
.cc-always-on {
    font-size: 0.6875rem;
    opacity: 0.45;
    font-weight: 500;
    white-space: nowrap;
}

/* ── Toggle switch ── */
.cc-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    cursor: pointer;
}
.cc-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
.cc-slider {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.12);
    border-radius: 11px;
    transition: background 0.25s ease;
}
.cc-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.cc-switch input:checked + .cc-slider {
    background: var(--cc-btn-bg);
}
.cc-switch input:checked + .cc-slider::after {
    transform: translateX(18px);
}
.cc-switch input:focus-visible + .cc-slider {
    outline: 2px solid var(--cc-btn-bg);
    outline-offset: 2px;
}

/* ── Details ── */
.cc-details-section { margin-top: 0.5rem; }
.cc-details-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    background: none;
    border: none;
    color: var(--cc-text);
    opacity: 0.5;
    font-size: 0.6875rem;
    cursor: pointer;
    padding: 0.25rem 0;
    transition: opacity 0.2s;
}
.cc-details-toggle:hover { opacity: 0.8; }
.cc-chevron { transition: transform 0.25s ease; }
.cc-details-toggle.cc-open .cc-chevron { transform: rotate(180deg); }
.cc-details-content {
    display: none;
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: var(--cc-radius-sm);
    font-size: 0.6875rem;
    opacity: 0.7;
    line-height: 1.5;
}
.cc-detail-group { margin-bottom: 0.5rem; }
.cc-detail-group:last-child { margin-bottom: 0; }
.cc-detail-group strong { font-weight: 600; }
.cc-detail-group ul {
    margin: 0.25rem 0 0 1rem;
    padding: 0;
    list-style: disc;
}
.cc-detail-group li { margin-bottom: 0.125rem; }

/* ── Buttons ── */
.cc-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}
.cc-btn {
    font-family: inherit;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    letter-spacing: -0.005em;
    padding: 0.65rem 1.25rem;
    border-radius: var(--cc-radius-sm);
}
.cc-btn-primary {
    flex: 1;
    min-width: 100px;
    background: var(--cc-btn-bg);
    color: var(--cc-btn-text);
    box-shadow: 0 8px 20px -8px var(--cc-btn-bg);
}
.cc-btn-primary:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}
.cc-btn-secondary {
    flex: 1;
    min-width: 100px;
    background: rgba(255, 255, 255, 0.08);
    color: var(--cc-text);
}
.cc-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.14);
}
.cc-btn-outline {
    flex: 1;
    min-width: 100px;
    background: transparent;
    color: var(--cc-text);
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.cc-btn-outline:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.25);
}

/* ── Reopen button ── */
#cc-reopen {
    display: none;
    position: fixed;
    bottom: 1.25rem;
    {{ $reopenPosition === 'right' ? 'right' : 'left' }}: 1.25rem;
    z-index: 9990;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    background: var(--cc-bg);
    color: var(--cc-text);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
#cc-reopen:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.08) inset;
}

/* ── Mobile ── */
@media (max-width: 480px) {
    #cc-panel { padding: 1.25rem 1rem 1rem !important; }
    .cc-buttons { flex-direction: column; }
    .cc-btn { min-width: 0 !important; }
    .cc-category { padding: 0.5rem 0.625rem; }
}
</style>

<script @cspNonce>
(function() {
    var banner = document.getElementById('cc-banner');
    var reopenBtn = document.getElementById('cc-reopen');
    var optionsPanel = document.getElementById('cc-options');
    var optionsBtn = document.getElementById('cc-toggle-options');
    var saveBtn = document.getElementById('cc-save-selection');
    var acceptBtn = document.getElementById('cc-accept-all');
    var necessaryBtn = document.getElementById('cc-necessary-only');
    var LIFETIME = {{ $lifetime }};
    var optionsOpen = false;

    function setCookie(value) {
        var d = new Date();
        d.setTime(d.getTime() + (LIFETIME * 86400000));
        document.cookie = 'cc_consent=' + encodeURIComponent(JSON.stringify(value))
            + ';path=/;expires=' + d.toUTCString()
            + ';SameSite=Lax'
            + (location.protocol === 'https:' ? ';Secure' : '');
    }

    function getCookie() {
        var m = document.cookie.match(/cc_consent=([^;]+)/);
        if (m) try { return JSON.parse(decodeURIComponent(m[1])); } catch(e) {}
        return null;
    }

    function showBanner() {
        banner.style.display = 'block';
        if (reopenBtn) reopenBtn.style.display = 'none';
        void banner.offsetHeight;
        banner.classList.add('cc-visible');
    }

    function hideBanner() {
        banner.classList.remove('cc-visible');
        setTimeout(function() { banner.style.display = 'none'; }, 400);
        if (reopenBtn) reopenBtn.style.display = 'flex';
    }

    function toggleOptions() {
        optionsOpen = !optionsOpen;
        optionsPanel.classList.toggle('cc-open', optionsOpen);
        optionsBtn.setAttribute('aria-expanded', optionsOpen ? 'true' : 'false');

        if (optionsOpen) {
            // Show save, hide accept all + necessary only
            saveBtn.style.display = '';
            acceptBtn.style.display = 'none';
            necessaryBtn.style.display = 'none';
        } else {
            // Show accept all + necessary only, hide save
            saveBtn.style.display = 'none';
            acceptBtn.style.display = '';
            necessaryBtn.style.display = '';
        }
    }

    var consent = getCookie();

    if (!consent) {
        showBanner();
    } else {
        banner.style.display = 'none';
        if (reopenBtn) reopenBtn.style.display = 'flex';
        ['functional','statistics','marketing'].forEach(function(cat) {
            var t = document.getElementById('cc-toggle-' + cat);
            if (t) t.checked = !!consent[cat];
        });
    }

    function save(cats) {
        cats.ts = Math.floor(Date.now() / 1000);
        cats.necessary = true;
        setCookie(cats);
        location.reload();
    }

    acceptBtn.addEventListener('click', function() {
        save({functional:true, statistics:true, marketing:true});
    });

    necessaryBtn.addEventListener('click', function() {
        save({functional:false, statistics:false, marketing:false});
    });

    saveBtn.addEventListener('click', function() {
        save({
            functional: !!document.getElementById('cc-toggle-functional').checked,
            statistics: !!document.getElementById('cc-toggle-statistics').checked,
            marketing:  !!document.getElementById('cc-toggle-marketing').checked
        });
    });

    optionsBtn.addEventListener('click', toggleOptions);

    if (reopenBtn) {
        reopenBtn.addEventListener('click', function() {
            // Reset to initial state when reopening
            if (optionsOpen) toggleOptions();
            showBanner();
        });
    }

    var detailsBtn = document.getElementById('cc-show-details');
    var detailsPanel = document.getElementById('cc-details');
    if (detailsBtn && detailsPanel) {
        var showText = {!! json_encode(__('cookie-consent::cc.show_details')) !!};
        var hideText = {!! json_encode(__('cookie-consent::cc.hide_details')) !!};
        detailsBtn.addEventListener('click', function() {
            var hidden = detailsPanel.style.display === 'none' || !detailsPanel.style.display;
            detailsPanel.style.display = hidden ? 'block' : 'none';
            detailsBtn.querySelector('span').textContent = hidden ? hideText : showText;
            detailsBtn.classList.toggle('cc-open', hidden);
            detailsBtn.setAttribute('aria-expanded', hidden ? 'true' : 'false');
        });
    }
})();
</script>
