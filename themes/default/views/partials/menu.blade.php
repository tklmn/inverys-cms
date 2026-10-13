{{--
    Main menu (desktop). Submenus open on hover and on keyboard focus purely
    via CSS (.site-nav-item:focus-within) — no JavaScript needed.

    If the menu does not fit on the line (many or long entries, e.g. in
    German), the trailing entries move into "More" (script in
    layouts/navigation). The menu button is reserved for phones.

    $pages: top-level pages; external links (including plugin pages such as
    the blog) are pages with an external_url.
--}}
@php
    $linkFor = function ($page) {
        if (! empty($page->external_url)) {
            return $page->external_url;
        }

        return $page->is_root ? route('home') : route('page.show', $page->slug);
    };

    $isActive = function ($page) {
        if (! empty($page->external_url)) {
            $path = trim((string) parse_url($page->external_url, PHP_URL_PATH), '/');
            $base = trim((string) parse_url(url('/'), PHP_URL_PATH), '/');
            $path = trim($base !== '' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path, '/');
            // Strip the language prefix (/de/blog) — the request runs without it internally
            $first = explode('/', $path, 2);
            if (count($first) === 2 && app(\App\Support\Locales::class)->has($first[0])) {
                $path = $first[1];
            }

            return $path !== '' && (request()->is($path) || request()->is($path.'/*'));
        }

        return $page->is_root ? request()->is('/') : (request()->is($page->slug) || request()->is($page->slug.'/*'));
    };
@endphp

<ul class="site-nav-list">
    @foreach($pages as $page)
        @php
            $children = empty($page->external_url)
                ? $page->children->where('is_active', true)->where('hide_in_menu', false)
                : collect();
            $active = $isActive($page) || $children->contains(fn ($child) => $isActive($child));
            $newWindow = ! empty($page->external_url) && $page->external_url_new_window;
        @endphp

        <li class="site-nav-item {{ $active ? 'is-active' : '' }}" @if($children->isNotEmpty()) data-dropdown-hover @endif>
            @if($children->isNotEmpty())
                <a href="{{ $linkFor($page) }}" class="site-nav-link" aria-haspopup="true" @if($isActive($page)) aria-current="page" @endif>
                    {{ $page->nav_title ?: $page->title }}
                    <x-ui.icon name="chevron-down" class="site-nav-chevron" />
                </a>
                <ul class="site-nav-sub">
                    @foreach($children as $child)
                        <li>
                            <a href="{{ $linkFor($child) }}"
                               @if($isActive($child)) aria-current="page" @endif
                               @if(! empty($child->external_url) && $child->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                                {{ $child->nav_title ?: $child->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <a href="{{ $linkFor($page) }}" class="site-nav-link"
                   @if($active) aria-current="page" @endif
                   @if($newWindow) target="_blank" rel="noopener noreferrer" @endif>
                    {{ $page->nav_title ?: $page->title }}
                </a>
            @endif
        </li>
    @endforeach

    <li class="site-nav-item site-nav-more" data-nav-more data-dropdown-hover hidden>
        <button type="button" class="site-nav-link" aria-haspopup="true" aria-expanded="false">
            {{ __('translation.frontend.nav_more') }}
            <x-ui.icon name="chevron-down" class="site-nav-chevron" />
        </button>
        <ul class="site-nav-sub site-nav-sub-end" data-nav-more-list></ul>
    </li>
</ul>
