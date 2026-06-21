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
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;500&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="store_checkout">
        <div class="store-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ route('music') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab" href="{{ url('/community') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab is-active" href="{{ url('/store') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>STORE</span>
                        </a>
                    </nav>
                </div>

                <x-member-card />
            </aside>

            <main class="main-content store-content checkout-content" id="store-checkout">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo.png') }}"
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

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ route('music') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="{{ url('/videos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="m22 8-6 4 6 4V8Z"></path>
                            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                        </svg>
                        <span class="sr-only">VIDEOS</span>
                    </a>
                    <a href="{{ url('/photos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                        <span class="sr-only">PHOTOS</span>
                    </a>
                    <a href="{{ url('/community') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                        </svg>
                        <span class="sr-only">COMMUNITY</span>
                    </a>
                    <a class="is-active" href="{{ url('/store') }}" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 10h16"></path>
                            <path d="M5 10l1.5-5h11L19 10"></path>
                            <path d="M6 10v9h12v-9"></path>
                            <path d="M9 19v-5h6v5"></path>
                        </svg>
                        <span class="sr-only">STORE</span>
                    </a>
                </nav>
            </main>
        </div>

        @include('partials.store-checkout-modals')
    </body>
</html>
