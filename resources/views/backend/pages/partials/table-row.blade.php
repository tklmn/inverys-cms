@php
    $level = $level ?? 0;
    $childCount = $page->children_count ?? $page->children->count();
@endphp

<x-ui.list-row
    :title="$page->title"
    icon="file-text"
    :href="$page->deleted_at ? null : route('backend.pages.edit', $page)"
    style="{{ $level > 0 ? '--ui-list-indent: '.($level * 1.75).'rem' : '' }}"
    class="{{ $level > 0 ? 'ui-list-row-nested' : '' }}"
>
    <x-slot:meta>
        <span>/{{ $page->is_root ? '' : $page->slug }}</span>
        @if($page->is_root)<span>{{ __('translation.pages.root') }}</span>@endif
        <span>{{ format_datetime($page->updated_at) }}</span>
        {{-- Only mention subpages if there are any — a "0" column in almost every row said nothing --}}
        @if($childCount > 0)<span>{{ trans_choice('translation.pages.subpages_count', $childCount, ['count' => $childCount]) }}</span>@endif
        {{-- Not in the menu: extra note in the meta line instead of a second badge —
             two stacked badges made exactly these rows taller than the others --}}
        @if($page->hide_in_menu)<span class="ui-meta-flag"><x-ui.icon name="eye-off" />{{ __('translation.pages.hidden') }}</span>@endif
        <x-ui.translation-status :model="$page" />
    </x-slot:meta>

    <x-slot:cols>
        <x-ui.list-col>
            @if($page->deleted_at)
                <x-ui.badge tone="critical">{{ __('translation.pages.trashed') }}</x-ui.badge>
            @elseif($page->is_active)
                <x-ui.badge tone="positive">{{ __('translation.pages.active') }}</x-ui.badge>
            @else
                <x-ui.badge>{{ __('translation.pages.inactive') }}</x-ui.badge>
            @endif
        </x-ui.list-col>
    </x-slot:cols>

    <x-slot:actions>
        @if($page->deleted_at)
            <form action="{{ route('backend.pages.restore', $page->id) }}" method="POST">
                @csrf
                <x-ui.button variant="ghost" icon="rotate-ccw" type="submit" :label="__('translation.restore')" />
            </form>
        @else
            <x-ui.button
                variant="ghost"
                icon="external-link"
                :label="__('translation.view')"
                :href="$page->is_root ? route('home') : route('page.show', $page->slug)"
                target="_blank"
                rel="noopener noreferrer"
            />

            @permission('edit-page')
                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.pages.edit', $page)" />
            @endpermission

            @permission('delete-page')
                {{-- The homepage stays visible but disabled --}}
                @if($page->is_root)
                    <x-ui.button
                        variant="danger"
                        icon="trash-2"
                        :label="__('translation.delete')"
                        :disabled="true"
                        :disabled-reason="__('translation.pages.root_not_deletable')"
                    />
                @else
                    <form id="delete-form-page-{{ $page->id }}" action="{{ route('backend.pages.destroy', $page) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <x-ui.button
                            variant="danger"
                            icon="trash-2"
                            :label="__('translation.delete')"
                            data-delete-modal="{{ json_encode(['formId' => 'delete-form-page-'.$page->id]) }}"
                        />
                    </form>
                @endif
            @endpermission
        @endif
    </x-slot:actions>
</x-ui.list-row>

@if($page->children && $page->children->count())
    @foreach($page->children as $child)
        @include('backend.pages.partials.table-row', ['page' => $child, 'level' => $level + 1])
    @endforeach
@endif
