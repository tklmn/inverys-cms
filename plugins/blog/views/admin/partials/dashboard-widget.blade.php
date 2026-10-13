{{-- Blog dashboard tile — same structure as the core tiles. --}}
<x-ui.card class="flex h-full flex-col" flush icon="newspaper" tone="teal">
    <x-slot:head>
        <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.blog.index')">
            {{ __('translation.dashboard.view_all') }}
        </x-ui.button>
    </x-slot:head>

    <x-slot:title>{{ __('blog::blog.dashboard_title') }}</x-slot:title>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <x-ui.list>
            @forelse($blogRecentPosts as $post)
                <x-ui.list-row :title="$post->title" icon="newspaper" :href="route('backend.blog.edit', $post)">
                    <x-slot:meta>
                        <span>{{ $post->updated_at->diffForHumans() }}</span>
                    </x-slot:meta>
                    <x-slot:cols>
                        <x-ui.list-col>
                            @if($post->is_active && $post->published_at)
                                <x-ui.badge tone="positive">{{ __('blog::blog.published') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="caution">{{ __('blog::blog.drafts') }}</x-ui.badge>
                            @endif
                        </x-ui.list-col>
                    </x-slot:cols>
                </x-ui.list-row>
            @empty
                <x-ui.empty icon="scroll-text">{{ __('blog::blog.no_posts') }}</x-ui.empty>
            @endforelse
        </x-ui.list>
    </div>
</x-ui.card>
