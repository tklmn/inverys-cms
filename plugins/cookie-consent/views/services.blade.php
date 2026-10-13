<x-app-layout>
    <x-slot name="header">{{ __('cookie-consent::cc.services_title') }}</x-slot> @php
        $categoryTone = [
            'functional' => 'positive', 'statistics' => 'accent', 'marketing' => 'caution', ];
    @endphp

    <x-ui.page :title="__('cookie-consent::cc.services_heading')"> <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.cookie-consent.services.create')"> {{ __('cookie-consent::cc.add_service') }} </x-ui.button>
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.list>
                @forelse($services as $service)
                    <x-ui.list-row
                        :title="$service['name']" icon="cookie" :href="route('backend.cookie-consent.services.edit', $service['id'])" >
                        <x-slot:cols>
                            <x-ui.list-col :label="__('cookie-consent::cc.service_position')" priority="low"> {{ __('cookie-consent::cc.position_'.$service['position']) }} </x-ui.list-col>

                            <x-ui.list-col>
                                <x-ui.badge :tone="$categoryTone[$service['category']] ?? 'neutral'"> {{ __('cookie-consent::cc.category_'.$service['category']) }} </x-ui.badge>
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.cookie-consent.services.edit', $service['id'])" /> <form id="delete-form-service-{{ $service['id'] }}" action="{{ route('backend.cookie-consent.services.destroy', $service['id']) }}" method="POST"> @csrf
                                @method('DELETE') <x-ui.button
                                    variant="danger" icon="trash-2" :label="__('translation.delete')" data-delete-modal="{{ json_encode(['formId' => 'delete-form-service-'.$service['id']]) }}" />
                            </form>
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="cookie">{{ __('cookie-consent::cc.no_services') }}</x-ui.empty> @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
