@php
    $checkoutUrl = route('store.checkout', ['product' => $checkoutProduct['key']]);
    $checkoutAmount = number_format((float) $checkoutProduct['amount'], 2, '.', '');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $checkoutProduct['title'] }} Checkout | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="golden-stage-page checkout-page checkout-dedicated-page" data-analytics-screen="store_checkout" data-preferred-currency="{{ auth()->user()?->preferred_currency ?? 'USD' }}">
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

                <div class="checkout-page-layout">
                    <div class="checkout-topline">
                        <a class="checkout-back-link" href="{{ route('store') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" />
                            </svg>
                            Back to Store
                        </a>

                        <div class="checkout-top-actions">
                            <button
                                class="checkout-copy-link"
                                type="button"
                                data-copy-current-url
                                data-copy-url="{{ $checkoutUrl }}"
                                data-copy-success="Link copied"
                            >Copy link</button>
                            <span class="checkout-secure-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <rect x="4" y="10" width="16" height="11" rx="2" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                </svg>
                                <span>Secure checkout</span>
                            </span>
                        </div>
                    </div>

                    <section
                        class="checkout-screen"
                        aria-labelledby="checkoutTitle"
                        data-dedicated-checkout
                        data-checkout-product="{{ $checkoutProduct['key'] }}"
                        data-product-name="{{ $checkoutProduct['title'] }}"
                        data-product-type="{{ $checkoutProduct['type_label'] }}"
                        data-product-summary="{{ $checkoutProduct['summary'] }}"
                        data-product-image="{{ $checkoutProduct['image_url'] }}"
                        data-product-price-value="{{ $checkoutAmount }}"
                    >
                        <div class="checkout-summary">
                            <span class="checkout-kicker">{{ $checkoutProduct['eyebrow'] }}</span>
                            <h1 id="checkoutTitle">{{ $checkoutProduct['title'] }}</h1>
                            <p class="checkout-summary-lede">{{ $checkoutProduct['summary'] }}</p>

                            <figure class="checkout-product-media">
                                <img
                                    src="{{ $checkoutProduct['image_url'] }}"
                                    alt="{{ $checkoutProduct['image_alt'] }}"
                                    loading="eager"
                                    decoding="async"
                                >
                                <figcaption>
                                    <strong>{{ $checkoutProduct['title'] }}</strong>
                                    <span
                                        data-price="{{ $checkoutProduct['key'] }}"
                                        data-price-value="{{ $checkoutAmount }}"
                                    >{{ $checkoutProduct['price_label'] }}</span>
                                </figcaption>
                            </figure>

                            <dl class="checkout-summary-details" aria-label="Product details">
                                @foreach ($checkoutProduct['details'] as $detail)
                                    <div>
                                        <dt>{{ $detail['label'] }}</dt>
                                        <dd>{{ $detail['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <ul class="checkout-benefits" aria-label="Included with this purchase">
                                @foreach ($checkoutProduct['bullets'] as $bullet)
                                    <li><span aria-hidden="true">✓</span>{{ $bullet }}</li>
                                @endforeach
                            </ul>

                            <div class="checkout-total">
                                <div>
                                    <span>Total</span>
                                    <small>PayPal charges in {{ strtoupper($checkoutProduct['currency']) }}</small>
                                </div>
                                <strong
                                    data-price="{{ $checkoutProduct['key'] }}"
                                    data-price-value="{{ $checkoutAmount }}"
                                >{{ $checkoutProduct['price_label'] }}</strong>
                            </div>
                        </div>

                        <section class="checkout-payment" data-checkout-payment-panel aria-labelledby="checkoutDetailsTitle">
                            <div class="checkout-section-title">
                                <div>
                                    <h2 id="checkoutDetailsTitle">Your details</h2>
                                    <p>We’ll use these for your receipt and account access.</p>
                                </div>
                            </div>

                            <form class="checkout-customer-form" id="checkoutCustomerForm" novalidate>
                                <div class="store-contact-grid checkout-fields">
                                    <div class="store-field">
                                        <label for="nameField">Full name</label>
                                        <input class="store-input" id="nameField" name="name" type="text" value="" autocomplete="name" placeholder="Your name" aria-describedby="nameFieldError" required>
                                        <p class="checkout-field-error" id="nameFieldError"></p>
                                    </div>
                                    <div class="store-field">
                                        <label for="emailField">Email</label>
                                        <input class="store-input" id="emailField" name="email" type="email" value="" autocomplete="email" placeholder="you@example.com" aria-describedby="emailFieldError" required>
                                        <p class="checkout-field-error" id="emailFieldError"></p>
                                    </div>
                                    <div class="store-field">
                                        <label for="phoneField">Phone (optional)</label>
                                        <input
                                            class="store-input"
                                            id="phoneField"
                                            name="phone"
                                            type="tel"
                                            value=""
                                            autocomplete="tel"
                                            inputmode="tel"
                                            placeholder="+507 6000 0000"
                                            pattern="^\+[1-9][0-9]{6,14}$"
                                            aria-describedby="phoneFieldError"
                                        >
                                        <p class="checkout-field-error" id="phoneFieldError"></p>
                                    </div>
                                    <div class="store-field">
                                        <label for="countryField">Country</label>
                                        <select class="store-input" id="countryField" name="country" autocomplete="country-name" aria-describedby="countryFieldError" required>
                                            <option value="">Select country</option>
                                            <option value="Panama">Panama</option>
                                            <option value="Dominican Republic">Dominican Republic</option>
                                            <option value="United States">United States</option>
                                            <option value="Puerto Rico">Puerto Rico</option>
                                            <option value="Mexico">Mexico</option>
                                            <option value="Colombia">Colombia</option>
                                            <option value="Costa Rica">Costa Rica</option>
                                            <option value="Venezuela">Venezuela</option>
                                            <option value="Spain">Spain</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <p class="checkout-field-error" id="countryFieldError"></p>
                                    </div>
                                </div>
                            </form>

                            <div class="checkout-paybox" id="checkoutPanel">
                                <div class="checkout-pay-head">
                                    <strong>Pay with</strong>
                                    <div class="store-payments" role="radiogroup" aria-label="Payment method">
                                        <button class="is-active" type="button" data-payment-method="paypal" data-provider-available="true" role="radio" aria-checked="true">PayPal</button>
                                    </div>
                                </div>
                                <div
                                    class="store-paypal-buttons"
                                    id="paypalButtons"
                                    data-paypal-client-id="{{ config('services.paypal.client_id') }}"
                                    data-create-order-endpoint="{{ route('checkout.paypal.orders') }}"
                                    data-cancel-order-endpoint="{{ route('checkout.paypal.orders.cancel') }}"
                                    data-capture-endpoint="{{ route('checkout.paypal') }}"
                                ></div>
                                <p class="checkout-payment-security">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <rect x="4" y="10" width="16" height="11" rx="2" />
                                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                    </svg>
                                    Your payment details are handled securely by PayPal.
                                </p>
                                <p class="store-checkout-note checkout-payment-status" id="paymentStatus" role="status" aria-live="polite">Loading PayPal checkout...</p>
                            </div>
                        </section>

                        <section class="checkout-confirmation" id="purchaseConfirmationPanel" aria-labelledby="purchaseConfirmationTitle" aria-live="polite" tabindex="-1" hidden>
                            <div>
                                <span class="checkout-success-icon" aria-hidden="true">✓</span>
                                <h2 id="purchaseConfirmationTitle">Purchase confirmed</h2>
                                <p id="purchaseConfirmationMessage">Payment confirmed. Your purchase was saved to your account.</p>
                                <a class="store-button checkout-confirmation-button" id="purchaseConfirmationAccount" href="{{ route('account.show') }}">View account</a>
                            </div>
                        </section>
                    </section>
                </div>

                <x-public-navigation active="store" mobile />
            </main>
        </div>

        <div class="store-toast" id="storeToast" role="status" aria-live="polite"></div>
    </body>
</html>
