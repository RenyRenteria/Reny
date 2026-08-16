import { createHash, createHmac } from 'node:crypto';
import { mkdir, writeFile } from 'node:fs/promises';

import { expect, test } from '@playwright/test';

const requiredEnvironment = [
    'PAYPAL_E2E_BASE_URL',
    'PAYPAL_E2E_EXPECTED_HOST',
    'PAYPAL_E2E_CONTROL_TOKEN',
    'PAYPAL_E2E_REFERENCE_KEY',
    'PAYPAL_SANDBOX_BUSINESS_EMAIL',
    'PAYPAL_SANDBOX_BUYER_EMAIL',
    'PAYPAL_SANDBOX_BUYER_PASSWORD',
    'PAYPAL_CLIENT_ID',
    'PAYPAL_CLIENT_SECRET',
    'PAYPAL_WEBHOOK_ID',
    'GITHUB_SHA',
];
const paypalApiBaseUrl = process.env.PAYPAL_API_BASE_URL || 'https://api-m.sandbox.paypal.com';
const evidence = [];
let paypalAccessToken;
let currentCustomerEmail;
let currentRunReference;
let verifiedRevision;

const digest = (value) => createHash('sha256').update(value).digest('hex');
const reference = (value) => createHmac('sha256', process.env.PAYPAL_E2E_REFERENCE_KEY).update(value).digest('hex').slice(0, 16);
const fixtureEmail = (runReference) => `qa+paypal-${digest(runReference).slice(0, 20)}@renyrenteria.test`;
const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const requireEnvironment = () => {
    const missing = requiredEnvironment.filter((name) => !process.env[name]);

    if (missing.length > 0) {
        throw new Error(`Missing ${missing.length} PayPal sandbox E2E environment values.`);
    }
};

