import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.window = { renyAnalytics: {} };
globalThis.document = { addEventListener: () => {} };

const { persistenceSafePayload } = await import('../../resources/js/features/analytics.js');

test('analytics persistence removes payment references and referrers while normalizing failure reasons', () => {
    const payload = persistenceSafePayload({
        screen: 'store_checkout',
        path: '/store/checkout/album',
        item_id: 'album',
        result: 'failed',
        reason: 'Provider error: card declined!',
        referrer: 'https://example.test/private?email=fan@example.test',
        paypal_order_id: 'PAYMENT-TOKEN',
    });

    assert.deepEqual(payload, {
        screen: 'store_checkout',
        path: '/store/checkout/album',
        item_id: 'album',
        result: 'failed',
        reason: 'provider_error_card_declined',
    });
});

test('analytics persistence enforces the backend string limits before sending', () => {
    const payload = persistenceSafePayload({
        screen: 's'.repeat(100),
        item_id: 'i'.repeat(200),
        currency: 'usd-extra',
    });

    assert.equal(payload.screen.length, 80);
    assert.equal(payload.item_id.length, 120);
    assert.equal(payload.currency, 'usd');
});
