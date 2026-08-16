import assert from 'node:assert/strict';
import test from 'node:test';

import {
    annotatePayPalError,
    checkoutRequestError,
    resolvePayPalCallbackError,
    shouldCancelPendingPayPalOrder,
} from '../../resources/js/features/checkout-paypal.js';

test('create 422 preserves the actionable account login message', () => {
    const error = checkoutRequestError({
        payload: {
            errors: {
                identifier: ['Log in to checkout with this email or phone.'],
            },
        },
        stage: 'create',
        status: 422,
    });

    assert.equal(error.userMessage, 'Log in to checkout with this email or phone.');
    assert.equal(error.checkoutState, 'validation_failed');
    assert.equal(error.reason, 'paypal_create_http_422');
});

test('create 500 explains that no charge was made', () => {
    const error = checkoutRequestError({
        payload: { message: 'Server Error' },
        stage: 'create',
        status: 500,
    });

    assert.match(error.userMessage, /No charge was made/);
    assert.equal(error.paymentMayBeCaptured, false);
});

test('expired create session asks for a refresh without exposing CSRF internals', () => {
    const error = checkoutRequestError({
        payload: { message: 'CSRF token mismatch.' },
        stage: 'create',
        status: 419,
    });

    assert.match(error.userMessage, /Refresh the page/);
    assert.doesNotMatch(error.userMessage, /CSRF/);
});

for (const status of [422, 500]) {
    test(`capture ${status} warns against a duplicate retry when the response has no safe detail`, () => {
        const error = checkoutRequestError({
            payload: status === 422 ? {} : { message: 'Server Error' },
            stage: 'capture',
            status,
        });

        assert.match(error.userMessage, /Do not retry/);
        assert.equal(error.paymentMayBeCaptured, true);
    });
}

test('PayPal callback keeps the original create error after the SDK wraps it', () => {
    const original = annotatePayPalError(new Error('Log in before checkout.'), 'create');
    original.userMessage = 'Log in before checkout.';
    const resolved = resolvePayPalCallbackError({
        callbackError: new Error('SDK generic error'),
        lastError: original,
        stage: 'create',
    });

    assert.equal(resolved.userMessage, 'Log in before checkout.');
    assert.equal(resolved.paypalStage, 'create');
});

test('an SDK error outside a callback still gets safe approval copy', () => {
    const resolved = resolvePayPalCallbackError({
        callbackError: new Error('SDK generic error'),
        lastError: null,
        stage: 'idle',
    });

    assert.match(resolved.userMessage, /No charge was confirmed/);
});

test('pending orders are never canceled after approval enters capture', () => {
    assert.equal(shouldCancelPendingPayPalOrder({ activeOrderId: 'ORDER-1', stage: 'approval' }), true);
    assert.equal(shouldCancelPendingPayPalOrder({ activeOrderId: 'ORDER-1', stage: 'capture' }), false);
    assert.equal(shouldCancelPendingPayPalOrder({ activeOrderId: 'ORDER-1', stage: 'completed' }), false);
    assert.equal(shouldCancelPendingPayPalOrder({ activeOrderId: null, stage: 'approval' }), false);
});
