@php
    $checkoutUrl = route('store.checkout', ['product' => $checkoutProduct['key']]);
    $checkoutAmount = number_format((float) $checkoutProduct['amount'], 2, '.', '');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $checkoutProduct['title'] }} Checkout | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="golden-stage-page checkout-page" data-analytics-screen="store_checkout" data-preferred-currency="{{ auth()->user()?->preferred_currency ?? 'USD' }}">
        <div class="store-shell home-shell golden-stage-shell checkout-stage-shell">
            @include('partials.stage-lights')

            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo-white.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation active="store" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content checkout-content golden-stage-main" id="store-checkout">
                <header class="mobile-header golden-stage-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo-white.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <section class="checkout-screen" aria-labelledby="checkoutTitle">
                    <a class="checkout-back-link" href="{{ route('store') }}">Store</a>

                    <div class="checkout-product-layout">
                        <figure class="checkout-product-media">
                            <img
                                src="{{ $checkoutProduct['image_url'] }}"
                                alt="{{ $checkoutProduct['image_alt'] }}"
                                loading="eager"
                                decoding="async"
                            >
                        </figure>

                        <div class="checkout-product-copy">
                            <span class="checkout-kicker">{{ $checkoutProduct['eyebrow'] }}</span>
                            <h1 id="checkoutTitle">{{ $checkoutProduct['title'] }}</h1>
                            <p>{{ $checkoutProduct['summary'] }}</p>
                            <strong
                                class="checkout-product-price"
                                data-price="{{ $checkoutProduct['key'] }}"
                                data-price-value="{{ $checkoutAmount }}"
                            >{{ $checkoutProduct['price_label'] }}</strong>

                            <div class="checkout-actions">
                                <button
                                    class="store-button checkout-primary-button"
                                    type="button"
                                    data-buy="{{ $checkoutProduct['key'] }}"
                                    data-buy-name="{{ $checkoutProduct['title'] }}"
                                    data-buy-type="{{ $checkoutProduct['type_label'] }}"
                                    data-buy-summary="{{ $checkoutProduct['summary'] }}"
                                    data-buy-image="{{ $checkoutProduct['image_url'] }}"
                                    data-buy-price-value="{{ $checkoutAmount }}"
                                    data-buy-url="{{ $checkoutUrl }}"
                                    data-auto-open-checkout="true"
                                >{{ $checkoutProduct['cta_label'] }}</button>

                                <button
                                    class="checkout-copy-link"
                                    type="button"
                                    data-copy-current-url
                                    data-copy-url="{{ $checkoutUrl }}"
                                    data-copy-success="Link copied"
                                >Copy link</button>
                            </div>
                        </div>
                    </div>

                    <div class="checkout-info-grid">
                        <section class="checkout-info-panel" aria-label="Checkout details">
                            <h2>Details</h2>
                            <div class="checkout-detail-grid">
                                @foreach ($checkoutProduct['details'] as $detail)
                                    <div>
                                        <strong>{{ $detail['value'] }}</strong>
                                        <span>{{ $detail['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="checkout-info-panel" aria-label="Included">
                            <h2>Included</h2>
                            <ul class="checkout-bullet-list">
                                @foreach ($checkoutProduct['bullets'] as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </section>
                    </div>
                </section>

                <x-public-navigation active="store" mobile />
            </main>
        </div>

        @include('partials.store-checkout-modals')
    </body>
</html>
