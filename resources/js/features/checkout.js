import {
    elementAnalyticsLabel,
    normalizeAnalyticsKey,
    trackElementEvent,
    trackEvent,
} from './analytics.js';

const initializeStoreInteractions = (root = document) => {
    const scope = root === document ? document : root;
    const storeShell = scope.querySelector?.('.store-shell') || document.querySelector('.store-shell');
    const storeCheckoutLayer = document.getElementById('bagLayer');
    const commerceRoot = storeShell
        || storeCheckoutLayer
        || scope.querySelector?.('[data-free-event-rsvp]')
        || scope.querySelector?.('[data-buy]')
        || scope.querySelector?.('[data-rsvp]')
        || document.querySelector('[data-free-event-rsvp]')
        || document.querySelector('[data-buy]')
        || document.querySelector('[data-rsvp]');

    if (!commerceRoot) {
        return;
    }

    const prices = {
        deluxe: 24,
        singles: 8,
        royal: 3.99,
        merch: 48,
        print: 86,
        concert: 0,
        listening: 15,
        making: 0,
    };

    const currencies = {
        usd: { symbol: '$', rate: 1, decimals: 0 },
        eur: { symbol: '€', rate: 0.92, decimals: 0 },
        gbp: { symbol: '£', rate: 0.78, decimals: 0 },
        dop: { symbol: 'RD$', rate: 59, decimals: 0 },
    };

    const products = {};
    let currency = 'usd';
    const settlementCurrency = 'usd';
    let bag = [];
    let activeProduct = null;
    let activeFreeEventButton = null;
    let focusedBeforeStoreModal = null;

    const storeToast = document.getElementById('storeToast');
    const bagCount = document.getElementById('bagCount');
    const bagList = document.getElementById('bagList');
    const bagTotal = document.getElementById('bagTotal');
    const nameField = document.getElementById('nameField');
    const emailField = document.getElementById('emailField');
    const phoneField = document.getElementById('phoneField');
    const countryField = document.getElementById('countryField');
    const freeEventRsvpLayer = document.getElementById('freeEventRsvpLayer');
    const freeEventRsvpForm = document.getElementById('freeEventRsvpForm');
    const freeEventRsvpTitle = document.getElementById('freeEventRsvpTitle');
    const freeEventRsvpEventName = document.getElementById('freeEventRsvpEventName');
    const freeEventRsvpName = document.getElementById('freeEventRsvpName');
    const freeEventRsvpEmail = document.getElementById('freeEventRsvpEmail');
    const freeEventRsvpCountry = document.getElementById('freeEventRsvpCountry');
    const freeEventRsvpSubmit = document.getElementById('freeEventRsvpSubmit');
    const freeEventRsvpStatus = document.getElementById('freeEventRsvpStatus');
    const paypalButtons = document.getElementById('paypalButtons');
    const paymentStatus = document.getElementById('paymentStatus');
    const paymentButtons = [...document.querySelectorAll('.store-payments button[data-payment-method]')];
    const freeEventRsvpButtons = [...document.querySelectorAll('[data-free-event-rsvp]')];
    const rsvpButtons = [...document.querySelectorAll('[data-rsvp]')];
    const countdownNodes = [...document.querySelectorAll('[data-countdown-at]')];
    const royalPassOptions = [...document.querySelectorAll('[data-royal-pass-option]')];
    const tierLabel = document.getElementById('tierLabel');
    const purchaseConfirmationTitle = document.getElementById('purchaseConfirmationTitle');
    const purchaseConfirmationMessage = document.getElementById('purchaseConfirmationMessage');
    const purchaseConfirmationAccount = document.getElementById('purchaseConfirmationAccount');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let activePaymentMethod = 'paypal';
    let selectedRoyalPassProduct = null;
    let paypalButtonsRendered = false;
    let paypalButtonsLoading = false;
    let paypalSdkPromise = null;
    let activePayPalOrderId = null;

    document.querySelectorAll('[data-detail]').forEach((button) => {
        products[button.dataset.detail] = {
            name: button.dataset.name,
            type: button.dataset.type,
            priceKey: button.dataset.priceKey,
            availability: button.dataset.availability,
            points: button.dataset.points,
            pass: button.dataset.pass,
            access: button.dataset.access,
            summary: button.dataset.summary,
            image: button.dataset.image,
            cta: button.dataset.cta || 'Add to bag',
        };
    });

    document.querySelectorAll('[data-price][data-price-value]').forEach((node) => {
        const value = Number.parseFloat(node.dataset.priceValue || '');

        if (Number.isFinite(value)) {
            prices[node.dataset.price] = value;
        }
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        const key = button.dataset.buy;

        if (!key) {
            return;
        }

        const buyPriceValue = Number.parseFloat(button.dataset.buyPriceValue || '');

        if (Number.isFinite(buyPriceValue)) {
            prices[key] = buyPriceValue;
        }

        products[key] = {
            ...products[key],
            name: button.dataset.buyName || products[key]?.name || key,
            type: button.dataset.buyType || products[key]?.type || 'Product',
            priceKey: key,
            availability: products[key]?.availability || 'Available',
            points: products[key]?.points || '+0 pts',
            pass: products[key]?.pass || 'No Royal Pass required',
            access: products[key]?.access || 'Checkout unlocks in profile',
            summary: button.dataset.buySummary || products[key]?.summary || 'Store checkout',
            image: button.dataset.buyImage || products[key]?.image,
            cta: button.textContent?.trim() || products[key]?.cta || 'Add to bag',
        };
    });

    const countdownLabel = (target, endedLabel) => {
        const seconds = Math.max(0, Math.floor((target.getTime() - Date.now()) / 1000));

        if (!Number.isFinite(seconds) || seconds <= 0) {
            return endedLabel || 'Today';
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);

        if (days > 0) {
            return `${days}D ${hours}H`;
        }

        const minutes = Math.floor((seconds % 3600) / 60);

        if (hours > 0) {
            return `${hours}H ${minutes}M`;
        }

        return `${Math.max(1, minutes)}M`;
    };

    const renderCountdowns = () => {
        countdownNodes.forEach((node) => {
            const target = new Date(node.dataset.countdownAt || '');

            if (Number.isNaN(target.getTime())) {
                return;
            }

            node.textContent = countdownLabel(target, node.dataset.countdownEndedLabel);
        });
    };

    if (countdownNodes.length > 0) {
        renderCountdowns();
        window.clearInterval(window.renyStoreCountdownInterval);
        window.renyStoreCountdownInterval = window.setInterval(renderCountdowns, 60000);
    }

    const money = (value, suffix = '') => {
        const current = currencies[currency];
        const converted = value * current.rate;
        const hasFractionalAmount = Math.abs(converted - Math.round(converted)) > Number.EPSILON;
        const decimals = hasFractionalAmount ? Math.max(current.decimals, 2) : current.decimals;
        const amount = converted.toLocaleString('en-US', {
            maximumFractionDigits: decimals,
            minimumFractionDigits: decimals,
        });

        return `${current.symbol}${amount}${suffix}`;
    };

    const showStoreToast = (message) => {
        if (!storeToast) {
            return;
        }

        storeToast.textContent = message;
        storeToast.classList.add('is-visible');
        window.clearTimeout(showStoreToast.timeout);
        showStoreToast.timeout = window.setTimeout(() => {
            storeToast.classList.remove('is-visible');
        }, 2200);
    };

    const setPaymentStatus = (message) => {
        if (paymentStatus) {
            paymentStatus.textContent = message;
        }
    };

    const checkoutError = (message, checkoutState = 'validation_failed', reason = null) => Object.assign(new Error(message), {
        checkoutState,
        reason: reason || normalizeAnalyticsKey(message),
        userMessage: message,
    });

    const trackPaymentState = (method, checkoutState, details = {}) => {
        const eventName = checkoutState === 'payment_success'
            ? 'store_payment_succeeded'
            : checkoutState === 'payment_started'
                ? 'store_checkout_started'
                : 'store_payment_failed';

        trackEvent(eventName, {
            item_type: checkoutState === 'payment_started' ? 'checkout' : 'payment_method',
            item_id: method,
            method,
            checkout_state: checkoutState,
            result: checkoutState,
            ...details,
        });
    };

    const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    const normalizeInternationalPhone = (value) => String(value || '').trim().replace(/[()\s.-]/g, '');

    const isValidPhone = (value) => /^\+[1-9][0-9]{6,14}$/.test(normalizeInternationalPhone(value));

    const markFieldValidity = (field, valid) => {
        if (!field) {
            return;
        }

        field.setAttribute('aria-invalid', valid ? 'false' : 'true');
    };

    const customerDetailsComplete = () => {
        const name = nameField?.value?.trim() || '';
        const email = emailField?.value?.trim() || '';
        const phone = phoneField?.value?.trim() || '';
        const country = countryField?.value?.trim() || '';

        return name.length > 0 && isValidEmail(email) && isValidPhone(phone) && country.length > 0;
    };

    const contactPayload = () => {
        const name = nameField?.value?.trim() || '';
        const email = emailField?.value?.trim() || '';
        const phone = phoneField?.value?.trim() || '';
        const country = countryField?.value?.trim() || '';

        if (!name) {
            markFieldValidity(nameField, false);
            throw checkoutError('Add your name.', 'validation_failed', 'missing_name');
        }

        if (!isValidEmail(email)) {
            markFieldValidity(emailField, false);
            throw checkoutError('Add a valid receipt email.', 'validation_failed', 'invalid_email');
        }

        if (!isValidPhone(phone)) {
            markFieldValidity(phoneField, false);
            throw checkoutError('Add a valid international phone number.', 'validation_failed', 'invalid_phone');
        }

        if (!country) {
            markFieldValidity(countryField, false);
            throw checkoutError('Select your country.', 'validation_failed', 'missing_country');
        }

        return {
            identifier: email,
            customer_name: name,
            customer_email: email,
            customer_phone: normalizeInternationalPhone(phone),
            customer_country: country,
        };
    };

    const checkoutPayload = () => {
        if (!bag.length) {
            throw checkoutError('Add a product first.', 'validation_failed', 'empty_cart');
        }

        const contact = contactPayload();

        return {
            identifier: contact.identifier,
            customer_name: contact.customer_name,
            customer_email: contact.customer_email,
            customer_phone: contact.customer_phone,
            customer_country: contact.customer_country,
            product_keys: [...bag],
            currency: settlementCurrency.toUpperCase(),
        };
    };

    const postCheckoutJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const checkoutState = response.status === 422 ? 'validation_failed' : 'payment_failed';

            throw checkoutError(validationMessage || payload.message || 'Checkout failed.', checkoutState, payload.message || 'checkout_request_failed');
        }

        return payload;
    };

    const cancelPendingPayPalOrder = async () => {
        const paypalOrderId = activePayPalOrderId;
        const endpoint = paypalButtons?.dataset.cancelOrderEndpoint;
        activePayPalOrderId = null;

        if (!paypalOrderId || !endpoint) {
            return;
        }

        await postCheckoutJson(endpoint, {
            paypal_order_id: paypalOrderId,
        });
    };

    const rsvpError = (message, reason = null) => Object.assign(new Error(message), {
        reason: reason || normalizeAnalyticsKey(message),
        userMessage: message,
    });

    const postRsvpJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const fallback = response.status === 401
                ? 'Sign in before saving RSVP.'
                : response.status === 419
                    ? 'Refresh this page and try RSVP again.'
                    : 'RSVP could not be saved. Try again.';

            throw rsvpError(validationMessage || payload.message || fallback, payload.message || response.status);
        }

        return payload;
    };

    const postFreeEventRsvpJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const fallback = response.status === 419
                ? 'Refresh this page and try again.'
                : 'Registration could not be saved. Try again.';

            throw rsvpError(validationMessage || payload.message || fallback, payload.message || response.status);
        }

        return payload;
    };

    const loadPayPalSdk = (clientId) => {
        if (window.paypal?.Buttons) {
            return Promise.resolve(window.paypal);
        }

        if (paypalSdkPromise) {
            return paypalSdkPromise;
        }

        paypalSdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = 'paypal-sdk';
            script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(settlementCurrency.toUpperCase())}&intent=capture`;
            script.async = true;
            script.onload = () => {
                if (window.paypal?.Buttons) {
                    resolve(window.paypal);
                    return;
                }

                reject(checkoutError('PayPal checkout could not load.', 'unavailable', 'paypal_sdk_unavailable'));
            };
            script.onerror = () => reject(checkoutError('PayPal checkout could not load.', 'unavailable', 'paypal_sdk_unavailable'));
            document.head.append(script);
        });

        return paypalSdkPromise;
    };

    const showPurchaseConfirmation = (payload) => {
        const royalActive = payload.royal_status === 'royal_active';

        if (purchaseConfirmationTitle) {
            purchaseConfirmationTitle.textContent = royalActive ? 'Royal Pass confirmed' : 'Purchase confirmed';
        }

        if (purchaseConfirmationMessage) {
            purchaseConfirmationMessage.textContent = royalActive
                ? 'Your Royal Pass is active. Confirmation was saved to your account.'
                : 'Payment confirmed. Your purchase was saved to your account.';
        }

        if (purchaseConfirmationAccount) {
            purchaseConfirmationAccount.href = payload.account_url || purchaseConfirmationAccount.href;
        }

        openStoreLayer('purchaseConfirmationLayer');
    };

    const completeApprovedCheckout = (payload) => {
        if (tierLabel && payload.royal_status === 'royal_active') {
            tierLabel.textContent = 'ROYAL MEMBER';
        }

        bag = [];
        renderBag();
        closeStoreLayer('bagLayer');
        showStoreToast('PayPal confirmed. Hub updated.');
        showPurchaseConfirmation(payload);
    };

    const renderPayPalButtons = async () => {
        if (!paypalButtons) {
            return;
        }

        if (paypalButtonsRendered) {
            setPaymentStatus(customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.');
            return;
        }

        if (paypalButtonsLoading) {
            return;
        }

        paypalButtonsLoading = true;
        const clientId = paypalButtons.dataset.paypalClientId;

        try {
            if (!clientId) {
                trackPaymentState('paypal', 'unavailable', {
                    reason: 'paypal_not_configured',
                });
                throw checkoutError('PayPal is not configured.', 'unavailable', 'paypal_not_configured');
            }

            setPaymentStatus('Loading PayPal checkout...');
            const paypal = await loadPayPalSdk(clientId);
            paypalButtons.replaceChildren();

            await paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                },
                createOrder: async () => {
                    const payload = checkoutPayload();
                    [nameField, emailField, phoneField, countryField].forEach((field) => markFieldValidity(field, true));
                    setPaymentStatus('Creating PayPal order...');
                    trackPaymentState('paypal', 'payment_started', {
                        item_count: payload.product_keys.length,
                        currency: payload.currency,
                    });
                    const order = await postCheckoutJson(paypalButtons.dataset.createOrderEndpoint, payload);
                    activePayPalOrderId = order.paypal_order_id;
                    setPaymentStatus('Approve payment in PayPal.');

                    return order.paypal_order_id;
                },
                onApprove: async (data) => {
                    const payload = checkoutPayload();
                    const paypalOrderId = data.orderID || activePayPalOrderId;
                    setPaymentStatus('Capturing approved PayPal payment...');
                    const capture = await postCheckoutJson(paypalButtons.dataset.captureEndpoint, {
                        ...payload,
                        paypal_order_id: paypalOrderId,
                    });

                    activePayPalOrderId = null;
                    completeApprovedCheckout(capture);
                    trackPaymentState('paypal', 'payment_success', {
                        paypal_order_id: paypalOrderId,
                    });
                },
                onCancel: async () => {
                    await cancelPendingPayPalOrder().catch((error) => console.warn(error));
                    setPaymentStatus('PayPal checkout canceled. No purchase was recorded.');
                    showStoreToast('PayPal checkout canceled.');
                    trackPaymentState('paypal', 'payment_failed', {
                        reason: 'canceled',
                    });
                },
                onError: (error) => {
                    console.error(error);
                    cancelPendingPayPalOrder().catch((cancelError) => console.warn(cancelError));
                    setPaymentStatus(error.userMessage || 'PayPal checkout failed. No purchase was recorded.');
                    showStoreToast(error.userMessage || 'PayPal checkout failed.');
                    trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                        reason: error.userMessage || error.message || 'paypal_error',
                    });
                },
            }).render(paypalButtons);

            paypalButtonsRendered = true;
            setPaymentStatus(customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.');
        } finally {
            paypalButtonsLoading = false;
        }
    };

    const updateStorePrices = () => {
        document.querySelectorAll('[data-price]').forEach((node) => {
            const key = node.dataset.price;
            const suffix = key === 'royal' ? '/mo' : '';

            node.textContent = money(prices[key] || 0, suffix);
        });
    };

    const getStoreFocusable = (layer) => [...layer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.disabled && node.offsetParent !== null);

    const trapStoreFocus = (layer, event) => {
        const focusable = getStoreFocusable(layer);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const openStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        focusedBeforeStoreModal = document.activeElement;
        layer.hidden = false;
        layer.removeAttribute('inert');
        document.body.classList.add('has-modal-open');
        getStoreFocusable(layer)[0]?.focus();
    };

    const closeStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        layer.hidden = true;
        layer.setAttribute('inert', '');
        document.body.classList.remove('has-modal-open');
        focusedBeforeStoreModal?.focus();
    };

    const paymentMethodLabel = (method) => ({
        apple_pay: 'Apple Pay',
        card: 'Card',
        paypal: 'PayPal',
    }[method] || method);

    const unavailableReason = (method) => paymentButtons.find((button) => button.dataset.paymentMethod === method)?.dataset.unavailableReason
        || `${method}_provider_not_configured`;

    const isPaymentMethodAvailable = (method) => paymentButtons.find((button) => button.dataset.paymentMethod === method)?.dataset.providerAvailable === 'true';

    const refreshCheckoutControls = ({ preserveStatus = false } = {}) => {
        const hasItems = bag.length > 0;

        paymentButtons.forEach((button) => {
            button.disabled = !hasItems;
        });

        if (paypalButtons) {
            paypalButtons.hidden = activePaymentMethod !== 'paypal' || !hasItems;
        }

        if (!hasItems) {
            if (!preserveStatus) {
                setPaymentStatus('Add a product to enable PayPal checkout.');
            }
            return;
        }

        if (activePaymentMethod === 'paypal') {
            if (!preserveStatus) {
                setPaymentStatus(paypalButtonsRendered
                    ? (customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.')
                    : 'Loading PayPal checkout...');
            }
            return;
        }

        if (!preserveStatus) {
            setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
        }
    };

    const selectPaymentMethod = (method, { track = true } = {}) => {
        activePaymentMethod = method;

        paymentButtons.forEach((button) => {
            const active = button.dataset.paymentMethod === method;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });

        refreshCheckoutControls();

        if (!track) {
            return;
        }

        trackEvent('store_payment_method_selected', {
            item_type: 'payment_method',
            item_id: method,
            method,
            checkout_state: isPaymentMethodAvailable(method) ? 'selected' : 'unavailable',
            result: 'selected',
        });

        if (!isPaymentMethodAvailable(method)) {
            trackPaymentState(method, 'unavailable', {
                reason: unavailableReason(method),
            });
        }
    };

    const renderBag = () => {
        if (!bagList || !bagTotal) {
            return;
        }

        if (bagCount) {
            bagCount.textContent = String(bag.length);
        }
        bagList.replaceChildren();

        if (!bag.length) {
            const empty = document.createElement('div');
            empty.className = 'store-bag-item';
            empty.innerHTML = `<span>Bag is empty</span><strong>${money(0)}</strong>`;
            bagList.append(empty);
            bagTotal.textContent = money(0);
            refreshCheckoutControls();
            return;
        }

        let total = 0;

        bag.forEach((key) => {
            const product = products[key];

            if (!product) {
                return;
            }

            const priceKey = product.priceKey || key;
            total += prices[priceKey] || 0;

            const item = document.createElement('div');
            item.className = 'store-bag-item';

            const copy = document.createElement('div');
            copy.className = 'store-bag-copy';

            const name = document.createElement('strong');
            name.textContent = product.name;

            const meta = document.createElement('span');
            meta.textContent = product.type || 'Product';

            const summary = document.createElement('p');
            summary.textContent = product.summary || 'Store checkout';

            const price = document.createElement('strong');
            price.className = 'store-bag-price';
            price.textContent = money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : '');

            if (product.image) {
                const image = document.createElement('img');
                image.className = 'store-bag-image';
                image.src = product.image;
                image.alt = '';
                image.decoding = 'async';
                item.append(image);
            }

            copy.append(name, meta, summary);
            item.append(copy, price);
            bagList.append(item);
        });

        bagTotal.textContent = money(total);
        refreshCheckoutControls();
    };

    const setCheckoutProduct = (key) => {
        if (!products[key]) {
            return false;
        }

        bag = [key];
        renderBag();

        return true;
    };

    const addToBag = (key) => {
        if (!setCheckoutProduct(key)) {
            return false;
        }

        trackEvent('store_product_added', {
            item_type: products[key].type || 'product',
            item_id: key,
            item_label: products[key].name,
            result: 'added',
        });

        return true;
    };

    const refreshRoyalPassOptions = () => {
        royalPassOptions.forEach((option) => {
            const selected = selectedRoyalPassProduct === option.dataset.royalPassOption;
            const container = option.closest('[data-royal-pass-container]') || option;
            const cta = container.querySelector('[data-royal-pass-cta]');

            container.classList.toggle('is-selected', selected);
            container.dataset.royalPassSelected = selected ? 'true' : 'false';
            option.classList.toggle('is-selected', selected);
            option.setAttribute('aria-pressed', selected ? 'true' : 'false');

            if (cta) {
                cta.disabled = !selected;
                cta.setAttribute('aria-disabled', selected ? 'false' : 'true');
            }
        });
    };

    const selectRoyalPassOption = (option) => {
        const key = option.dataset.royalPassOption;

        if (!key || !products[key]) {
            return;
        }

        selectedRoyalPassProduct = key;
        refreshRoyalPassOptions();
        trackElementEvent(option, 'royal_pass_plan_selected', {
            item_type: 'subscription',
            item_id: key,
            item_label: products[key]?.name || 'Royal Pass',
            result: 'selected',
        });
    };

    const initializeRoyalPassSelection = () => {
        if (selectedRoyalPassProduct || royalPassOptions.length === 0) {
            return;
        }

        const preselectedOption = royalPassOptions.find((option) => {
            const container = option.closest('[data-royal-pass-container]');

            return option.getAttribute('aria-pressed') === 'true'
                || container?.dataset.royalPassSelected === 'true';
        }) || royalPassOptions[0];
        const key = preselectedOption?.dataset.royalPassOption;

        if (!key || !products[key]) {
            return;
        }

        selectedRoyalPassProduct = key;
        refreshRoyalPassOptions();
    };

    const openBuyUrl = (button) => {
        if (!button.dataset.buyUrl) {
            return false;
        }

        const url = new URL(button.dataset.buyUrl, window.location.href);

        trackElementEvent(button, 'store_checkout_link_opened', {
            item_type: button.dataset.buyType || 'product',
            item_id: button.dataset.buy,
            destination: url.pathname,
            result: 'opened',
        });

        window.location.assign(url.href);

        return true;
    };

    const initializeVisiblePayPalCheckout = async () => {
        if (activePaymentMethod !== 'paypal') {
            setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
            trackPaymentState(activePaymentMethod, 'unavailable', {
                reason: unavailableReason(activePaymentMethod),
            });
            return;
        }

        try {
            await renderPayPalButtons();
        } catch (error) {
            setPaymentStatus(error.userMessage || 'PayPal checkout is unavailable.');
            showStoreToast(error.userMessage || 'PayPal checkout is unavailable.');
            trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                reason: error.userMessage || error.message || 'checkout_unavailable',
            });
        } finally {
            refreshCheckoutControls({ preserveStatus: true });
        }
    };

    const openCheckoutModal = (key, { source = 'buy_button', itemType = 'checkout' } = {}) => {
        if (!storeCheckoutLayer || !setCheckoutProduct(key)) {
            return false;
        }

        const product = products[key];
        selectPaymentMethod('paypal', { track: false });
        openStoreLayer('bagLayer');
        trackEvent('store_checkout_started', {
            item_type: itemType || product?.type || 'checkout',
            item_id: key,
            item_label: product?.name,
            item_count: bag.length,
            source,
            result: 'opened',
        });
        void initializeVisiblePayPalCheckout();

        return true;
    };

    const startCheckoutFromBuyButton = (button, { source = 'buy_button' } = {}) => {
        if (button.hasAttribute('data-royal-pass-cta')) {
            initializeRoyalPassSelection();
            const container = button.closest('[data-royal-pass-container]');

            if (container?.dataset.royalPassSelected === 'true') {
                button.disabled = false;
                button.setAttribute('aria-disabled', 'false');
            }
        }

        if (button.dataset.requiresPlanSelection === 'true' && button.disabled) {
            return false;
        }

        if (openCheckoutModal(button.dataset.buy, {
            source,
            itemType: button.dataset.buyType || 'checkout',
        })) {
            return true;
        }

        if (openBuyUrl(button)) {
            return true;
        }

        if (addToBag(button.dataset.buy)) {
            openStoreLayer('bagLayer');
            return true;
        }

        return false;
    };

    const openProductDetail = (key) => {
        const product = products[key];

        if (!product) {
            return;
        }

        activeProduct = key;
        document.getElementById('detailTitle').textContent = product.name;
        document.getElementById('detailText').textContent = product.summary;

        const detailImage = document.getElementById('detailImage');

        if (detailImage && product.image) {
            detailImage.src = product.image;
            detailImage.alt = product.name;
        }

        const grid = document.getElementById('detailGrid');
        const priceKey = product.priceKey || key;

        grid.replaceChildren();
        [
            [money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : ''), 'Price'],
            [product.type, 'Type'],
            [product.availability, 'Availability'],
            [product.points, 'Points'],
            [product.pass, 'Royal Pass'],
            [product.access, 'Access'],
        ].forEach(([value, label]) => {
            const cell = document.createElement('div');
            const strong = document.createElement('strong');
            const span = document.createElement('span');

            strong.textContent = value;
            span.textContent = label;
            cell.append(strong, span);
            grid.append(cell);
        });

        document.getElementById('detailBuy').textContent = product.cta || 'Checkout with PayPal';
        openStoreLayer('detailLayer');
    };

    document.querySelectorAll('.currency-button').forEach((button) => {
        button.addEventListener('click', () => {
            currency = button.dataset.currency || 'usd';
            document.querySelectorAll('.currency-button').forEach((node) => {
                const active = node === button;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            updateStorePrices();
            renderBag();
            trackElementEvent(button, 'store_currency_selected', {
                item_type: 'currency',
                item_id: currency,
                result: 'selected',
            });
        });
    });

    document.querySelectorAll('.store-filter').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter || 'all';

            document.querySelectorAll('.store-filter').forEach((node) => {
                const active = node === button;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            document.querySelectorAll('.store-product-card').forEach((card) => {
                const categories = (card.dataset.category || '').split(' ');
                card.hidden = filter !== 'all' && !categories.includes(filter);
            });

            trackElementEvent(button, 'store_filter_selected', {
                item_type: 'product_filter',
                item_id: filter,
                result: 'selected',
            });
        });
    });

    document.querySelectorAll('[data-detail]').forEach((button) => {
        button.addEventListener('click', () => {
            trackElementEvent(button, 'store_product_opened', {
                item_type: button.dataset.type || 'product',
                item_id: button.dataset.detail,
                result: 'opened',
            });
            openProductDetail(button.dataset.detail);
        });
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        button.addEventListener('click', () => {
            startCheckoutFromBuyButton(button);
        });
    });

    royalPassOptions.forEach((option) => {
        option.addEventListener('click', () => {
            selectRoyalPassOption(option);
        });

        if (option.tagName !== 'BUTTON') {
            option.addEventListener('keydown', (event) => {
                if (!['Enter', ' '].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                selectRoyalPassOption(option);
            });
        }
    });

    initializeRoyalPassSelection();

    document.querySelectorAll('[data-copy-current-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyUrl || window.location.href;
            const successLabel = button.dataset.copySuccess || 'Link copied';
            const originalLabel = button.textContent;

            try {
                if (!navigator.clipboard?.writeText) {
                    throw new Error('clipboard_unavailable');
                }

                await navigator.clipboard.writeText(value);
                button.textContent = successLabel;
                showStoreToast(successLabel);
                trackElementEvent(button, 'store_checkout_link_copied', {
                    item_type: 'checkout_link',
                    destination: new URL(value, window.location.href).pathname,
                    result: 'copied',
                });
            } catch (error) {
                console.warn(error);
                showStoreToast('Copy the checkout URL from the address bar.');
                trackElementEvent(button, 'store_checkout_link_copy_failed', {
                    item_type: 'checkout_link',
                    reason: error.message || 'clipboard_unavailable',
                    result: 'failed',
                });
            } finally {
                window.setTimeout(() => {
                    button.textContent = originalLabel;
                }, 1800);
            }
        });
    });

    const setRsvpStatus = (button, message, { tone = 'neutral', code = null, accountUrl = null } = {}) => {
        const status = document.getElementById(button.dataset.rsvpStatusTarget);

        if (!status) {
            return;
        }

        status.classList.toggle('is-confirmed', tone === 'confirmed');
        status.classList.toggle('is-error', tone === 'error');
        status.replaceChildren();

        const text = document.createElement('span');
        text.textContent = code ? `${message} Code ${code}` : message;
        status.append(text);

        if (accountUrl) {
            const link = document.createElement('a');
            link.href = accountUrl;
            link.textContent = 'View in account';
            status.append(' ', link);
        }
    };

    const renderRsvpSuccess = (button, payload) => {
        const ticket = payload.ticket || {};
        const event = payload.event || {};
        const statusLabel = String(ticket.status || 'reserved').replace(/_/g, ' ');

        button.dataset.rsvpConfirmed = 'true';
        button.textContent = 'RSVP confirmed';
        setRsvpStatus(button, `${event.name || button.dataset.rsvpName || 'Event'} reserved - ${statusLabel}.`, {
            tone: 'confirmed',
            code: ticket.code,
            accountUrl: payload.account_url,
        });
    };

    const setFreeEventRsvpStatus = (message, isError = false) => {
        if (!freeEventRsvpStatus) {
            return;
        }

        freeEventRsvpStatus.textContent = message || '';
        freeEventRsvpStatus.classList.toggle('is-error', isError);
    };

    const resetFreeEventRsvpForm = () => {
        freeEventRsvpForm?.reset();
        setFreeEventRsvpStatus('');
        [freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => markFieldValidity(field, true));
    };

    const openFreeEventRsvpModal = (button) => {
        if (!freeEventRsvpLayer || !freeEventRsvpForm) {
            return false;
        }

        activeFreeEventButton = button;
        resetFreeEventRsvpForm();
        freeEventRsvpForm.dataset.eventKey = button.dataset.freeEventRsvp || '';
        freeEventRsvpForm.dataset.eventName = button.dataset.freeEventName || elementAnalyticsLabel(button);

        if (freeEventRsvpTitle) {
            freeEventRsvpTitle.textContent = button.dataset.freeEventName || 'Get Tickets';
        }

        if (freeEventRsvpEventName) {
            freeEventRsvpEventName.textContent = button.dataset.freeEventName || '';
        }

        openStoreLayer('freeEventRsvpLayer');

        return true;
    };

    const freeEventRsvpPayload = () => {
        const name = freeEventRsvpName?.value?.trim() || '';
        const email = freeEventRsvpEmail?.value?.trim() || '';
        const country = freeEventRsvpCountry?.value?.trim() || '';

        if (!name) {
            markFieldValidity(freeEventRsvpName, false);
            throw rsvpError('Agrega tu nombre.', 'missing_name');
        }

        if (!isValidEmail(email)) {
            markFieldValidity(freeEventRsvpEmail, false);
            throw rsvpError('Agrega un correo válido.', 'invalid_email');
        }

        if (!country) {
            markFieldValidity(freeEventRsvpCountry, false);
            throw rsvpError('Selecciona tu país.', 'missing_country');
        }

        return {
            event_key: freeEventRsvpForm?.dataset.eventKey || '',
            event_name: freeEventRsvpForm?.dataset.eventName || '',
            name,
            email,
            country,
        };
    };

    freeEventRsvpButtons.forEach((button) => {
        button.addEventListener('click', () => {
            trackElementEvent(button, 'free_event_rsvp_started', {
                item_type: 'event',
                item_id: button.dataset.freeEventRsvp,
                result: 'started',
            });

            openFreeEventRsvpModal(button);
        });
    });

    freeEventRsvpForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!activeFreeEventButton) {
            return;
        }

        const endpoint = activeFreeEventButton.dataset.freeEventRsvpEndpoint
            || freeEventRsvpForm.dataset.freeEventRsvpEndpoint;
        const originalLabel = freeEventRsvpSubmit?.textContent || 'Registrarme';

        try {
            const payload = freeEventRsvpPayload();
            [freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => markFieldValidity(field, true));

            if (freeEventRsvpSubmit) {
                freeEventRsvpSubmit.disabled = true;
                freeEventRsvpSubmit.textContent = 'Guardando...';
            }

            const response = await postFreeEventRsvpJson(endpoint, payload);
            const message = response.message || 'Te has registrado con éxito! Te esperamos.';

            activeFreeEventButton.textContent = response.status === 'already_registered' ? 'Ya registrado' : 'Registrado';
            setFreeEventRsvpStatus(message);
            showStoreToast(message);
            trackElementEvent(activeFreeEventButton, 'free_event_rsvp_succeeded', {
                item_type: 'event',
                item_id: activeFreeEventButton.dataset.freeEventRsvp,
                rsvp_status: response.status,
                result: 'succeeded',
            });

            window.setTimeout(() => closeStoreLayer('freeEventRsvpLayer'), 900);
        } catch (error) {
            console.error(error);
            const message = error.userMessage || 'Registration could not be saved. Try again.';

            setFreeEventRsvpStatus(message, true);
            showStoreToast(message);
            trackElementEvent(activeFreeEventButton, 'free_event_rsvp_failed', {
                item_type: 'event',
                item_id: activeFreeEventButton.dataset.freeEventRsvp,
                reason: error.reason || error.message || 'free_event_rsvp_failed',
                result: 'failed',
            });
        } finally {
            if (freeEventRsvpSubmit) {
                freeEventRsvpSubmit.disabled = false;
                freeEventRsvpSubmit.textContent = originalLabel;
            }
        }
    });

    rsvpButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const originalLabel = button.textContent;

            trackElementEvent(button, 'store_rsvp_started', {
                item_type: 'event',
                item_id: button.dataset.rsvp,
                result: 'started',
            });

            button.disabled = true;
            button.textContent = 'Saving RSVP...';

            try {
                const payload = await postRsvpJson(button.dataset.rsvpEndpoint, {
                    event_key: button.dataset.rsvp,
                    event_name: button.dataset.rsvpName || elementAnalyticsLabel(button),
                });

                renderRsvpSuccess(button, payload);
                showStoreToast(payload.message || 'RSVP confirmed.');
                trackElementEvent(button, 'store_rsvp_succeeded', {
                    item_type: 'event',
                    item_id: button.dataset.rsvp,
                    ticket_status: payload.ticket?.status,
                    rsvp_status: payload.ticket?.rsvp_status,
                    result: 'succeeded',
                });
            } catch (error) {
                console.error(error);
                const message = error.userMessage || 'RSVP could not be saved. Try again.';

                setRsvpStatus(button, message, { tone: 'error' });
                showStoreToast(message);
                trackElementEvent(button, 'store_rsvp_failed', {
                    item_type: 'event',
                    item_id: button.dataset.rsvp,
                    reason: error.reason || error.message || 'rsvp_failed',
                    result: 'failed',
                });

                if (button.dataset.rsvpConfirmed !== 'true') {
                    button.textContent = originalLabel;
                }
            } finally {
                button.disabled = false;
            }
        });
    });

    document.getElementById('detailBuy')?.addEventListener('click', () => {
        if (!activeProduct) {
            return;
        }

        closeStoreLayer('detailLayer');
        openCheckoutModal(activeProduct, {
            source: 'detail_modal',
            itemType: products[activeProduct]?.type || 'product',
        });
    });

    document.getElementById('openBag')?.addEventListener('click', () => {
        renderBag();
        openStoreLayer('bagLayer');
        if (bag.length) {
            void initializeVisiblePayPalCheckout();
        }
        trackEvent('store_checkout_started', {
            item_type: 'checkout',
            item_count: bag.length,
            result: bag.length ? 'opened' : 'empty',
        });
    });

    paymentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectPaymentMethod(button.dataset.paymentMethod || 'paypal');
        });
    });

    const openRequestedCheckout = () => {
        const autoOpenButton = document.querySelector('[data-auto-open-checkout="true"][data-buy]');

        if (autoOpenButton && products[autoOpenButton.dataset.buy]) {
            openCheckoutModal(autoOpenButton.dataset.buy, { source: 'dedicated_checkout_url' });
            return;
        }

        const requestedProduct = new URLSearchParams(window.location.search).get('buy');

        if (!requestedProduct || !products[requestedProduct]) {
            return;
        }

        openCheckoutModal(requestedProduct, { source: 'query_buy', itemType: 'checkout' });
    };

    const openAutoCheckout = () => {
        const button = document.querySelector('[data-auto-open-checkout][data-buy]');

        if (!button || bag.length > 0) {
            return;
        }

        startCheckoutFromBuyButton(button, { source: 'shareable_checkout' });
    };

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeStoreLayer(button.dataset.close));
    });

    window.renyStoreKeydownAbort?.abort();
    window.renyStoreKeydownAbort = new AbortController();
    document.addEventListener('keydown', (event) => {
        const openLayerId = ['bagLayer', 'detailLayer', 'freeEventRsvpLayer', 'purchaseConfirmationLayer'].find((id) => {
            const layer = document.getElementById(id);
            return layer && !layer.hidden;
        });

        if (!openLayerId) {
            return;
        }

        const layer = document.getElementById(openLayerId);

        if (event.key === 'Tab') {
            trapStoreFocus(layer, event);
        } else if (event.key === 'Escape') {
            closeStoreLayer(openLayerId);
        }
    }, { signal: window.renyStoreKeydownAbort.signal });

    selectPaymentMethod('paypal', { track: false });
    refreshRoyalPassOptions();
    updateStorePrices();
    renderBag();
    [nameField, emailField, phoneField, countryField, freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => {
        field?.addEventListener('input', () => {
            markFieldValidity(field, true);
            refreshCheckoutControls();
            if ([freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].includes(field)) {
                setFreeEventRsvpStatus('');
            }
        });
        field?.addEventListener('change', () => {
            markFieldValidity(field, true);
            refreshCheckoutControls();
            if ([freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].includes(field)) {
                setFreeEventRsvpStatus('');
            }
        });
    });
    openRequestedCheckout();
    openAutoCheckout();
};

export { initializeStoreInteractions };
