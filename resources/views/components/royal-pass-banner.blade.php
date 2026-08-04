@props([
    'pass' => [],
    'images' => [],
])

@php
    $isGuestPreview = (bool) request()->attributes->get('site_editor_guest_preview', false);
    $viewer = $isGuestPreview ? null : auth()->user();
    $shouldShow = $isGuestPreview || ! \App\Support\EntitlementMatrix::canUseRoyalFeature($viewer);
    $royalPass = is_array($pass) ? $pass : [];

    if ($royalPass === []) {
        try {
            $royalPass = data_get(app(\App\Services\StorefrontSettingsService::class)->publicPayload(), 'royal_pass', []);
        } catch (\Throwable) {
            $royalPass = data_get(app(\App\Services\StorefrontSettingsService::class)->defaults(), 'royal_pass', []);
        }
    }

    $royalProductKey = $royalPass['product_key'] ?? 'royal';
    $royalCtaLabel = $royalPass['cta_label'] ?? 'Unlock Royal Pass';
    $royalImages = collect($images)
        ->merge([
            asset('images/photos/capri.jpg'),
            asset('images/photos/performance.jpg'),
            asset('images/photos/tvVisit.jpg'),
        ])
        ->filter()
        ->unique()
        ->take(3)
        ->values();
@endphp

@if ($shouldShow)
    <section
        class="home-royal-pass is-selected"
        data-royal-pass-banner
        data-royal-pass-container
        data-royal-pass-selected="true"
    >
        <button
            class="home-royal-pass-selector"
            type="button"
            aria-label="Get your Royal Pass to unlock access to our community."
            aria-pressed="true"
            data-royal-pass-option="{{ $royalProductKey }}"
        >
            <span class="home-royal-pass-copy">
                <span class="home-royal-pass-heading">Get your <strong>Royal Pass</strong></span>
                <span class="home-royal-pass-description">to unlock access to our community.</span>
            </span>
            <span class="home-royal-pass-images" aria-hidden="true">
                @foreach ($royalImages as $image)
                    <img src="{{ $image }}" alt="">
                @endforeach
            </span>
        </button>
        <button
            class="store-button home-unlock-button"
            type="button"
            data-buy="{{ $royalProductKey }}"
            data-buy-name="Royal Pass"
            data-buy-type="Membership"
            data-buy-summary="Monthly membership with community access."
            data-buy-image="{{ asset('images/store/royal-pass.png') }}"
            data-buy-url="{{ route('store.checkout', ['product' => $royalProductKey]) }}"
            data-royal-pass-cta
            aria-disabled="false"
            aria-label="{{ $royalCtaLabel }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path d="M7 17 17 7"></path>
                <path d="M8 7h9v9"></path>
            </svg>
            {{ $royalCtaLabel }}
        </button>
    </section>
@endif
