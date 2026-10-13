<footer class="bg-gray-800 dark:bg-slate-900 text-white">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        {{-- Region: footer_content --}}
        @if(theme_region('footer_content'))
            <div class="mb-8 footer-region [&_a]:text-gray-300 [&_a:hover]:text-white [&_a]:underline-offset-2 [&_h2]:text-white [&_h3]:text-white [&_h4]:text-white [&_p]:text-gray-400">
                {!! theme_region('footer_content') !!}
            </div>
        @endif

        <!-- Copyright -->
        <div class="pt-6 border-t border-gray-700 text-center text-gray-400">
            <p>{{ theme('footer_text', '© ' . date('Y') . ' ' . __('translation.app_name') . '. ' . __('All rights reserved.')) }}</p>
        </div>
    </div>
</footer>
