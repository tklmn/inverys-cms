@php
    $albumId = $data['album_id'] ?? null;
    $album = null;

    if ($albumId) {
        $album = \Plugins\Gallery\Models\GalleryAlbum::where('id', $albumId)->where('is_active', true)->first();
    }

    if (!$album) return;

    // Resolve settings: global → album overrides
    $globalSettings = cms()->settings()->getByPrefix('gallery.');
    $albumSettings = $album->settings ?? [];

    $resolve = function ($key, $default) use ($globalSettings, $albumSettings) {
        // Album override takes precedence
        if (isset($albumSettings[$key]) && $albumSettings[$key] !== '') return $albumSettings[$key];
        // Global setting
        return $globalSettings[$key] ?? $default;
    };

    $columns = (int) $resolve('columns', 3);
    $lightbox = (bool) $resolve('lightbox', true);
    $imagesPerPage = (int) $resolve('images_per_page', 12);
    $pagination = (bool) $resolve('pagination', true);
    $gap = (int) $resolve('gap', 4);
    $aspectRatio = $resolve('aspect_ratio', 'cover');
    $showCaptions = (bool) $resolve('show_captions', true);
    $lazyLoading = (bool) $resolve('lazy_loading', true);

    // Fetch images
    $imagesQuery = $album->images();
    $totalImages = $imagesQuery->count();

    if ($imagesPerPage > 0 && $pagination) {
        $currentPage = (int) request()->query('gallery_page', 1);
        $totalPages = (int) ceil($totalImages / $imagesPerPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $images = $imagesQuery->skip(($currentPage - 1) * $imagesPerPage)->take($imagesPerPage)->get();
    } else {
        $images = $imagesQuery->get();
        $totalPages = 1;
        $currentPage = 1;
    }

    if ($images->isEmpty()) return;

    // Grid classes
    $gridCols = match ($columns) {
        2 => 'grid-cols-1 sm:grid-cols-2',
        3 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        5 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
        default => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3',
    };

    $gapClass = 'gap-' . $gap;

    $imageClasses = match ($aspectRatio) {
        'cover' => 'object-cover aspect-square',
        'contain' => 'object-contain aspect-square bg-gray-100 dark:bg-gray-800',
        default => '',
    };

    $cssClass = ($settings ?? [])['class'] ?? '';

    // Display width of a grid cell: the browser picks the matching copy from srcset
    $cellSizes = match (true) {
        $columns >= 5 => '(min-width: 64rem) 16rem, (min-width: 48rem) 33vw, (min-width: 40rem) 50vw, 100vw',
        $columns === 4 => '(min-width: 64rem) 20rem, (min-width: 48rem) 33vw, (min-width: 40rem) 50vw, 100vw',
        $columns === 2 => '(min-width: 80rem) 40rem, (min-width: 40rem) 50vw, 100vw',
        default => '(min-width: 80rem) 26rem, (min-width: 48rem) 33vw, (min-width: 40rem) 50vw, 100vw',
    };
@endphp

<div class="gallery-block {{ $cssClass }}">
    @if($lightbox)
        {{-- Labels of the lightbox buttons (resources/js/frontend/app.js) --}}
        @once
            @php
                $lightboxLabels = [
                    'close' => __('gallery::gallery.lightbox_close'),
                    'previous' => __('gallery::gallery.lightbox_previous'),
                    'next' => __('gallery::gallery.lightbox_next'),
                ];
            @endphp
            <script type="application/json" id="glightbox-i18n">@json($lightboxLabels)</script>
        @endonce
    @endif

    <div class="grid {{ $gridCols }} {{ $gapClass }}">
        @foreach($images as $index => $image)
            @php
                $isFirstRow = $index < $columns;
                $lazy = $lazyLoading && !$isFirstRow;
                $caption = $album->captionFor($image);
                // The lightbox shows the image large: it picks from the same scaled copies
                $lightboxSrcset = \App\Support\ResponsiveImage::srcset($image);
            @endphp
            <div class="gallery-item">
                @if($lightbox)
                    {{-- GLightbox inserts data-title via innerHTML — hence escaped twice,
                         otherwise markup from captions would be executed --}}
                    <a class="glightbox" href="{{ $image->url }}" data-gallery="album-{{ $album->id }}"
                       @if($lightboxSrcset !== '') data-srcset="{{ $lightboxSrcset }}" data-sizes="100vw" @endif
                       @if($caption) data-title="{{ e($caption) }}" @endif>
                @endif

                {{-- Scaled copies instead of the 300 px backend thumbnail: sharp on large and high-density screens --}}
                <x-responsive-image
                    :src="$image"
                    :sizes="$cellSizes"
                    :priority="($imagePriority ?? false) && $index === 0"
                    :lazy="$lazy"
                    :focal="$aspectRatio !== 'contain'"
                    alt="{{ $image->alt_text ?? $album->name }}"
                    class="w-full rounded {{ $imageClasses }}"
                />

                @if($lightbox)
                    </a>
                @endif

                @if($showCaptions && $caption)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $caption }}</p>
                @endif
            </div>
        @endforeach
    </div>

    @if($pagination && $totalPages > 1)
        <nav class="flex items-center justify-center gap-2 mt-6">
            @if($currentPage > 1)
                <a href="{{ request()->fullUrlWithQuery(['gallery_page' => $currentPage - 1]) }}" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    {{ __('gallery::gallery.previous') }}
                </a>
            @endif

            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('gallery::gallery.page_x_of_y', ['current' => $currentPage, 'total' => $totalPages]) }}
            </span>

            @if($currentPage < $totalPages)
                <a href="{{ request()->fullUrlWithQuery(['gallery_page' => $currentPage + 1]) }}" class="px-3 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    {{ __('gallery::gallery.next') }}
                </a>
            @endif
        </nav>
    @endif
</div>
