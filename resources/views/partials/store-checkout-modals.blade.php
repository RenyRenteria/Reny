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
                <button class="store-button" id="detailBuy" type="button">Checkout with PayPal</button>
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
                <h3>Product</h3>
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
