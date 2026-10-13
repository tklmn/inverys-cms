@props([
    // Library entry, "storage/media/…", "media/…", a URL of this site or an external URL
    'src' => null,
    // Display width per viewport (the browser picks the matching copy from srcset)
    'sizes' => null,
    // Largest image above the fold: eager + fetchpriority="high"
    'priority' => false,
    // false: load eagerly without raising the priority
    'lazy' => true,
    // Focal point of the library entry as object-position; false for object-fit: contain
    'focal' => true,
])
{{-- Responsive <img>: srcset/sizes, width/height, decoding, loading and the focal point
     (object-position, merged with a given style) from App\Support\ResponsiveImage.
     All other attributes (alt, class, role, data-*) are passed through unchanged. --}}
<img {{ $attributes->merge(\App\Support\ResponsiveImage::attributes($src, ['sizes' => $sizes, 'priority' => (bool) $priority, 'lazy' => (bool) $lazy, 'focal' => (bool) $focal])) }}>
