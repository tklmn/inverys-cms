<ul class="flex flex-wrap items-center gap-x-6 gap-y-1">
    @foreach($pages as $page)
        @php
            $displayTitle = $page->nav_title ?: $page->title;
            $isExternal = !empty($page->external_url);
            $linkUrl = $isExternal
                ? $page->external_url
                : ($page->is_root ? route('home') : route('page.show', $page->slug));
            $linkTarget = ($isExternal && $page->external_url_new_window) ? '_blank' : null;

            // Active logic: is the page itself or a child page active?
            $isLocalExternal = $isExternal && str_starts_with($page->external_url ?? '', '/');
            $isActive = $isLocalExternal
                ? (request()->is(ltrim($page->external_url, '/')) || request()->is(ltrim($page->external_url, '/') . '/*'))
                : (!$isExternal && (
                    ($page->is_root && request()->is('/')) ||
                    request()->is($page->slug) ||
                    request()->is($page->slug . '/*') ||
                    (isset($page->children) && $page->children->where('is_active', true)->pluck('slug')->contains(function($slug) {
                        return request()->is($slug) || request()->is($slug . '/*');
                    }))
                ));
            $liClasses = 'relative flex items-center';
            if($isActive) {
                $liClasses .= ' border-b-2 border-indigo-400 dark:border-indigo-600';
            } else {
                $liClasses .= ' border-b-2 border-transparent';
            }
        @endphp
        <li class="{{ $liClasses }}">
            @if(!$isExternal && $page->children->where('is_active', true)->where('hide_in_menu', false)->count())
                <x-dropdown align="left" width="48">
                    <x-slot name="trigger">
                        <x-menu-trigger :active="$isActive">
                            {{ $displayTitle }}
                            <svg class="inline w-3 h-3 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </x-menu-trigger>
                    </x-slot>
                    <x-slot name="content">
                        <ul class="py-1 min-w-[180px]">
                            @foreach($page->children->where('is_active', true)->where('hide_in_menu', false) as $child)
                                @php
                                    $childTitle = $child->nav_title ?: $child->title;
                                    $childIsExternal = !empty($child->external_url);
                                    $childUrl = $childIsExternal
                                        ? $child->external_url
                                        : ($child->is_root ? route('home') : route('page.show', $child->slug));
                                    $childTarget = ($childIsExternal && $child->external_url_new_window) ? '_blank' : null;
                                @endphp
                                <li>
                                    @if(!$childIsExternal && $child->children->where('is_active', true)->where('hide_in_menu', false)->count())
                                        @include('frontend.partials.menu', ['pages' => collect([$child])])
                                    @else
                                        <x-menu-link :href="$childUrl" :active="!$childIsExternal && ($child->is_root ? request()->is('/') : request()->is($child->slug))" class="block px-4 py-2" :target="$childTarget">
                                            {{ $childTitle }}
                                        </x-menu-link>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-slot>
                </x-dropdown>
            @else
                <x-menu-link :href="$linkUrl" :active="$isActive" :target="$linkTarget">
                    {{ $displayTitle }}
                </x-menu-link>
            @endif
        </li>
    @endforeach
</ul>
