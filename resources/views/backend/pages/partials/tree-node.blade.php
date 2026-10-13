{{-- A node in the page tree. pages/tree.js needs the classes page-item/page-children/drag-handle (dragging and saving). --}}
<li class="page-item{{ ! $page->is_active ? ' inactive' : '' }}{{ $page->is_root ? ' root-page' : '' }}" data-id="{{ $page->id }}">
    <div class="page-item-content ui-tree-item">
        <span class="drag-handle ui-tree-handle" title="{{ __('translation.pages.drag_to_move') }}">
            <x-ui.icon name="grip-vertical" />
        </span>
        <span class="ui-list-lead"><x-ui.icon :name="$page->is_root ? 'house' : 'file-text'" /></span>
        {{-- Title and flags share a wrapping column; the actions stay on the right --}}
        <span class="ui-tree-text">
            <span class="page-title ui-tree-title">{{ $page->title }}</span>

            {{-- The same flags as in the list --}}
            <span class="flex flex-wrap gap-1">
                @if($page->is_root)<x-ui.badge tone="accent">{{ __('translation.pages.root') }}</x-ui.badge>@endif
                @unless($page->is_active)<x-ui.badge>{{ __('translation.pages.inactive') }}</x-ui.badge>@endunless
                @if($page->hide_in_menu)<x-ui.badge tone="caution">{{ __('translation.pages.hidden') }}</x-ui.badge>@endif
            </span>
        </span>

        <span class="ui-list-actions ms-auto">
            <x-ui.button variant="ghost" icon="external-link" :label="__('translation.view')" :href="$page->is_root ? route('home') : route('page.show', $page->slug)" target="_blank" rel="noopener noreferrer" />
            @permission('edit-page')
                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.pages.edit', $page)" />
            @endpermission
        </span>
    </div>

    {{-- No whitespace, otherwise :empty does not match and the empty list has no height as a drop target --}}
    <ul class="page-children">@foreach($page->children as $child)
        @include('backend.pages.partials.tree-node', ['page' => $child])
    @endforeach</ul>
</li>
