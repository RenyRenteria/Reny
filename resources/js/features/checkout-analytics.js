const PAYMENT_ANALYTICS_EVENTS = Object.freeze({
    payment_started: 'store_payment_started',
    payment_success: 'store_payment_succeeded',
    payment_failed: 'store_payment_failed',
    canceled: 'store_payment_canceled',
    unavailable: 'store_payment_unavailable',
    validation_failed: 'store_checkout_validation_failed',
});

const paymentAnalyticsEventName = (checkoutState) => PAYMENT_ANALYTICS_EVENTS[checkoutState]
    || 'store_payment_state_changed';

const createPaymentAnalyticsTracker = ({ track, createId }) => {
    let logicalEventIds = new Map();

    return {
        beginCheckout() {
            logicalEventIds = new Map();
        },

        track(method, checkoutState, details = {}) {
            const logicalKey = `${method}:${checkoutState}`;
            const eventId = logicalEventIds.get(logicalKey) || createId();
            logicalEventIds.set(logicalKey, eventId);

            return track(paymentAnalyticsEventName(checkoutState), {
                item_type: checkoutState === 'payment_started' ? 'payment_attempt' : 'payment_method',
                item_id: method,
                method,
                checkout_state: checkoutState,
                result: checkoutState,
                ...details,
            }, { eventId });
        },
    };
};

export {
    createPaymentAnalyticsTracker,
    paymentAnalyticsEventName,
};
