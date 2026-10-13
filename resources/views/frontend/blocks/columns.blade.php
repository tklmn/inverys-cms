@php
    $colCount = count($block['children'] ?? []);
    if ($colCount < 1) {
        $colCount = (int) ($settings['columns'] ?? 2);
    }
    // Fixed classes so that Tailwind finds them
    $gridCols = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-2 lg:grid-cols-4', 5 => 'md:grid-cols-3 lg:grid-cols-5', 6 => 'md:grid-cols-3 lg:grid-cols-6'];
    // Unequal widths ("split" option) for two and three columns
    $ratios = [
        2 => ['2-1' => 'md:grid-cols-[2fr_1fr]', '1-2' => 'md:grid-cols-[1fr_2fr]', '3-2' => 'md:grid-cols-[3fr_2fr]', '2-3' => 'md:grid-cols-[2fr_3fr]'],
        3 => ['2-1-1' => 'md:grid-cols-[2fr_1fr_1fr]', '1-2-1' => 'md:grid-cols-[1fr_2fr_1fr]', '1-1-2' => 'md:grid-cols-[1fr_1fr_2fr]'],
    ];
    $colsClass = $ratios[$colCount][$settings['ratio'] ?? ''] ?? ($gridCols[$colCount] ?? 'md:grid-cols-'.$colCount);
    $valign = ($settings['valign'] ?? '') === 'center' ? 'site-columns-center' : '';
@endphp
<div class="block-columns grid grid-cols-1 {{ $colsClass }} {{ $settings['gap'] ?? 'gap-6' }} {{ $valign }} {{ $settings['class'] ?? '' }}">
    {!! $children !!}
</div>
