<x-app-layout>
    <x-slot name="header">{{ __('API Tokens') }}</x-slot>

    @php
        // A token's permissions in words — what is stored are abilities (* / read / write)
        $access = fn ($token) => match (true) {
            in_array('*', $token->abilities ?? ['*'], true) => [__('Full access'), 'accent'],
            in_array('write', $token->abilities ?? [], true) => [__('Read & write'), 'caution'],
            default => [__('Read only'), 'neutral'],
        };
    @endphp

    <x-ui.page :title="__('API Tokens')" :lead="__('translation.api_tokens.lead')">
        {{-- Newly created token — shown exactly once --}}
        @if(session('plain_text_token'))
            <x-ui.note tone="caution" :title="__('Your new API token')">
                <p class="mb-2">{{ __('Copy this token now. It will not be shown again.') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <code id="new-api-token" class="min-w-0 flex-1 select-all rounded-md border border-line bg-surface-sunken p-2 font-mono text-xs break-all text-fg">{{ session('plain_text_token') }}</code>
                    <x-ui.button size="md" icon="copy" data-copy="#new-api-token">{{ __('translation.api_tokens.copy') }}</x-ui.button>
                </div>
            </x-ui.note>
        @endif

        <x-ui.card :title="__('Create Token')" icon="plus" tone="accent">
            <form action="{{ route('backend.api-tokens.store') }}" method="POST" class="flex flex-col gap-4">
                @csrf

                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('Token Name')">
                        <x-ui.input id="name" name="name" :placeholder="__('e.g. Mobile app')" required />
                    </x-ui.field>

                    <x-ui.field name="expires" :label="__('Expires (days)')" optional :help="__('translation.api_tokens.expires_help')">
                        <x-ui.input id="expires" name="expires" type="number" :placeholder="__('Never')" min="1" />
                    </x-ui.field>

                    <x-ui.field name="scope" :label="__('Access')">
                        <x-ui.select id="scope" name="scope">
                            <option value="full">{{ __('Full access') }}</option>
                            <option value="read-write">{{ __('Read & write') }}</option>
                            <option value="read-only">{{ __('Read only') }}</option>
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <div class="flex justify-end">
                    <x-ui.button variant="primary" size="md" icon="plus" type="submit">{{ __('Create') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('Your Tokens')" icon="key-round" tone="violet" flush>
            <x-ui.list>
                @forelse($tokens as $token)
                    @php([$accessLabel, $accessTone] = $access($token))
                    <x-ui.list-row :title="$token->name" icon="key-round">
                        <x-slot:meta>
                            <span>{{ __('Created') }}: {{ format_date($token->created_at) }}</span>
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('Access')">
                                <x-ui.badge :tone="$accessTone">{{ $accessLabel }}</x-ui.badge>
                            </x-ui.list-col>
                            <x-ui.list-col :label="__('Last Used')" priority="low">
                                @if($token->last_used_at)
                                    <span title="{{ format_datetime($token->last_used_at) }}">{{ $token->last_used_at->diffForHumans() }}</span>
                                @else
                                    <span class="text-fg-muted">{{ __('Never') }}</span>
                                @endif
                            </x-ui.list-col>
                            <x-ui.list-col :label="__('Expires')">
                                @if(! $token->expires_at)
                                    <span class="text-fg-muted">{{ __('Never') }}</span>
                                @elseif($token->isExpired())
                                    <x-ui.badge tone="critical">{{ __('Expired') }}</x-ui.badge>
                                @else
                                    {{ format_date($token->expires_at) }}
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            <form id="delete-form-token-{{ $token->id }}" action="{{ route('backend.api-tokens.destroy', $token) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <x-ui.button
                                    variant="danger"
                                    icon="trash-2"
                                    :label="__('Revoke')"
                                    data-delete-modal="{{ json_encode(['formId' => 'delete-form-token-'.$token->id, 'title' => $token->name, 'subtitle' => __('translation.api_tokens.revoke_hint')]) }}"
                                />
                            </form>
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="key-round" :title="__('No API tokens yet.')">{{ __('translation.api_tokens.empty_hint') }}</x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
