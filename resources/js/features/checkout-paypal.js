const stageFallbacks = {
    create: 'We could not start PayPal checkout. No charge was made. Try again.',
    approval: 'PayPal approval did not finish. No charge was confirmed. Try again.',
    capture: 'PayPal approved the payment, but we could not confirm it here. Do not retry; contact support with the time and amount.',
};

const genericServerMessages = new Set([
    'Server Error',
    'Checkout failed.',
]);

const firstValidationMessage = (payload) => Object.values(payload?.errors || {})[0]?.[0];

export const annotatePayPalError = (error, stage) => {
    const fallback = stageFallbacks[stage] || stageFallbacks.approval;
    const annotated = error instanceof Error ? error : new Error(String(error || fallback));

    annotated.paypalStage = annotated.paypalStage || stage;
    annotated.userMessage = annotated.userMessage || fallback;
    annotated.checkoutState = annotated.checkoutState || 'payment_failed';
    annotated.reason = annotated.reason || `paypal_${stage}_failed`;

    if (stage === 'capture') {
        annotated.paymentMayBeCaptured = true;
    }

    return annotated;
};

export const checkoutRequestError = ({ payload = {}, stage, status }) => {
    const validationMessage = firstValidationMessage(payload);
    const sessionMessage = status === 419
        ? stage === 'capture'
            ? stageFallbacks.capture
            : 'Your checkout session expired. Refresh the page and try again. No charge was made.'
        : null;
    const responseMessage = typeof payload.message === 'string' && !genericServerMessages.has(payload.message)
        ? payload.message
        : null;
    const message = validationMessage || sessionMessage || responseMessage || stageFallbacks[stage] || 'Checkout failed.';
    const error = new Error(message);

    return Object.assign(error, {
        checkoutState: status === 422 && stage === 'create' ? 'validation_failed' : 'payment_failed',
        httpStatus: status,
        paypalStage: stage,
        paymentMayBeCaptured: stage === 'capture',
        reason: `paypal_${stage}_http_${status}`,
        userMessage: message,
    });
};

export const resolvePayPalCallbackError = ({ callbackError, lastError, stage }) => {
    if (lastError?.userMessage) {
        return annotatePayPalError(lastError, lastError.paypalStage || stage);
    }

    return annotatePayPalError(callbackError, stage || 'approval');
};

export const shouldCancelPendingPayPalOrder = ({ activeOrderId, stage }) => Boolean(activeOrderId)
    && !['capture', 'completed'].includes(stage);
