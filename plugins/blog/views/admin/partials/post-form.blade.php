@props(['post' => null, 'categories'])

@php
    $editing = $post !== null;
    $selected = array_map('intval', (array) old('categories', $editing ? $post->categories->pluck('id')->toArray() : []));

    $status = old('post_status', $editing
        ? ($post->is_active && $post->published_at && $post->published_at <= now() ? 'published'
            : ($post->is_active && $post->published_at ? 'scheduled' : 'draft'))
        : 'draft');

    $suffix = $editing ? 'edit' : 'create';
    $pickerId = 'blog-media-'.$suffix;

    // Multilingual: original or translation (only when editing)
    $i18n = \App\Support\EditLocale::form($post);

    $featured = old('featured_image', $post?->featured_image);
@endphp

<x-ui.translation-note :form="$i18n" />

<form id="post-form"
    action="{{ $editing ? route('backend.blog.update', $post) : route('backend.blog.store') }}"
    method="POST"
    class="flex flex-col gap-4"
>
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    @if($i18n->locale)
        <input type="hidden" name="_lang" value="{{ $i18n->locale }}">
    @endif

    {{--
        Two columns as for users: the post's text on the left,
        everything that classifies it on the right (publishing, image, SEO).
    --}}
    <div class="ui-split">
        <div class="ui-split-main">
            <x-ui.card :title="__('blog::blog.general')" icon="file-text">
                <div class="ui-grid">
                    <x-ui.field name="title" :label="__('blog::blog.post_title')">
                        <x-ui.input id="title" name="title" :value="$i18n->value('title')" :placeholder="$i18n->placeholder('title')" :required="! $i18n->shared" autofocus />
                    </x-ui.field>

                    <x-ui.field name="slug" :label="__('blog::blog.slug')" optional>
                        <x-ui.input id="slug" name="slug" :value="$i18n->value('slug')" :placeholder="$i18n->placeholder('slug', __('blog::blog.slug_auto'))" />
                    </x-ui.field>

                    <x-ui.field name="excerpt" :label="__('blog::blog.excerpt')" class="ui-grid-full" optional>
                        <x-ui.textarea id="excerpt" name="excerpt" rows="3" :placeholder="$i18n->placeholder('excerpt')">{{ $i18n->value('excerpt') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('blog::blog.content')" icon="align-left" tone="violet" flush>
                <div class="ui-card-body">
                    @include('backend.partials.tinymce-editor', [
                        'name' => 'content',
                        'value' => $i18n->value('content'),
                    ])
                </div>
            </x-ui.card>
        </div>

        <div class="ui-split-aside">
            <x-ui.card :title="__('blog::blog.publishing')" icon="calendar" tone="teal">
                <div class="flex flex-col gap-4">
                    <x-ui.field name="author_id" :label="__('blog::blog.author')">
                        <x-ui.select id="author_id" name="author_id" :disabled="$i18n->shared">
                            <option value="">—</option>
                            @foreach(\App\Models\User::orderBy('name')->get() as $user)
                                <option value="{{ $user->id }}" @selected(old('author_id', $post?->author_id ?? auth()->id()) == $user->id)>
                                    {{ $user->full_name }}
                                </option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="post_status" :label="__('blog::blog.status')">
                        <x-ui.select id="post_status" name="post_status" data-blog-status :disabled="$i18n->shared">
                            @permission('draft-post')
                                <option value="draft" @selected($status === 'draft')>{{ __('blog::blog.draft') }}</option>
                            @endpermission
                            @permission('publish-post')
                                <option value="published" @selected($status === 'published')>{{ __('blog::blog.published') }}</option>
                            @endpermission
                            @permission('schedule-post')
                                <option value="scheduled" @selected($status === 'scheduled')>{{ __('blog::blog.scheduled') }}</option>
                            @endpermission
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field
                        name="published_at"
                        :label="__('blog::blog.published_at')"
                        data-blog-schedule
                        @class(['hidden' => $status !== 'scheduled' && ! $errors->has('published_at')])
                    >
                        <x-ui.input
                            id="published_at"
                            name="published_at"
                            type="datetime-local"
                            :disabled="$i18n->shared"
                            :value="old('published_at', $post?->published_at?->format('Y-m-d\TH:i'))"
                        />
                    </x-ui.field>

                    {{-- Categories as a list of switches instead of a multi-select (no Ctrl-click) --}}
                    <div class="ui-field">
                        <x-input-label :value="__('blog::blog.categories')" />

                        @if($categories->isEmpty())
                            <p class="ui-help">{{ __('blog::blog.no_categories') }}</p>
                        @else
                            <div class="ui-choices">
                                @foreach($categories as $category)
                                    <x-ui.switch-row
                                        name="categories[]"
                                        multiple
                                        :bleed="false"
                                        :label="$category->name"
                                        :on-value="$category->id"
                                        :checked="in_array($category->id, $selected, true)"
                                        :input-id="'category_'.$category->id"
                                        :disabled="$i18n->shared"
                                    />
                                @endforeach
                            </div>
                        @endif

                        <x-input-error :messages="$errors->get('categories')" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('blog::blog.featured_image')" icon="image" tone="violet">
                <div class="flex flex-col gap-3" data-blog-featured>
                    <input type="hidden" id="featured_image" name="featured_image" value="{{ $featured }}" @disabled($i18n->shared)>

                    {{-- Preview: image or placeholder, both in the markup so the script only toggles --}}
                    <img
                        src="{{ $featured ? asset('storage/'.$featured) : '' }}"
                        alt=""
                        data-blog-featured-preview
                        @class(['aspect-video w-full rounded-lg border border-line object-cover', 'hidden' => ! $featured])
                    >
                    <div data-blog-featured-empty @class(['hidden' => $featured])>
                        <x-ui.empty icon="image-off">{{ __('blog::blog.no_featured_image') }}</x-ui.empty>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button
                            icon="image"
                            data-media-picker-open="{{ $pickerId }}"
                            :disabled="$i18n->shared"
                        >
                            {{ __('blog::blog.choose_image') }}
                        </x-ui.button>

                        <x-ui.button
                            variant="danger"
                            icon="trash-2"
                            data-blog-featured-remove
                            :data-reason="__('blog::blog.no_featured_image')"
                            :disabled="$i18n->shared || ! $featured"
                            :disabled-reason="__('blog::blog.no_featured_image')"
                        >
                            {{ __('blog::blog.remove_image') }}
                        </x-ui.button>
                    </div>

                    <x-input-error :messages="$errors->get('featured_image')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('blog::blog.seo')" icon="search" tone="caution">
                <div class="flex flex-col gap-4">
                    <x-ui.field name="meta_title" :label="__('blog::blog.meta_title')" optional>
                        <x-ui.input id="meta_title" name="meta_title" :value="$i18n->value('meta_title')" :placeholder="$i18n->placeholder('meta_title')" />
                    </x-ui.field>

                    <x-ui.field name="meta_description" :label="__('blog::blog.meta_description')" optional>
                        <x-ui.textarea id="meta_description" name="meta_description" rows="3" :placeholder="$i18n->placeholder('meta_description')">{{ $i18n->value('meta_description') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <x-ui.button size="md" :href="route('backend.blog.index')">{{ __('translation.cancel') }}</x-ui.button>
        <x-ui.button variant="primary" size="md" icon="check" type="submit">
            {{ $editing ? __('translation.update') : __('translation.create') }}
        </x-ui.button>
    </div>
</form>

{{-- Media picker for the featured image --}}
<x-ui.media-picker :id="$pickerId" target="featured_image" />

<script @cspNonce>
(function () {
    // The date field only appears when the post is scheduled
    var status = document.querySelector('[data-blog-status]');
    var schedule = document.querySelector('[data-blog-schedule]');

    if (status && schedule) {
        status.addEventListener('change', function () {
            schedule.classList.toggle('hidden', status.value !== 'scheduled');
        });
    }

    // Featured image: update the preview immediately, remove only with an image
    var box = document.querySelector('[data-blog-featured]');
    if (!box) return;

    var input = document.getElementById('featured_image');
    var preview = box.querySelector('[data-blog-featured-preview]');
    var empty = box.querySelector('[data-blog-featured-empty]');
    var remove = box.querySelector('[data-blog-featured-remove]');
    var picker = document.getElementById(@json($pickerId));

    function render(src) {
        var has = input.value !== '';
        preview.classList.toggle('hidden', !has);
        empty.classList.toggle('hidden', has);
        if (src !== undefined) preview.src = src;
        if (input.disabled) return;
        remove.disabled = !has;
        remove.setAttribute('aria-disabled', has ? 'false' : 'true');
        if (has) remove.removeAttribute('title');
        else remove.title = remove.dataset.reason;
    }

    if (picker) {
        picker.addEventListener('media-picker:select', function (event) {
            var item = (event.detail.items || [])[0];
            if (item) render(item.url || item.thumbnail);
        });
    }

    // A button that starts disabled has no type="button" — prevent submitting
    remove.addEventListener('click', function (event) {
        event.preventDefault();
        input.value = '';
        preview.removeAttribute('src');
        render();
    });
}());
</script>
