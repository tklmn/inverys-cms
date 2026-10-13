<x-app-layout>
    <x-slot name="header">{{ __('translation.redirects.title') }}</x-slot>

    @php
        $formAction = $editing ? route('backend.settings.seo.redirects.update', $editing) : route('backend.settings.seo.redirects.store');
    @endphp

    <x-ui.page :title="__('translation.redirects.title')" :lead="__('translation.redirects.lead')">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="md" icon="arrow-left" :href="route('backend.settings.seo')">{{ __('translation.seo.title') }}</x-ui.button>
        </x-slot:actions>

        {{-- Create, or edit the one picked from the list (?edit=ID) --}}
        <x-ui.card :title="$editing ? __('translation.redirects.edit') : __('translation.redirects.create')" :icon="$editing ? 'pencil' : 'plus'" tone="accent">
            <form action="{{ $formAction }}" method="POST" class="flex flex-col gap-4">
                @csrf
                @if($editing)
                    @method('PUT')
                @endif

                <div class="ui-grid">
                    <x-ui.field name="source_path" :label="__('translation.redirects.source')" :help="__('translation.redirects.source_hint')">
                        <x-ui.input id="source_path" name="source_path" class="font-mono" spellcheck="false" :value="old('source_path', $editing?->source_path)" :placeholder="'/old-page'" required />
                    </x-ui.field>

                    <x-ui.field name="target" :label="__('translation.redirects.target')" :help="__('translation.redirects.target_hint')">
                        <x-ui.input id="target" name="target" class="font-mono" spellcheck="false" :value="old('target', $editing?->target)" :placeholder="'/new-page'" required />
                    </x-ui.field>

                    <x-ui.field name="status_code" :label="__('translation.redirects.status')">
                        <x-ui.select id="status_code" name="status_code">
                            @foreach($statusCodes as $code)
                                <option value="{{ $code }}" @selected((int) old('status_code', $editing?->status_code ?? 301) === $code)>{{ __('translation.redirects.status_'.$code) }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    @if($locales !== [])
                        <x-ui.field name="locale" :label="__('translation.redirects.language')" :help="__('translation.redirects.language_hint')">
                            <x-ui.select id="locale" name="locale">
                                <option value="">{{ __('translation.redirects.all_languages') }}</option>
                                @foreach($locales as $code => $meta)
                                    <option value="{{ $code }}" @selected(old('locale', $editing?->locale) === $code)>{{ $meta['native'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    @if($editing)
                        <x-ui.button variant="ghost" size="md" :href="route('backend.settings.seo.redirects')">{{ __('translation.cancel') }}</x-ui.button>
                    @endif
                    <x-ui.button variant="primary" size="md" :icon="$editing ? 'check' : 'plus'" type="submit">{{ $editing ? __('translation.save') : __('translation.redirects.add') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('translation.redirects.list')" icon="split" tone="violet" flush>
            <x-slot:head>
                <form method="GET" action="{{ route('backend.settings.seo.redirects') }}" role="search">
                    <x-ui.input icon="search" type="search" name="q" class="w-56" :value="$search" :placeholder="__('translation.redirects.search')" :aria-label="__('translation.redirects.search')" />
                </form>
            </x-slot:head>

            <x-ui.list>
                @forelse($redirects as $redirect)
                    <x-ui.list-row :title="$redirect->source_path" icon="split">
                        <x-slot:meta>
                            <span class="font-mono wrap-anywhere">→ {{ $redirect->target }}</span>
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.redirects.status')">
                                <x-ui.badge :tone="$redirect->status_code === 301 ? 'accent' : 'neutral'">{{ $redirect->status_code }}</x-ui.badge>
                                @if($redirect->is_automatic)
                                    <x-ui.badge tone="neutral">{{ __('translation.redirects.automatic') }}</x-ui.badge>
                                @endif
                                @if($redirect->locale)
                                    <x-ui.badge tone="neutral">{{ strtoupper($redirect->locale) }}</x-ui.badge>
                                @endif
                            </x-ui.list-col>
                            <x-ui.list-col :label="__('translation.redirects.hits')" priority="low">
                                {{ $redirect->hits }}
                                @if($redirect->last_hit_at)
                                    <span class="text-fg-muted" title="{{ format_datetime($redirect->last_hit_at) }}">· {{ $redirect->last_hit_at->diffForHumans() }}</span>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            <x-ui.button variant="ghost" icon="pencil" :href="route('backend.settings.seo.redirects', ['edit' => $redirect->id])" :label="__('translation.redirects.edit')" />
                            <form id="delete-form-redirect-{{ $redirect->id }}" action="{{ route('backend.settings.seo.redirects.destroy', $redirect) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <x-ui.button
                                    variant="danger"
                                    icon="trash-2"
                                    :label="__('translation.redirects.delete')"
                                    data-delete-modal="{{ json_encode(['formId' => 'delete-form-redirect-'.$redirect->id, 'title' => $redirect->source_path, 'subtitle' => __('translation.redirects.delete_hint')]) }}"
                                />
                            </form>
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="split" :title="$search !== '' ? __('translation.redirects.none_found') : __('translation.redirects.empty')">{{ __('translation.redirects.empty_hint') }}</x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.card>

        @if($redirects->hasPages())
            <div>{{ $redirects->links() }}</div>
        @endif
    </x-ui.page>
</x-app-layout>
