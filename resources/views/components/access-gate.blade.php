@props([
    'section' => 'royal',
    'title' => 'Royal Pass required',
    'preview' => 'Preview available in Open mode.',
    'cta' => 'Get your Royal Pass',
])

@php
    $user = auth()->user();
    $canAccess = \App\Support\EntitlementMatrix::canUseRoyalFeature($user);
    $stateView = \App\Support\AccessStatePresenter::for($user, request()->path());
    $reactivationActions = ['upgrade', 'reactivate', 'update_payment', 'repurchase'];
    $ctaClasses = 'access-gate-button';

    if (in_array($stateView['primary_action'], $reactivationActions, true)) {
        $ctaClasses .= ' reactivation-action';
    }
@endphp

@if ($canAccess)
    {{ $slot }}
@else
    <div
        class="access-gate"
        data-section="{{ $section }}"
        data-access-state="{{ $stateView['state'] }}"
        data-source-route="{{ $stateView['source_route'] }}"
    >
        <div>
            <span>{{ strtoupper($section) }} PREVIEW</span>
            <strong>{{ $title }}</strong>
            <p>{{ $stateView['paywall_title'] }}. {{ $preview }}</p>
        </div>
        <div class="access-gate-actions">
            <a
                class="{{ $ctaClasses }}"
                href="{{ $stateView['primary_url'] }}"
                data-reactivation-action="{{ $stateView['primary_action'] }}"
                data-access-state="{{ $stateView['state'] }}"
            >{{ $cta === 'Get your Royal Pass' ? $stateView['primary_label'] : $cta }}</a>

            @guest
                <a class="access-gate-link" href="{{ route('register') }}">Create account</a>
            @endguest
        </div>
    </div>
@endif
