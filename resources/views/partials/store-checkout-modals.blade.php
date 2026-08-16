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
    <div class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="bagTitle">
        <div class="store-dialog-head">
            <h2 id="bagTitle">Checkout</h2>
            <button class="store-icon-button" type="button" data-close="bagLayer" aria-label="Close checkout">Close</button>
        </div>
        <div class="store-checkout-grid">
            <div class="store-checkout-panel">
                <h3>Product</h3>
                <p class="store-checkout-note">Selected currency is a reference. PayPal checkout is charged in USD. Every completed purchase activates Royal Pass for 1 month on this account.</p>
                <div class="store-bag-list" id="bagList"></div>
                <div class="store-contact-grid">
                    <div class="store-field">
                        <label for="nameField">Name</label>
                        <input class="store-input" id="nameField" type="text" value="" autocomplete="name" required>
                    </div>
                    <div class="store-field">
                        <label for="emailField">Email</label>
                        <input class="store-input" id="emailField" type="email" value="" autocomplete="email" required>
                    </div>
                    <div class="store-field">
                        <label for="phoneField">Phone (optional)</label>
                        <input
                            class="store-input"
                            id="phoneField"
                            type="tel"
                            value=""
                            autocomplete="tel"
                            inputmode="tel"
                            placeholder="+507 6000 0000"
                            pattern="^\+[1-9][0-9]{6,14}$"
                        >
                    </div>
                    <div class="store-field">
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
                    <span>Total</span>
                    <strong id="bagTotal">$0</strong>
                </div>
            </div>
            <div class="store-checkout-panel" id="checkoutPanel">
                <h3>PayPal Checkout</h3>
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
            </div>
        </div>
    </div>
</section>

<div class="store-toast" id="storeToast" role="status" aria-live="polite"></div>
