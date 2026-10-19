@extends('yoycol.layout')
@section('title', $product->title)
@section('content')
<a href="{{ route('merch') }}" class="yoycol-back">← Back to merch</a>
<div class="yoycol-checkout" data-yoycol-checkout
    data-quote-url="{{ route('yoycol.quote', $product) }}"
    data-create-url="{{ route('checkout.paypal.orders') }}"
    data-capture-url="{{ route('checkout.paypal') }}"
    data-cancel-url="{{ route('checkout.paypal.orders.cancel') }}"
    data-product-key="{{ $product->productKey() }}"
    data-paypal-client="{{ config('services.paypal.client_id') }}">
    <section class="yoycol-product">
        @if ($product->image_url)<img data-product-image src="{{ $product->image_url }}" alt="{{ $product->title }}">@endif
        <p class="yoycol-eyebrow">OFFICIAL MERCH · MADE TO ORDER</p>
        <h1>{{ $product->title }}</h1>
        <p>{{ $product->description }}</p>
        <strong class="yoycol-price">${{ number_format($product->price_cents / 100, 2) }} USD</strong>
        <p>Shipping calculated below. Includes one month of Royal Pass.</p>
        <p>Made after your purchase. Shipping estimates exclude production time. Import duties and local import taxes, if applicable, are paid by the recipient.</p>
    </section>
    <section class="yoycol-panel" aria-label="Delivery and payment">
        <h2>Your delivery</h2>
        <form data-shipping-form>
            <fieldset>
                <div class="yoycol-fields">
                    <label class="yoycol-wide">Size / color
                        <select name="variant_id" required>
                            @foreach ($product->variants as $variant)
                                <option value="{{ $variant['id'] }}" data-image="{{ $variant['image_url'] ?? $product->image_url }}">{{ $variant['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Quantity<input name="quantity" type="number" min="1" max="10" value="1" required></label>
                    <label>Country<select name="country" autocomplete="country" required>
                        <option value="">Select country</option>
                        @foreach (config('yoycol.countries') as $code => $country)<option value="{{ $code }}">{{ $country['name'] }}</option>@endforeach
                    </select></label>
                    <label>First name<input name="firstName" autocomplete="given-name" maxlength="60" required></label>
                    <label>Last name<input name="lastName" autocomplete="family-name" maxlength="60" required></label>
                    <label class="yoycol-wide">Email<input name="email" type="email" autocomplete="email" value="{{ auth()->user()?->email }}" maxlength="255" required></label>
                    <label class="yoycol-wide">Delivery phone<input name="phone" type="tel" autocomplete="tel" placeholder="+50760000000" pattern="\+[1-9][0-9]{6,14}" required><small>Include country code. Used for delivery.</small></label>
                    <label class="yoycol-wide">Street address<input name="address1" autocomplete="address-line1" maxlength="200" required></label>
                    <label class="yoycol-wide">Apartment / suite (optional)<input name="address2" autocomplete="address-line2" maxlength="200"></label>
                    <label>City<input name="city" autocomplete="address-level2" maxlength="100" required></label>
                    <label>State / province<input name="province" autocomplete="address-level1" maxlength="100" required></label>
                    <label>Postal code<input name="zip" autocomplete="postal-code" maxlength="20" required></label>
                </div>
                <button class="yoycol-button" type="submit">Calculate shipping</button>
            </fieldset>
        </form>
        <div data-quote-summary hidden>
            <label>Shipping service<select data-shipping-level></select></label>
            <p data-shipping-estimate></p>
            <dl class="yoycol-totals"><div><dt>Products</dt><dd data-subtotal></dd></div><div><dt>Shipping</dt><dd data-shipping></dd></div><div><dt>Total</dt><dd data-total></dd></div></dl>
            <p>Pay securely with PayPal. Your order will be prepared after payment confirmation.</p>
        </div>
        <p data-checkout-status role="status" aria-live="polite"></p>
        <div data-paypal-buttons></div>
        <a data-account-link class="yoycol-button" hidden>View my order</a>
        <noscript>Enable JavaScript to calculate shipping and pay.</noscript>
    </section>
</div>
@endsection
