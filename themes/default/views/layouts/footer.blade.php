{{--
    Footer: dark in both modes, with a fine glowing line in the primary
    colour. Content comes from the "footer_content" block region.
--}}
<footer class="site-footer">
    <div class="site-container">
        @if($footerRegion = theme_region('footer_content'))
            <div class="site-footer-region pb-10 pt-14">
                {!! $footerRegion !!}
            </div>
        @endif

        <div class="site-footer-bottom">
            <p>{{ theme('footer_text', '© '.date('Y').' '.setting('site_name', __('translation.app_name')).'. '.__('All rights reserved.')) }}</p>
            {{-- No second "Back to top": the floating button covers that (layouts/app) --}}
        </div>
    </div>
</footer>
