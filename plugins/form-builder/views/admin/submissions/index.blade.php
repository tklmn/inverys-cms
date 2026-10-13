<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.submissions') }} — {{ $form->name }}</x-slot>

    @php
        // The form's first fields serve as the preview in the row
        $previewFields = collect($form->getDataFields())
            ->filter(fn ($f) => ($f['type'] ?? '') !== 'hidden')
            ->take(2);

        // The export covers all submissions, regardless of the filter
        $hasSubmissions = $form->submissions()->exists();
    @endphp

    <x-ui.page :title="__('form-builder::forms.submissions')" :lead="$form->name">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.forms.index')">
                {{ __('form-builder::forms.forms') }}
            </x-ui.button>

            @permission('show-form-submission')
                <x-ui.button
                    size="md"
                    icon="download"
                    :href="route('backend.forms.submissions.export', $form)"
                    :disabled="! $hasSubmissions"
                    :disabled-reason="__('form-builder::forms.export_nothing')"
                >
                    {{ __('form-builder::forms.export_csv') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form method="GET" action="{{ route('backend.forms.submissions.index', $form) }}" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-64"
                        :placeholder="__('form-builder::forms.search_submissions')"
                        :aria-label="__('translation.search')"
                    />

                    <x-ui.select name="is_read" data-autosubmit class="w-auto" :aria-label="__('form-builder::forms.status')">
                        <option value="">{{ __('form-builder::forms.all_submissions') }}</option>
                        <option value="0" @selected(request('is_read') === '0')>{{ __('form-builder::forms.unread') }}</option>
                        <option value="1" @selected(request('is_read') === '1')>{{ __('form-builder::forms.read') }}</option>
                    </x-ui.select>

                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button>
                </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($submissions as $submission)
                    @php
                        $first = $previewFields->first();
                        $title = $first
                            ? (\Illuminate\Support\Str::limit($submission->data[$first['name'] ?? ''] ?? '', 60) ?: __('form-builder::forms.submission'))
                            : __('form-builder::forms.submission');
                    @endphp

                    <x-ui.list-row
                        :title="$title"
                        icon="inbox"
                        :selected="! $submission->is_read"
                        :href="route('backend.forms.submissions.show', [$form, $submission])"
                    >
                        <x-slot:meta>
                            <span>{{ format_datetime($submission->created_at) }}</span>
                            @foreach($previewFields->skip(1) as $pf)
                                <span>{{ \Illuminate\Support\Str::limit($submission->data[$pf['name'] ?? ''] ?? '', 40) }}</span>
                            @endforeach
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col>
                                @if($submission->is_read)
                                    <x-ui.badge>{{ __('form-builder::forms.read') }}</x-ui.badge>
                                @else
                                    <x-ui.badge tone="accent">{{ __('form-builder::forms.unread') }}</x-ui.badge>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('show-form-submission')
                                <x-ui.button variant="ghost" icon="eye" :label="__('form-builder::forms.view')" :href="route('backend.forms.submissions.show', [$form, $submission])" />

                                <form action="{{ route('backend.forms.submissions.toggle-read', [$form, $submission]) }}" method="POST">
                                    @csrf
                                    <x-ui.button
                                        variant="ghost"
                                        :icon="$submission->is_read ? 'mail' : 'circle-check'"
                                        type="submit"
                                        :label="$submission->is_read ? __('form-builder::forms.mark_unread') : __('form-builder::forms.mark_read')"
                                    />
                                </form>
                            @endpermission

                            @permission('delete-form-submission')
                                <form id="delete-submission-{{ $submission->id }}" action="{{ route('backend.forms.submissions.destroy', [$form, $submission]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-submission-'.$submission->id]) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="inbox">{{ __('form-builder::forms.no_submissions') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($submissions->hasPages())
                <x-slot:foot>{{ $submissions->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
