{{-- Modern theme uses inline navigation in its own navigation.blade.php --}}
{{-- This partial is kept for compatibility if included elsewhere --}}
@includeFirst(['theme::partials.menu', 'frontend.partials.menu'], ['pages' => $pages])
