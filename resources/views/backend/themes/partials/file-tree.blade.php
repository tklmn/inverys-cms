@foreach($nodes as $node)
    @if($node['type'] === 'dir')
        <div class="mb-0.5">
            <button data-folder-toggle
                    class="flex items-center gap-1.5 w-full px-2 py-1 rounded text-fg-muted hover:bg-surface-sunken text-left"
                    style="padding-left: {{ $depth * 0.75 + 0.5 }}rem">
                {{-- Both states in the markup, the script toggles between them --}}
                <x-ui.icon name="folder-open" class="folder-icon folder-icon-open text-fg-subtle text-xs" />
                <x-ui.icon name="folder" class="folder-icon folder-icon-closed hidden text-fg-subtle text-xs" />
                <span class="truncate">{{ $node['name'] }}</span>
            </button>
            <div>
                @include('backend.themes.partials.file-tree', ['nodes' => $node['children'], 'depth' => $depth + 1])
            </div>
        </div>
    @else
        <button data-file-path="{{ $node['path'] }}"
                class="flex items-center gap-1.5 w-full px-2 py-1 rounded text-fg-muted hover:bg-surface-sunken text-left transition-colors"
                style="padding-left: {{ $depth * 0.75 + 0.5 }}rem">
            <x-ui.icon name="file-code" class="text-fg-subtle text-xs" />
            <span class="truncate">{{ $node['name'] }}</span>
        </button>
    @endif
@endforeach
