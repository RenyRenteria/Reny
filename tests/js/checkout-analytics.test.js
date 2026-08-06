import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createPaymentAnalyticsTracker,
    paymentAnalyticsEventName,
} from '../../resources/js/features/checkout-analytics.js';

test('payment analytics keeps cancellations and unavailable methods out of failed payments', () => {
    assert.equal(paymentAnalyticsEventName('payment_started'), 'store_payment_started');
    assert.equal(paymentAnalyticsEventName('payment_failed'), 'store_payment_failed');
    assert.equal(paymentAnalyticsEventName('canceled'), 'store_payment_canceled');
    assert.equal(paymentAnalyticsEventName('unavailable'), 'store_payment_unavailable');
    assert.equal(paymentAnalyticsEventName('validation_failed'), 'store_checkout_validation_failed');
});

test('payment analytics reuses logical event ids for retries within one checkout', () => {
    const calls = [];
    let sequence = 0;
    const tracker = createPaymentAnalyticsTracker({
        createId: () => `event-${++sequence}`,
        track: (...args) => calls.push(args),
    });

    tracker.beginCheckout();
    tracker.track('paypal', 'payment_started');
    tracker.track('paypal', 'payment_started');
    tracker.track('paypal', 'payment_failed', { reason: 'provider_error' });
    tracker.track('paypal', 'payment_failed', { reason: 'provider_error' });

    assert.equal(calls[0][2].eventId, calls[1][2].eventId);
    assert.equal(calls[2][2].eventId, calls[3][2].eventId);
    assert.notEqual(calls[0][2].eventId, calls[2][2].eventId);

    tracker.beginCheckout();
    tracker.track('paypal', 'payment_started');
    assert.notEqual(calls[0][2].eventId, calls[4][2].eventId);
});
