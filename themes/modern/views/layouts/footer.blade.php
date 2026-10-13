<footer class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800">
    <div class="{{ theme('max_width', 'max-w-5xl') }} mx-auto py-12 px-4 sm:px-6 lg:px-8">
        {{-- Region: footer_content --}}
        @if(theme_region('footer_content'))
            <div class="mb-8 footer-region [&_a]:text-gray-600 [&_a:hover]:text-gray-900 dark:[&_a]:text-gray-400 dark:[&_a:hover]:text-white [&_a]:underline-offset-2 [&_h2]:text-gray-800 [&_h3]:text-gray-800 [&_h4]:text-gray-800 dark:[&_h2]:text-white dark:[&_h3]:text-white dark:[&_h4]:text-white [&_p]:text-gray-500 dark:[&_p]:text-gray-400">
                {!! theme_region('footer_content') !!}
            </div>
        @endif

        <!-- Copyright -->
        <div class="pt-6 border-t border-gray-200 dark:border-gray-800 text-center">
            <p class="text-xs text-gray-400">{{ theme('footer_text', '© ' . date('Y') . ' ' . setting('site_name', __('translation.app_name')) . '. ' . __('translation.frontend.all_rights_reserved')) }}</p>
        </div>
    </div>
</footer>