const appRequest = async (path, body = {}) => {
    const response = await fetch(new URL(path, process.env.PAYPAL_E2E_BASE_URL), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${process.env.PAYPAL_E2E_CONTROL_TOKEN}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Sandbox control ${path} returned HTTP ${response.status}.`);
    }

    return response.json();
};

const paypalRequest = async (path, options = {}) => {
    if (!paypalAccessToken) {
        const credentials = Buffer.from(`${process.env.PAYPAL_CLIENT_ID}:${process.env.PAYPAL_CLIENT_SECRET}`).toString('base64');
        const tokenResponse = await fetch(`${paypalApiBaseUrl}/v1/oauth2/token`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                Authorization: `Basic ${credentials}`,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'grant_type=client_credentials',
        });

        if (!tokenResponse.ok) {
            throw new Error(`PayPal sandbox authentication returned HTTP ${tokenResponse.status}.`);
        }

        paypalAccessToken = (await tokenResponse.json()).access_token;
    }

    const response = await fetch(`${paypalApiBaseUrl}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${paypalAccessToken}`,
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        },
    });

    if (!response.ok) {
        throw new Error(`PayPal sandbox ${options.method || 'GET'} request returned HTTP ${response.status}.`);
    }

    return response.status === 204 ? {} : response.json();
};

const preflight = async () => {
    requireEnvironment();

    const baseUrl = new URL(process.env.PAYPAL_E2E_BASE_URL);
    const expectedHost = process.env.PAYPAL_E2E_EXPECTED_HOST.toLowerCase();

    expect(baseUrl.protocol, 'Sandbox app must use HTTPS.').toBe('https:');
    expect(baseUrl.hostname.toLowerCase(), 'Sandbox host must match the protected environment value.').toBe(expectedHost);
    expect(baseUrl.hostname.toLowerCase()).not.toBe('renyrenteria.com');
    expect(paypalApiBaseUrl).toBe('https://api-m.sandbox.paypal.com');
    expect(process.env.PAYPAL_SANDBOX_BUYER_EMAIL.toLowerCase()).not.toBe(process.env.PAYPAL_SANDBOX_BUSINESS_EMAIL.toLowerCase());
    const preflightRunReference = `preflight-${process.env.GITHUB_RUN_ID}-${process.env.GITHUB_RUN_ATTEMPT || '1'}`;
    const appConfiguration = await appRequest('/qa/paypal-e2e/prepare', { run_reference: preflightRunReference });
    expect(appConfiguration).toMatchObject({
        status: 'ready',
        paypal_api_environment: 'sandbox',
        paypal_client_reference: reference(process.env.PAYPAL_CLIENT_ID),
        paypal_webhook_reference: reference(process.env.PAYPAL_WEBHOOK_ID),
        deployed_revision: process.env.GITHUB_SHA,
    });
    verifiedRevision = appConfiguration.deployed_revision;

    const webhook = await paypalRequest(`/v1/notifications/webhooks/${encodeURIComponent(process.env.PAYPAL_WEBHOOK_ID)}`);
    const expectedWebhookUrl = new URL('/paypal/webhook', baseUrl).toString();
    const subscribedEvents = (webhook.event_types || []).map((event) => event.name);

    expect(new URL(webhook.url).toString()).toBe(expectedWebhookUrl);
    expect(subscribedEvents).toContain('PAYMENT.CAPTURE.COMPLETED');
    expect(subscribedEvents).toContain('PAYMENT.CAPTURE.REFUNDED');
};

const fillCheckout = async (page) => {
    await page.goto('/store/checkout/listening');
    await page.locator('#nameField').fill('PayPal Sandbox QA');
    await page.locator('#emailField').fill(currentCustomerEmail);
    await page.locator('#phoneField').fill('+50760009998');
    await page.locator('#countryField').selectOption({ label: 'Panama' });
    await expect(page.locator('#paymentStatus')).toContainText(/Use the PayPal button|approve with PayPal/i);
};

const firstVisible = async (locators, timeout = 20_000) => {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        for (const locator of locators) {
            if (await locator.isVisible().catch(() => false)) {
                return locator;
            }
        }

        await sleep(250);
    }

    throw new Error('Expected PayPal control did not become visible.');
};

const openPayPal = async (page) => {
    const frame = page.frameLocator('#paypalButtons iframe').first();
    const button = await firstVisible([
        frame.getByRole('link', { name: /PayPal/i }).first(),
        frame.getByRole('button', { name: /PayPal/i }).first(),
        frame.locator('[role="link"], button').first(),
    ]);
    const popupPromise = page.waitForEvent('popup');
    const createdResponsePromise = page.waitForResponse((response) => response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/checkout/paypal/orders');

    await button.click();

    const [popup, createdResponse] = await Promise.all([popupPromise, createdResponsePromise]);
    const created = await createdResponse.json();

    expect(createdResponse.ok()).toBeTruthy();
    expect(typeof created.paypal_order_id).toBe('string');
    await popup.waitForLoadState('domcontentloaded');

    return { orderId: created.paypal_order_id, popup };
};

const signInToPayPal = async (popup) => {
    const email = popup.locator('#email, input[name="login_email"]').first();
    const initialPassword = popup.locator('#password, input[name="login_password"], input[type="password"]').first();

    await firstVisible([email, initialPassword]);

    if (await email.isVisible().catch(() => false)) {
        await email.fill(process.env.PAYPAL_SANDBOX_BUYER_EMAIL);
        const next = popup.locator('#btnNext, button:has-text("Next")').first();

        if (await next.isVisible().catch(() => false)) {
            await next.click();
        }
    }

    const password = await firstVisible([
        popup.locator('#password').first(),
        popup.locator('input[name="login_password"]').first(),
        popup.locator('input[type="password"]').first(),
    ]);
    await password.fill(process.env.PAYPAL_SANDBOX_BUYER_PASSWORD);
    await (await firstVisible([
        popup.locator('#btnLogin').first(),
        popup.getByRole('button', { name: /Log In|Sign In/i }).first(),
    ])).click();
};

const approvePayPal = async (popup) => {
    await (await firstVisible([
        popup.locator('#payment-submit-btn').first(),
        popup.getByRole('button', { name: /Complete Purchase|Pay Now|Agree & Pay|Continue/i }).first(),
    ], 30_000)).click();
};

const cancelPayPal = async (popup) => {
    const cancel = await firstVisible([
        popup.locator('#cancelLink').first(),
        popup.getByRole('link', { name: /Cancel and return|Cancel/i }).first(),
        popup.getByRole('button', { name: /Cancel/i }).first(),
    ], 10_000).catch(() => null);

    if (cancel) {
        await cancel.click();
    } else {
        await popup.close();
    }
};

const orderCaptures = (order) => (order.purchase_units || [])
    .flatMap((unit) => unit.payments?.captures || [])
    .filter((capture) => capture.status === 'COMPLETED');

const stateFor = (orderId) => appRequest('/qa/paypal-e2e/state', { paypal_order_id: orderId });

const waitForState = async (orderId, predicate, timeout = 90_000) => {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        const state = await stateFor(orderId);

        if (predicate(state)) {
            return state;
        }

        await sleep(1_000);
    }

    throw new Error(`Sandbox checkout ${reference(orderId)} did not reach the expected state.`);
};

const findWebhookEvent = async (eventType, startedAt, matches) => {
    const deadline = Date.now() + 90_000;

    while (Date.now() < deadline) {
        const query = new URLSearchParams({
            start_time: new Date(startedAt - 300_000).toISOString(),
            end_time: new Date().toISOString(),
            event_type: eventType,
            page_size: '100',
        });
        const payload = await paypalRequest(`/v1/notifications/webhooks-events?${query}`);
        const event = (payload.events || []).find(matches);

        if (event?.id) {
            return event.id;
        }

        await sleep(2_000);
    }

    throw new Error(`PayPal ${eventType} event was not available for resend.`);
};

const findCaptureEvent = (orderId, startedAt) => findWebhookEvent(
    'PAYMENT.CAPTURE.COMPLETED',
    startedAt,
    (candidate) => candidate.resource?.supplementary_data?.related_ids?.order_id === orderId,
);

const resendEvent = (eventId) => paypalRequest(`/v1/notifications/webhooks-events/${encodeURIComponent(eventId)}/resend`, {
    method: 'POST',
    body: JSON.stringify({ webhook_ids: [process.env.PAYPAL_WEBHOOK_ID] }),
});

const refundAndVerifyReplay = async (orderId, captureId) => {
    const startedAt = Date.now();
    const refund = await paypalRequest(`/v2/payments/captures/${encodeURIComponent(captureId)}/refund`, {
        method: 'POST',
        headers: {
            'PayPal-Request-Id': `reny-e2e-refund-${reference(captureId)}`,
        },
        body: '{}',
    });
    const refundedState = await waitForState(orderId, (candidate) => candidate.refund_count === 1);
    const eventId = await findWebhookEvent(
        'PAYMENT.CAPTURE.REFUNDED',
        startedAt,
        (candidate) => candidate.resource?.supplementary_data?.related_ids?.capture_id === captureId,
    );

    await resendEvent(eventId);
    await sleep(3_000);
    const replayState = await stateFor(orderId);

    expect(replayState.refund_count).toBe(1);
    expect(replayState.membership_expired_event_count).toBe(refundedState.membership_expired_event_count);

    return {
        provider_refund_status: refund.status,
        refund_reference: reference(refund.id),
        refund_event_reference: reference(eventId),
        local_refund_count: replayState.refund_count,
        membership_expired_event_count: replayState.membership_expired_event_count,
    };
};

test.describe.configure({ mode: 'serial' });

test.describe('reusable PayPal sandbox checkout gate', () => {
    test.beforeAll(async () => {
        await preflight();
    });

    test.beforeEach(async ({}, testInfo) => {
        const titleReference = digest(testInfo.titlePath.join(':')).slice(0, 12);
        currentRunReference = `${process.env.GITHUB_RUN_ID}-${process.env.GITHUB_RUN_ATTEMPT || '1'}-${titleReference}`;
        currentCustomerEmail = fixtureEmail(currentRunReference);
        await appRequest('/qa/paypal-e2e/prepare', { run_reference: currentRunReference });
    });

    test.afterAll(async () => {
        const outputDirectory = 'test-results/paypal-sandbox';
        const payload = {
            schema_version: 1,
            commit: verifiedRevision || null,
            environment_host: process.env.PAYPAL_E2E_EXPECTED_HOST || null,
            generated_at: new Date().toISOString(),
            scenarios: evidence,
        };

        await mkdir(outputDirectory, { recursive: true });
        await writeFile(`${outputDirectory}/evidence.json`, `${JSON.stringify(payload, null, 2)}\n`);
    });

    test('existing logged-out customer completes once and remains logged out', async ({ page }) => {
        await fillCheckout(page);
        const { orderId, popup } = await openPayPal(page);
        await signInToPayPal(popup);
        const captureResponsePromise = page.waitForResponse((response) => response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/checkout/paypal');
        await approvePayPal(popup);
        const captureResponse = await captureResponsePromise;

        expect(captureResponse.ok()).toBeTruthy();
        await expect(page.locator('#purchaseConfirmationPanel')).toBeVisible();
        await expect(page.locator('#purchaseConfirmationMessage')).toContainText('existing account');
        await expect(page.locator('#purchaseConfirmationAccount')).toHaveText('Sign in to view account');
        await expect(page.locator('#purchaseConfirmationAccount')).toHaveAttribute('href', /\/login$/);

        const providerOrder = await paypalRequest(`/v2/checkout/orders/${encodeURIComponent(orderId)}`);
        const captures = orderCaptures(providerOrder);
        const state = await waitForState(orderId, (candidate) => candidate.statuses?.completed === 1);

        expect(providerOrder.status).toBe('COMPLETED');
        expect(captures).toHaveLength(1);
        expect(captures[0].amount).toMatchObject({ currency_code: 'USD', value: '15.00' });
        expect(state).toMatchObject({
            order_count: 1,
            statuses: { completed: 1 },
            royal_status: 'royal_active',
            billing_profile_count: 1,
            purchase_event_count: 1,
            membership_event_count: 1,
        });
        const refundEvidence = await refundAndVerifyReplay(orderId, captures[0].id);

        evidence.push({
            scenario: 'existing_logged_out_success',
            order_reference: reference(orderId),
            capture_reference: reference(captures[0].id),
            provider_status: providerOrder.status,
            local_statuses: state.statuses,
            side_effect_counts: {
                billing_profiles: state.billing_profile_count,
                purchases: state.purchase_event_count,
                memberships: state.membership_event_count,
            },
            cleanup: refundEvidence,
        });
    });

    test('explicit PayPal cancellation records no capture or activation', async ({ page }) => {
        await fillCheckout(page);
        const { orderId, popup } = await openPayPal(page);
        await cancelPayPal(popup);
        await expect(page.locator('#paymentStatus')).toContainText(/canceled/i);

        const state = await waitForState(orderId, (candidate) => candidate.statuses?.cancelled === 1);
        const providerOrder = await paypalRequest(`/v2/checkout/orders/${encodeURIComponent(orderId)}`);

        expect(orderCaptures(providerOrder)).toHaveLength(0);
        expect(state).toMatchObject({
            statuses: { cancelled: 1 },
            billing_profile_count: 0,
            purchase_event_count: 0,
            membership_event_count: 0,
        });

        evidence.push({
            scenario: 'explicit_cancel',
            order_reference: reference(orderId),
            provider_status: providerOrder.status,
            local_statuses: state.statuses,
            capture_count: 0,
        });
    });

    test('post-capture failure, retry, webhook, and replay remain exactly once', async ({ page }) => {
        const startedAt = Date.now();
        await appRequest('/qa/paypal-e2e/arm', { run_reference: currentRunReference });
        await fillCheckout(page);
        const { orderId, popup } = await openPayPal(page);
        await signInToPayPal(popup);
        const captureResponsePromise = page.waitForResponse((response) => response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/checkout/paypal');
        await approvePayPal(popup);
        const captureResponse = await captureResponsePromise;

        expect(captureResponse.status()).toBeGreaterThanOrEqual(500);
        await expect(page.locator('#paymentStatus')).toContainText(/Do not retry/i);

        const reviewState = await waitForState(orderId, (candidate) => candidate.statuses?.payment_review === 1);
        expect(reviewState).toMatchObject({
            statuses: { payment_review: 1 },
            royal_status: 'open',
            billing_profile_count: 0,
            purchase_event_count: 0,
            membership_event_count: 0,
        });

        const retryStatus = await page.evaluate(async ({ orderId: rawOrderId, identifier }) => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch('/checkout/paypal', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    identifier,
                    product_keys: ['listening'],
                    currency: 'USD',
                    paypal_order_id: rawOrderId,
                }),
            });

            return response.status;
        }, {
            identifier: currentCustomerEmail,
            orderId,
        });
        expect(retryStatus).toBe(422);

        const providerOrderBeforeWebhook = await paypalRequest(`/v2/checkout/orders/${encodeURIComponent(orderId)}`);
        const capturesBeforeWebhook = orderCaptures(providerOrderBeforeWebhook);
        expect(capturesBeforeWebhook).toHaveLength(1);

        const eventId = await findCaptureEvent(orderId, startedAt);
        await appRequest('/qa/paypal-e2e/release', { paypal_order_id: orderId });
        await resendEvent(eventId);
        const completedState = await waitForState(orderId, (candidate) => candidate.statuses?.completed === 1);
        await resendEvent(eventId);
        await sleep(3_000);
        const replayState = await stateFor(orderId);
        const providerOrderAfterReplay = await paypalRequest(`/v2/checkout/orders/${encodeURIComponent(orderId)}`);

        expect(orderCaptures(providerOrderAfterReplay)).toHaveLength(1);
        expect(completedState).toMatchObject({
            statuses: { completed: 1 },
            royal_status: 'royal_active',
            billing_profile_count: 1,
            purchase_event_count: 1,
            membership_event_count: 1,
        });
        expect(replayState).toMatchObject(completedState);
        const refundEvidence = await refundAndVerifyReplay(orderId, capturesBeforeWebhook[0].id);

        evidence.push({
            scenario: 'payment_review_retry_webhook_replay',
            order_reference: reference(orderId),
            capture_reference: reference(capturesBeforeWebhook[0].id),
            event_reference: reference(eventId),
            retry_http_status: retryStatus,
            before_webhook_statuses: reviewState.statuses,
            after_replay_statuses: replayState.statuses,
            provider_capture_count: 1,
            side_effect_counts: {
                billing_profiles: replayState.billing_profile_count,
                purchases: replayState.purchase_event_count,
                memberships: replayState.membership_event_count,
            },
            cleanup: refundEvidence,
        });
    });
});
