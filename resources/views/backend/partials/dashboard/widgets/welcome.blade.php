{{--
    Greeting as a gradient banner — the colourful entry into the backend.
    Next to it the two most common actions, so the banner is not just decoration.
--}}
<div class="ui-hero">
    <div class="min-w-0">
        {{-- Weekday + long date in the language's order (isoFormat "LL"):
             "Friday, September 25, 2026" / "vendredi, 25 septembre 2026" --}}
        <p class="ui-hero-kicker">{{ now()->locale(app()->getLocale())->isoFormat('dddd, LL') }}</p>
        <h2 class="ui-hero-title">{{ __('translation.dashboard.welcome') }}, {{ Auth::user()->name }}</h2>
        <p class="ui-hero-text">{{ __('translation.dashboard.welcome_message') }}</p>
    </div>

    <div class="ui-hero-actions">
        @permission('create-page')
            <a href="{{ route('backend.pages.create') }}" class="ui-hero-btn">
                <x-ui.icon name="plus" /> {{ __('translation.dashboard.create_page') }}
            </a>
        @endpermission
        <a href="{{ url('/') }}" class="ui-hero-btn ui-hero-btn-ghost" target="_blank" rel="noopener">
            <x-ui.icon name="external-link" /> {{ __('translation.dashboard.view_site') }}
        </a>
    </div>
</div>
