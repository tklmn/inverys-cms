{{-- Preview notice: only for people with edit permission, visitors cannot see the post yet --}}
@php($scheduled = $post->isScheduled())
<div class="site-alert site-alert-warning site-alert-preview" role="status">
    <span class="site-alert-icon"><x-ui.icon :name="$scheduled ? 'clock' : 'eye'" /></span>
    <div class="site-alert-body">
        <p class="site-alert-title">{{ __('blog::blog.preview_title') }}</p>
        <p class="site-alert-text">
            @if($scheduled)
                {{ __('blog::blog.preview_scheduled', ['date' => format_datetime($post->published_at)]) }}
            @else
                {{ __('blog::blog.preview_draft') }}
            @endif
        </p>
    </div>
    @if(Route::has('backend.blog.edit'))
        <a href="{{ route('backend.blog.edit', $post) }}" class="site-btn site-btn-secondary site-alert-action">
            <x-ui.icon name="pencil" /> {{ __('blog::blog.preview_edit') }}
        </a>
    @endif
</div>
