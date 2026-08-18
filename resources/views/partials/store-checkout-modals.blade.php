<section class="store-modal-layer" id="detailLayer" hidden inert>
    <div class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
        <div class="store-dialog-head">
            <h2 id="detailTitle">Product</h2>
            <button class="store-icon-button" type="button" data-close="detailLayer" aria-label="Close product details">Close</button>
        </div>
        <div class="store-detail">
            <img id="detailImage" src="{{ asset($detailPlaceholderImage ?? 'images/photos/merch.jpg') }}" alt="">
            <div class="store-detail-copy">
                <p id="detailText"></p>
                <div class="store-detail-grid" id="detailGrid"></div>
                <button class="store-button" id="detailBuy" type="button">Checkout with PayPal</button>
            </div>
        </div>
    </div>
</section>

<section class="store-modal-layer" id="freeEventRsvpLayer" hidden inert>
    <div class="store-dialog free-event-rsvp-dialog" role="dialog" aria-modal="true" aria-labelledby="freeEventRsvpTitle">
        <div class="store-dialog-head">
            <h2 id="freeEventRsvpTitle">Get Tickets</h2>
            <button class="store-icon-button" type="button" data-close="freeEventRsvpLayer" aria-label="Close registration">Close</button>
        </div>
        <form
            class="free-event-rsvp-form"
            id="freeEventRsvpForm"
            data-free-event-rsvp-form
            data-free-event-rsvp-endpoint="{{ route('community.free-event-rsvp.store') }}"
        >
            <p class="store-checkout-note" id="freeEventRsvpEventName"></p>
            <div class="store-contact-grid">
                <div class="store-field">
                    <label for="freeEventRsvpName">Nombre</label>
                    <input class="store-input" id="freeEventRsvpName" name="name" type="text" value="" autocomplete="name" required>
                </div>
                <div class="store-field">
                    <label for="freeEventRsvpEmail">Correo electrónico</label>
                    <input class="store-input" id="freeEventRsvpEmail" name="email" type="email" value="" autocomplete="email" required>
                </div>
                <div class="store-field">
                    <label for="freeEventRsvpCountry">País</label>
                    <select class="store-input" id="freeEventRsvpCountry" name="country" autocomplete="country-name" required>
                        <option value="">Select country</option>
                        @foreach (\App\Support\CountryOptions::names() as $country)
                            <option value="{{ $country }}">{{ $country }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="free-event-rsvp-actions">
                <button class="store-button" id="freeEventRsvpSubmit" type="submit">Registrarme</button>
                <p class="store-checkout-note free-event-rsvp-status" id="freeEventRsvpStatus" role="status" aria-live="polite"></p>
            </div>
        </form>
    </div>
</section>

<section class="store-modal-layer" id="purchaseConfirmationLayer" hidden inert>
    <div class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="purchaseConfirmationTitle">
        <div class="store-dialog-head">
            <h2 id="purchaseConfirmationTitle">Royal Pass confirmed</h2>
            <button class="store-icon-button" type="button" data-close="purchaseConfirmationLayer" aria-label="Close confirmation">Close</button>
        </div>
        <div class="store-checkout-panel">
            <h3>Payment confirmed</h3>
            <p class="store-checkout-note" id="purchaseConfirmationMessage">Your Royal Pass is active. Confirmation was saved to your account.</p>
            <a class="store-button" id="purchaseConfirmationAccount" href="{{ route('account.show') }}">View account</a>
        </div>
    </div>
</section>

<section class="store-modal-layer" id="bagLayer" hidden inert>
    <div class="store-dialog store-checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="bagTitle">
        <div class="store-dialog-head">
            <div class="store-checkout-title-lockup">
                <span>Secure checkout</span>
                <h2 id="bagTitle">Complete your order</h2>
            </div>
            <button class="store-icon-button store-checkout-close" type="button" data-close="bagLayer" aria-label="Close checkout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        </div>
        <div class="store-checkout-grid">
            <section class="store-checkout-panel store-checkout-order-panel" aria-labelledby="bagOrderTitle">
                <div class="store-checkout-section-heading">
                    <h3 id="bagOrderTitle">Your order</h3>
                    <span>1 month access</span>
                </div>
                <div class="store-bag-list" id="bagList"></div>
                <div class="store-checkout-benefits" aria-label="Royal Pass benefits">
                    <span>Exclusive content</span>
                    <span>Royal community</span>
                    <span>Member access</span>
                </div>
                <p class="store-checkout-order-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 10v6M12 7h.01" />
                    </svg>
                    PayPal charges in USD. Every completed purchase activates Royal Pass for 1 month on this account.
                </p>
            </section>
            <section class="store-checkout-panel store-checkout-contact-panel" id="checkoutPanel" aria-labelledby="bagContactTitle">
                <div class="store-checkout-section-heading">
                    <h3 id="bagContactTitle">Contact details</h3>
                    <span>Name + email only</span>
                </div>
                <div class="store-contact-grid">
                    <div class="store-field">
                        <label for="nameField">Name</label>
                        <input class="store-input" id="nameField" type="text" value="" autocomplete="name" required>
                    </div>
                    <div class="store-field">
                        <label for="emailField">Email</label>
                        <input class="store-input" id="emailField" type="email" value="" autocomplete="email" required>
                    </div>
                    <div class="store-field store-checkout-country">
                        <label for="countryField">Country</label>
                        <select class="store-input" id="countryField" autocomplete="country-name" required>
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
                    </div>
                </div>
                <div class="store-total-row">
                    <span>
                        <strong>Total</strong>
                        <small>Charged in USD</small>
                    </span>
                    <strong id="bagTotal">$0</strong>
                </div>
                <div
                    class="store-paypal-buttons"
                    id="paypalButtons"
                    data-paypal-client-id="{{ config('services.paypal.client_id') }}"
                    data-create-order-endpoint="{{ route('checkout.paypal.orders') }}"
                    data-cancel-order-endpoint="{{ route('checkout.paypal.orders.cancel') }}"
                    data-capture-endpoint="{{ route('checkout.paypal') }}"
                ></div>
                <p class="store-checkout-security">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="5" y="10" width="14" height="10" rx="2" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    Secure payment processed by PayPal. Your payment details are never stored here.
                </p>
                <p class="store-checkout-note store-checkout-payment-status" id="paymentStatus" role="status" aria-live="polite">Add a product to enable PayPal checkout.</p>
            </section>
        </div>
    </div>
</section>

<div class="store-toast" id="storeToast" role="status" aria-live="polite"></div>
