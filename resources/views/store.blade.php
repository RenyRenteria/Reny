@php
    $products = [
        [
            'key' => 'deluxe',
            'name' => 'Deluxe Digital Album',
            'type' => 'Digital',
            'category' => 'music',
            'price' => 24,
            'availability' => 'Instant access',
            'points' => '+240 pts',
            'pass' => 'No Royal Pass required',
            'access' => 'Unlocks in profile',
            'image' => 'cover.jpg',
            'summary' => 'Extended cuts, alternate versions, lyric annotations, visual zine, and documentary materials.',
            'cta' => 'Buy album',
        ],
        [
            'key' => 'singles',
            'name' => 'Singles / Digital Pack',
            'type' => 'Digital',
            'category' => 'music',
            'price' => 8,
            'availability' => 'Instant access',
            'points' => '+80 pts',
            'pass' => 'No Royal Pass required',
            'access' => 'Unlocks in profile',
            'image' => 'campaign.jpg',
            'summary' => 'Small digital packs for fans who want new releases without waiting for the full album cycle.',
            'cta' => 'Buy music',
        ],
        [
            'key' => 'royal',
            'name' => 'Royal Pass',
            'type' => 'Subscription',
            'category' => 'membership',
            'price' => 4.99,
            'suffix' => '/mo',
            'availability' => 'Active today',
            'points' => '+50 pts',
            'pass' => 'Pass product',
            'access' => 'Content lives in Royal Pass',
            'image' => 'studio.jpg',
            'summary' => 'Monthly membership with exclusive songs, livestreams, community access, voting, and early drops.',
            'cta' => 'Join membership',
        ],
        [
            'key' => 'merch',
            'name' => 'Studio Merch',
            'type' => 'Physical',
            'category' => 'merch',
            'price' => 48,
            'availability' => 'In stock',
            'points' => '+120 pts',
            'pass' => 'No Royal Pass required',
            'access' => 'Ships after checkout',
            'image' => 'dance.jpg',
            'summary' => 'Minimal studio pieces designed as wearable objects, not generic tour merch.',
            'cta' => 'Add to bag',
        ],
        [
            'key' => 'print',
            'name' => 'Numbered Art Print',
            'type' => 'Drop',
            'category' => 'merch',
            'price' => 86,
            'availability' => '31 left of 100',
            'points' => '+220 pts',
            'pass' => 'Royal Pass early access',
            'access' => 'Certificate included',
            'image' => 'places.jpg',
            'summary' => "A numbered visual companion to the current release and entry point into Royal's Exclusives.",
            'cta' => 'Add print',
        ],
    ];

    $events = [
        [
            'key' => 'concert',
            'name' => 'Reny Live - Studio Night',
            'kicker' => 'Physical event',
            'date' => 'Aug 24, 2026',
            'place' => 'Panama City',
            'price' => 42,
            'image' => 'reny-store-concert-poster.png',
            'action' => 'Buy ticket',
            'mode' => 'buy',
        ],
        [
            'key' => 'making',
            'name' => 'Making The Deluxe Album',
            'kicker' => 'Digital event',
            'date' => 'Aug 31, 2026',
            'place' => 'Royal Stream',
            'price' => 0,
            'image' => 'studio.jpg',
            'action' => 'RSVP',
            'mode' => 'rsvp',
        ],
        [
            'key' => 'listening',
            'name' => 'Deluxe Preview Session',
            'kicker' => 'Listening session',
            'date' => 'Sep 06, 2026',
            'place' => 'Deluxe Listening Room',
            'price' => 18,
            'image' => 'capri.jpg',
            'action' => 'Buy ticket',
            'mode' => 'buy',
        ],
    ];

    $rsvpTickets = $rsvpTickets ?? [];

    if (! empty($publicCms['products'] ?? [])) {
        $products = $publicCms['products'];
    }

    if (! empty($publicCms['events'] ?? [])) {
        $events = $publicCms['events'];
    }

    $featuredProduct = collect($products)->firstWhere('key', 'royal') ?? $products[0] ?? [
        'key' => 'deluxe',
        'name' => 'Deluxe Digital Album',
        'type' => 'Digital',
        'price' => 24,
        'image' => 'cover.jpg',
        'cta' => 'Buy album',
    ];
    $heroImage = $featuredProduct['image_url'] ?? asset('images/photos/' . ($featuredProduct['image'] ?? 'cover.jpg'));
    $featuredCta = $featuredProduct['cta'] ?? 'Add to bag';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Store | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;500&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="store">
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
                        <a class="tab" href="{{ url('/') }}">
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

            <main class="main-content store-content" id="store">
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

                <div class="store-topline">
                    <div>
                        <div class="currency-switch" role="group" aria-label="Currency reference" aria-describedby="currency-note">
                            <button class="currency-button is-active" type="button" data-currency="usd" aria-pressed="true">USD</button>
                            <button class="currency-button" type="button" data-currency="eur" aria-pressed="false">EUR</button>
                            <button class="currency-button" type="button" data-currency="gbp" aria-pressed="false">GBP</button>
                            <button class="currency-button" type="button" data-currency="dop" aria-pressed="false">DOP</button>
                        </div>
                        <p class="store-currency-note" id="currency-note">Reference prices update here. Checkout is charged in USD.</p>
                    </div>
                    <button class="store-bag-button" id="openBag" type="button">Bag <span id="bagCount">0</span></button>
                </div>

                <section class="store-hero" aria-labelledby="store-hero-title">
                    <img src="{{ $heroImage }}" alt="{{ $featuredProduct['name'] }}">
                    <div class="store-hero-copy">
                        <span>Official store</span>
                        <h1 id="store-hero-title">Reny Shop</h1>
                        <p>Membership, music and merch in one clean checkout.</p>
                        <div class="store-hero-actions">
                            <strong data-price="{{ $featuredProduct['key'] }}" data-price-value="{{ $featuredProduct['price'] }}">${{ $featuredProduct['price'] }}{{ $featuredProduct['suffix'] ?? '' }}</strong>
                            <button
                                class="store-button store-button-light"
                                type="button"
                                data-buy="{{ $featuredProduct['key'] }}"
                            >{{ $featuredCta }}</button>
                        </div>
                    </div>
                </section>

                <section class="store-market" aria-labelledby="market-title">
                    <div class="store-section-head">
                        <h2 id="market-title">Shop</h2>
                        <div class="store-filters" role="group" aria-label="Product filters">
                            <button class="store-filter is-active" type="button" data-filter="all" aria-pressed="true">All</button>
                            <button class="store-filter" type="button" data-filter="membership" aria-pressed="false">Membership</button>
                            <button class="store-filter" type="button" data-filter="music" aria-pressed="false">Music</button>
                            <button class="store-filter" type="button" data-filter="merch" aria-pressed="false">Merch</button>
                        </div>
                    </div>

                    <div class="store-product-grid">
                        @foreach ($products as $product)
                            @php
                                $productImage = $product['image_url'] ?? asset('images/photos/' . $product['image']);
                            @endphp
                            <article class="store-product-card" data-category="{{ $product['category'] }}">
                                <button
                                    class="store-product-button"
                                    type="button"
                                    data-detail="{{ $product['key'] }}"
                                    data-name="{{ $product['name'] }}"
                                    data-type="{{ $product['type'] }}"
                                    data-price-key="{{ $product['key'] }}"
                                    data-availability="{{ $product['availability'] }}"
                                    data-points="{{ $product['points'] }}"
                                    data-pass="{{ $product['pass'] }}"
                                    data-access="{{ $product['access'] }}"
                                    data-summary="{{ $product['summary'] }}"
                                    data-image="{{ $productImage }}"
                                    data-cta="{{ $product['cta'] ?? 'Add to bag' }}"
                                >
                                    <span class="store-product-visual">
                                        <img src="{{ $productImage }}" alt="{{ $product['name'] }}" loading="lazy" decoding="async">
                                    </span>
                                    <span class="store-product-meta">
                                        <span>{{ $product['type'] }}</span>
                                        <strong>{{ $product['name'] }}</strong>
                                        <em data-price="{{ $product['key'] }}" data-price-value="{{ $product['price'] }}">${{ $product['price'] }}{{ $product['suffix'] ?? '' }}</em>
                                    </span>
                                </button>
                                <div class="store-product-actions">
                                    <button
                                        class="store-button store-product-cta"
                                        type="button"
                                        data-buy="{{ $product['key'] }}"
                                        aria-label="{{ $product['cta'] ?? 'Add to bag' }} - {{ $product['name'] }}"
                                    >{{ $product['cta'] ?? 'Add to bag' }}</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="store-events" aria-labelledby="events-title">
                    <div class="store-section-head">
                        <h2 id="events-title">Events</h2>
                    </div>

                    <div class="store-event-grid">
                        @foreach ($events as $event)
                            @php
                                $eventImage = $event['image_url'] ?? asset('images/photos/' . $event['image']);
                                $rsvpTicket = $rsvpTickets[$event['key']] ?? null;
                                $rsvpStatusId = 'rsvp-status-' . \Illuminate\Support\Str::slug($event['key']);
                            @endphp
                            <article class="store-event-card">
                                <img src="{{ $eventImage }}" alt="{{ $event['name'] }} poster" loading="lazy" decoding="async">
                                <div class="store-event-copy">
                                    <span>{{ $event['kicker'] }}</span>
                                    <h3>{{ $event['name'] }}</h3>
                                    <div class="store-event-meta">
                                        <strong>{{ $event['date'] }}</strong>
                                        <strong>{{ $event['place'] }}</strong>
                                    </div>
                                    @if ($event['mode'] === 'buy')
                                        <button
                                            class="store-button store-button-light"
                                            type="button"
                                            data-buy="{{ $event['key'] }}"
                                            data-buy-name="{{ $event['name'] }}"
                                            data-buy-type="{{ $event['kicker'] }}"
                                            data-buy-summary="{{ $event['date'] }} - {{ $event['place'] }}"
                                        >{{ $event['action'] }} <span data-price="{{ $event['key'] }}" data-price-value="{{ $event['price'] }}">${{ $event['price'] }}</span></button>
                                    @else
                                        <button
                                            class="store-button store-button-light"
                                            type="button"
                                            data-rsvp="{{ $event['key'] }}"
                                            data-rsvp-name="{{ $event['name'] }}"
                                            data-rsvp-endpoint="{{ route('store.rsvp') }}"
                                            data-rsvp-status-target="{{ $rsvpStatusId }}"
                                            data-rsvp-confirmed="{{ $rsvpTicket ? 'true' : 'false' }}"
                                            aria-describedby="{{ $rsvpStatusId }}"
                                        >{{ $rsvpTicket ? 'RSVP confirmed' : $event['action'] }}</button>
                                        <p
                                            class="store-rsvp-status {{ $rsvpTicket ? 'is-confirmed' : '' }}"
                                            id="{{ $rsvpStatusId }}"
                                        >
                                            @if ($rsvpTicket)
                                                Reserved - {{ str_replace('_', ' ', $rsvpTicket['status']) }} - Code {{ $rsvpTicket['code'] }}
                                            @else
                                                Free RSVP confirms a reservation on this account.
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ url('/') }}">
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

        <section class="store-modal-layer" id="detailLayer" hidden inert>
            <div class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
                <div class="store-dialog-head">
                    <h2 id="detailTitle">Product</h2>
                    <button class="store-icon-button" type="button" data-close="detailLayer" aria-label="Close product details">Close</button>
                </div>
                <div class="store-detail">
                    <img id="detailImage" src="{{ asset('images/photos/merch.jpg') }}" alt="">
                    <div class="store-detail-copy">
                        <p id="detailText"></p>
                        <div class="store-detail-grid" id="detailGrid"></div>
                        <button class="store-button" id="detailBuy" type="button">Add to bag</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="store-modal-layer" id="bagLayer" hidden inert>
            <div class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="bagTitle">
                <div class="store-dialog-head">
                    <h2 id="bagTitle">Checkout</h2>
                    <button class="store-icon-button" type="button" data-close="bagLayer" aria-label="Close checkout">Close</button>
                </div>
                <div class="store-checkout-grid">
                    <div class="store-checkout-panel">
                        <h3>Step 1 - Bag</h3>
                        <p class="store-checkout-note">Selected currency is a reference. PayPal checkout is charged in USD. Every completed purchase activates Royal Pass for 1 month on this account.</p>
                        <div class="store-bag-list" id="bagList"></div>
                        <div class="store-contact-grid">
                            <div>
                                <label for="emailField">Receipt email</label>
                                <input class="store-input" id="emailField" type="email" value="" autocomplete="email">
                            </div>
                            <div>
                                <label for="phoneField">Phone</label>
                                <input class="store-input" id="phoneField" type="tel" value="" autocomplete="tel">
                            </div>
                        </div>
                        <div class="store-total-row">
                            <span>Total</span>
                            <strong id="bagTotal">$0</strong>
                        </div>
                    </div>
                    <div
                        class="store-checkout-panel"
                        id="checkoutPanel"
                    >
                        <h3>Step 2 - Pay</h3>
                        <div class="store-payments" role="radiogroup" aria-label="Payment method">
                            <button class="is-active" type="button" data-payment-method="paypal" data-provider-available="true" role="radio" aria-checked="true">PayPal</button>
                        </div>
                        <div
                            class="store-paypal-buttons"
                            id="paypalButtons"
                            data-paypal-client-id="{{ config('services.paypal.client_id') }}"
                            data-create-order-endpoint="{{ route('checkout.paypal.orders') }}"
                            data-cancel-order-endpoint="{{ route('checkout.paypal.orders.cancel') }}"
                            data-capture-endpoint="{{ route('checkout.paypal') }}"
                        ></div>
                        <p class="store-checkout-note" id="paymentStatus">Add a product to enable PayPal checkout.</p>
                        <button class="store-button" id="completePurchase" type="button">Load PayPal checkout</button>
                    </div>
                </div>
            </div>
        </section>

        <div class="store-toast" id="storeToast" role="status" aria-live="polite"></div>
    </body>
</html>
