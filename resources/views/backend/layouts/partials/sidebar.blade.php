{{--
    Side navigation. Structure, permissions and active state come from
    \App\Support\Navigation — this view only renders them.
--}}
@php($navGroups = \App\Support\Navigation::groups())

<div
    class="fixed inset-0 z-40 bg-ink-950/50 lg:hidden"
    data-nav-close
    aria-hidden="true"
    id="nav-scrim"
></div>

<nav class="nav-shell fixed inset-y-0 start-0 z-50 lg:static" id="nav" aria-label="{{ __('translation.navigation.main') }}">
    <div class="nav-brand">
        <a href="{{ route('dashboard') }}" class="nav-brand-link">
            {{-- Expanded: the wordmark; collapsed there is only room for the icon (brand guide) --}}
            <x-brand-wordmark class="nav-label h-[1.125rem] w-auto text-[#F8FAFC]" />
            <span class="nav-label brand-product" aria-hidden="true">CMS</span>
            <x-application-logo class="nav-mark h-7 w-7 shrink-0 text-[#F8FAFC]" />
            <span class="sr-only">{{ __('translation.app_name') }}</span>
        </a>
        <button type="button" class="ui-btn ui-btn-sm ui-btn-icon lg:hidden text-nav-fg" data-nav-close aria-label="{{ __('translation.close') }}">
            <x-ui.icon name="x" />
        </button>
    </div>

    <div class="nav-scroll">
        @foreach($navGroups as $group)
            <div class="nav-group">
                @if($group['label'])
                    <p class="nav-group-label">{{ __($group['label']) }}</p>
                @endif

                @foreach($group['items'] as $index => $item)
                    @php($groupId = 'nav-sub-'.$loop->parent->index.'-'.$index)

                    @if($item['children'])
                        <button
                            type="button"
                            class="nav-item w-full"
                            data-nav-group="{{ $groupId }}"
                            aria-controls="{{ $groupId }}"
                            aria-expanded="{{ $item['state'] ? 'true' : 'false' }}"
                            @if($item['state']) data-{{ $item['state'] }} @endif
                        >
                            @if($item['icon'])
                                <span class="nav-item-icon"><x-ui.nav-icon :name="$item['icon']" /></span>
                            @endif
                            <span class="nav-label">{{ __($item['label']) }}</span>
                            <x-ui.icon name="chevron-right" class="nav-toggle-icon" />
                        </button>

                        <div class="nav-sub" id="{{ $groupId }}" @if($item['state']) data-open @endif>
                            <div class="nav-sub-inner">
                                @foreach($item['children'] as $child)
                                    <a
                                        href="{{ $child['url'] }}"
                                        class="nav-item"
                                        @if($child['state']) data-{{ $child['state'] }} @endif
                                        @if($child['state'] === 'current') aria-current="page" @endif
                                    >
                                        <span class="nav-label">{{ __($child['label']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ $item['url'] }}"
                            class="nav-item"
                            @if($item['state']) data-{{ $item['state'] }} @endif
                            @if($item['state'] === 'current') aria-current="page" @endif
                        >
                            @if($item['icon'])
                                <span class="nav-item-icon"><x-ui.nav-icon :name="$item['icon']" /></span>
                            @endif
                            <span class="nav-label">{{ __($item['label']) }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="nav-foot">
        <button type="button" class="nav-item w-full" data-nav-collapse>
            <span class="nav-item-icon"><x-ui.icon name="panel-left" /></span>
            <span class="nav-label">{{ __('translation.dashboard.collapse_sidebar') }}</span>
        </button>
    </div>
</nav>
