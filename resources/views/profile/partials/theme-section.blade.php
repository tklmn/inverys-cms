@php
    $tm = app(\App\Services\ThemeManager::class);
    $allThemes = $tm->all();
    $globalDefault = $tm->getActive() ?? 'default';
    // Only the user's own choice — empty means "site default"
    $own = auth()->user()->ownSettings(['theme', 'frontend_theme']);
    $ownFrontendTheme = $own['frontend_theme'] && $tm->has($own['frontend_theme']) ? $own['frontend_theme'] : '';
    $effectiveFrontendTheme = $ownFrontendTheme !== '' ? $ownFrontendTheme : $globalDefault;
    $supportsDarkMode = ($tm->get($effectiveFrontendTheme)['supports_dark_mode'] ?? true);
    $mode = in_array($own['theme'], ['light', 'dark'], true) ? $own['theme'] : '';
    $siteMode = setting('theme') === 'dark' ? 'dark' : 'light';
@endphp

{{-- Every choice saves immediately (data-autosubmit, shared/csp-actions.js) --}}
<div class="flex flex-col gap-4">
    <p class="ui-help">{{ __('translation.users.theme_appearance_description') }}</p>

    {{--
        Light/dark always applies to the admin area — the choice stays available
        even when the website theme has no dark mode.
    --}}
    <form method="post" action="{{ route('profile.theme.update') }}">
        @csrf

        <p class="ui-label mb-2">{{ __('translation.users.theme') }}</p>

        <div class="flex flex-wrap gap-2">
            @foreach(['light' => 'sun', 'dark' => 'moon', '' => 'monitor'] as $value => $icon)
                <label class="ui-choice" @if($mode === $value) data-selected @endif>
                    <input type="radio" name="theme" value="{{ $value }}" class="sr-only" data-autosubmit @checked($mode === $value)>
                    <x-ui.icon :name="$icon" class="text-lg" />
                    <span>{{ $value === '' ? __('translation.users.theme_site_default', ['value' => __('translation.users.theme_'.$siteMode)]) : __('translation.users.theme_'.$value) }}</span>
                </label>
            @endforeach
        </div>

        <p class="ui-help mt-2">{{ __('translation.users.theme_explanation') }}</p>

        @if(! $supportsDarkMode)
            <p class="ui-help mt-2">{{ __('translation.users.theme_no_dark_frontend', ['theme' => __($tm->get($effectiveFrontendTheme)['name'] ?? $effectiveFrontendTheme)]) }}</p>
        @endif
    </form>

    @if(count($allThemes) > 1)
        <form method="post" action="{{ route('profile.theme.update') }}">
            @csrf

            <x-ui.field name="frontend_theme" :label="__('translation.users.frontend_theme')" :help="__('translation.users.frontend_theme_hint')">
                <x-ui.select name="frontend_theme" id="frontend_theme" data-autosubmit>
                    <option value="" @selected($ownFrontendTheme === '')>{{ __('translation.users.site_default', ['value' => __($allThemes[$globalDefault]['name'] ?? $globalDefault)]) }}</option>
                    @foreach($allThemes as $slug => $theme)
                        <option value="{{ $slug }}" @selected($ownFrontendTheme === $slug)>{{ __($theme['name']) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </form>
    @endif
</div>
