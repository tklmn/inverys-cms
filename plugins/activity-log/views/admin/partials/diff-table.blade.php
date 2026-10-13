{{-- Old and new values side by side --}}
@php
    $oldValues = $properties['old'] ?? []; $newValues = $properties['new'] ?? []; $allFields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

    $format = fn ($value) => is_array($value) ? json_encode($value) : ($value ?? '—');
@endphp <x-ui.card :title="__('activity-log::activity.properties')" icon="list" tone="violet" flush> <x-ui.list>
        @forelse($allFields as $field)
            @php
                $oldVal = $oldValues[$field] ?? null;
                $newVal = $newValues[$field] ?? null;
                $changed = $oldVal !== $newVal;
            @endphp

            <x-ui.list-row :title="$field" :icon="$changed ? 'git-compare' : 'minus'"> <x-slot:cols>
                    <x-ui.list-col :label="__('activity-log::activity.old_value')"> <span class="{{ $changed ? 'text-critical line-through' : '' }}">{{ $format($oldVal) }}</span> </x-ui.list-col>

                    <x-ui.list-col :label="__('activity-log::activity.new_value')"> <span class="{{ $changed ? 'font-medium text-positive' : '' }}">{{ $format($newVal) }}</span> </x-ui.list-col>
                </x-slot:cols>
            </x-ui.list-row>
        @empty
            <x-ui.empty icon="circle-check">{{ __('activity-log::activity.no_changes') }}</x-ui.empty> @endforelse
    </x-ui.list>
</x-ui.card>
