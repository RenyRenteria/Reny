const money = (cents) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(cents / 100);

document.querySelectorAll('[data-yoycol-checkout]').forEach((root) => {
    const form = root.querySelector('[data-shipping-form]');
    const fieldset = form.querySelector('fieldset');
    const status = root.querySelector('[data-checkout-status]');
    const summary = root.querySelector('[data-quote-summary]');
    const level = root.querySelector('[data-shipping-level]');
    const buttons = root.querySelector('[data-paypal-buttons]');
    let quote = null;
    let generation = 0;
    let pendingOrder = null;
    let captureStarted = false;
    let completed = false;
    let frozenCustomer = null;
    let sdkPromise = null;
    let createFailure = null;

    const post = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The request failed. Please try again.');
        return data;
    };
    const lock = (value) => { fieldset.disabled = value; level.disabled = value; };
    const invalidate = () => {
        generation += 1;
        quote = null;
        summary.hidden = true;
        buttons.replaceChildren();
    };
    form.addEventListener('input', () => {
        if (captureStarted || completed || pendingOrder) return;
        invalidate();
        status.textContent = 'Calculate shipping to update your total.';
    });
    form.elements.variant_id.addEventListener('change', () => {
        const image = form.elements.variant_id.selectedOptions[0].dataset.image;
        if (image && root.querySelector('[data-product-image]')) root.querySelector('[data-product-image]').src = image;
    });
    const loadPayPal = () => {
        if (window.paypal?.Buttons) return Promise.resolve(window.paypal);
        if (sdkPromise) return sdkPromise;
        sdkPromise = new Promise((resolve, reject) => {
            if (!root.dataset.paypalClient) { reject(new Error('Payments are currently unavailable. Please contact us.')); return; }
            const script = document.createElement('script');
            script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(root.dataset.paypalClient)}&currency=USD&intent=capture&components=buttons`;
            script.onload = () => window.paypal?.Buttons ? resolve(window.paypal) : reject(new Error('PayPal could not load. Please refresh.'));
            script.onerror = () => reject(new Error('PayPal could not load. Please refresh.'));
            document.head.append(script);
        });
        return sdkPromise;
    };
    const payload = () => ({
        identifier: frozenCustomer.email,
        customer_name: `${frozenCustomer.firstName} ${frozenCustomer.lastName}`,
        customer_email: frozenCustomer.email,
        customer_phone: frozenCustomer.phone,
        customer_country: frozenCustomer.country,
        product_keys: [root.dataset.productKey], currency: 'USD',
    });
    const cancel = async () => {
        if (captureStarted || completed) return;
        try {
            if (pendingOrder) await post(root.dataset.cancelUrl, { paypal_order_id: pendingOrder });
        } catch {
            // The PayPal order was never captured. A fresh quote is required either way.
        }
        pendingOrder = null;
        lock(false);
        invalidate();
        status.textContent = 'Payment cancelled. Calculate shipping again when you are ready.';
    };
    const calculate = async (shippingLevel = null) => {
        if (!form.reportValidity() || captureStarted || completed || pendingOrder) return;
        invalidate();
        const current = generation;
        const fields = new FormData(form);
        const address = Object.fromEntries(['firstName', 'lastName', 'email', 'phone', 'country', 'province', 'city', 'address1', 'address2', 'zip'].map((key) => [key, fields.get(key)]));
        lock(true);
        status.textContent = 'Calculating shipping…';
        try {
            const nextQuote = await post(root.dataset.quoteUrl, { variant_id: fields.get('variant_id'), quantity: Number(fields.get('quantity')), address, shipping_level: shippingLevel });
            if (generation !== current) return;
            quote = nextQuote;
            frozenCustomer = address;
            level.replaceChildren(...quote.levels.map((item) => {
                const option = document.createElement('option');
                option.value = item.code;
                option.textContent = `${item.name} · ${money(item.amount_cents)}`;
                option.selected = item.code === quote.shipping_level;
                return option;
            }));
            root.querySelector('[data-shipping-estimate]').textContent = quote.levels.find((item) => item.code === quote.shipping_level)?.description || '';
            root.querySelector('[data-subtotal]').textContent = money(quote.subtotal_cents);
            root.querySelector('[data-shipping]').textContent = money(quote.shipping_cents);
            root.querySelector('[data-total]').textContent = `${money(quote.amount_cents)} USD`;
            summary.hidden = false;
            status.textContent = 'Total confirmed. Continue with PayPal.';
            const paypal = await loadPayPal();
            if (generation !== current) return;
            await paypal.Buttons({
                createOrder: async () => {
                    if (!quote || Date.now() >= Date.parse(quote.expires_at)) {
                        invalidate();
                        throw new Error('The shipping quote expired. Please calculate shipping again.');
                    }
                    lock(true);
                    try {
                        createFailure = null;
                        const data = await post(root.dataset.createUrl, { ...payload(), yoycol_quote_id: quote.quote_id });
                        pendingOrder = data.paypal_order_id;
                        return pendingOrder;
                    } catch (error) {
                        createFailure = error.message;
                        throw error;
                    }
                },
                onApprove: async (data) => {
                    captureStarted = true;
                    lock(true);
                    status.textContent = 'Confirming your payment. Please keep this page open.';
                    try {
                        const result = await post(root.dataset.captureUrl, { ...payload(), paypal_order_id: data.orderID });
                        completed = true;
                        buttons.replaceChildren();
                        status.textContent = 'Payment confirmed. Your merch order is being prepared. Follow its progress in your account.';
                        const link = root.querySelector('[data-account-link]');
                        link.href = result.account_url;
                        link.hidden = false;
                    } catch (error) {
                        buttons.replaceChildren();
                        status.textContent = `${error.message} Payment confirmation needs review. Do not pay again; contact us.`;
                    }
                },
                onCancel: cancel,
                onError: (error) => {
                    if (completed || captureStarted) return;
                    lock(false);
                    // Never mask an actionable server validation message or cancel an approved capture.
                    const message = createFailure || error?.message || 'PayPal could not start. Please calculate shipping again.';
                    if (pendingOrder) {
                        cancel().then(() => { status.textContent = message; });
                    } else {
                        invalidate();
                        status.textContent = message;
                    }
                },
            }).render(buttons);
        } catch (error) {
            status.textContent = error.message;
        } finally {
            if (!pendingOrder && !captureStarted && !completed) lock(false);
        }
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); calculate(); });
    level.addEventListener('change', () => calculate(level.value));
});
