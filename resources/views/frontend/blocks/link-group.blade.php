<div class="block-link-group flex flex-wrap gap-2 {{ $settings['class'] ?? '' }}">
    @foreach(($data['links'] ?? []) as $i => $link)
        @php
            $newWindow = ($link['target'] ?? '_self') === '_blank';
            $label = trim((string) ($link['aria_label'] ?? ''));
        @endphp
        <a
            href="{{ \App\Helpers\HtmlHelper::safeUrl($link['url'] ?? '#') }}"
            target="{{ $newWindow ? '_blank' : '_self' }}"
            @if($newWindow) rel="noopener noreferrer" @endif
            @if($label !== '') aria-label="{{ $newWindow ? $label.' '.__('translation.frontend.opens_new_tab') : $label }}" @endif
            class="site-btn site-btn-secondary min-h-10 rounded-full px-4 py-2 text-sm"
        >
            <span @pbField('links.'.$i.'.text')>{{ $link['text'] ?? '' }}</span>{{ \App\Support\BlockDesign::newTabHint($newWindow) }}
            <x-ui.icon :name="$newWindow ? 'external-link' : 'arrow-right'" />
        </a>
    @endforeach
</div>
