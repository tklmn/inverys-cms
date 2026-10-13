@php
    // Pricing table: per plan name, price, period, text, features (one per line) and button
    // Keys are preserved: the editor preview edits plans.N.name etc.
    $plans = array_filter($data['plans'] ?? [], fn ($plan) => filled($plan['name'] ?? null));
@endphp
@if($plans)
<div class="block-pricing site-pricing {{ $settings['class'] ?? '' }}">
    @foreach($plans as $i => $plan)
        @php
            $featured = ! empty($plan['featured']);
            $features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($plan['features'] ?? '')))));
        @endphp
        <div class="site-card site-plan {{ $featured ? 'site-plan-featured' : '' }}">
            @if($featured && ! empty($plan['badge']))
                <span class="site-plan-badge">{{ $plan['badge'] }}</span>
            @endif
            <div>
                <h3 class="site-plan-name" @pbField('plans.'.$i.'.name')>{{ $plan['name'] }}</h3>
                @if(! empty($plan['description']))
                    <p class="site-plan-text mt-1" @pbField('plans.'.$i.'.description')>{{ $plan['description'] }}</p>
                @endif
            </div>
            @if(filled($plan['price'] ?? null))
                <p class="site-plan-price">
                    <span class="site-plan-amount" @pbField('plans.'.$i.'.price')>{{ $plan['price'] }}</span>
                    @if(! empty($plan['period']))<span class="site-plan-period" @pbField('plans.'.$i.'.period')>{{ $plan['period'] }}</span>@endif
                </p>
            @endif
            @if($features)
                <ul class="site-plan-features">
                    @foreach($features as $feature)
                        <li><x-ui.icon name="circle-check" />{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif
            @if(! empty($plan['button_text']))
                <a href="{{ \App\Helpers\HtmlHelper::safeUrl($plan['button_url'] ?? '#') }}" class="site-btn {{ $featured ? 'site-btn-primary' : 'site-btn-secondary' }}">
                    <span @pbField('plans.'.$i.'.button_text')>{{ $plan['button_text'] }}</span>
                </a>
            @endif
        </div>
    @endforeach
</div>
@endif
