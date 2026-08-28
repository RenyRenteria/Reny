import { trackAccessGateViews } from './access-gate-analytics.js';
import { createAnalyticsIdentity } from './analytics-identity.js';

const normalizeAnalyticsKey = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .replace(/&[a-z0-9#]+;/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'unknown';

const compactAnalyticsPayload = (payload) => Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''),
);

const currentAnalyticsScreen = () => document.body?.dataset.analyticsScreen
    || document.querySelector('main[id]')?.id
    || normalizeAnalyticsKey(window.location.pathname || 'home');

const analyticsDebugEnabled = () => {
    try {
        const params = new URLSearchParams(window.location.search);
        const requested = params.get('analytics_debug');

        if (['1', 'true', 'yes', 'on'].includes(String(requested).toLowerCase())) {
            window.localStorage?.setItem('reny_analytics_debug', '1');
        } else if (['0', 'false', 'no', 'off'].includes(String(requested).toLowerCase())) {
            window.localStorage?.removeItem('reny_analytics_debug');
        }

        return window.renyAnalyticsDebug === true
            || window.localStorage?.getItem('reny_analytics_debug') === '1';
    } catch {
        return window.renyAnalyticsDebug === true;
    }
};

const analyticsRandomId = () => {
    if (typeof window.crypto?.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
};

const createAnalyticsId = analyticsRandomId;

const analyticsStorage = (() => {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
})();

const analyticsIdentity = createAnalyticsIdentity({
    storage: analyticsStorage,
    createId: analyticsRandomId,
    href: window.location.href,
    referrer: document.referrer,
    siteHostname: window.location.hostname,
});

const analyticsSessionId = () => analyticsIdentity.sessionId();

const dispatchAnalyticsEvent = (event) => {
    if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({ event: event.name, ...event.payload });
    }

    if (typeof window.gtag === 'function') {
        window.gtag('event', event.name, event.payload);
    }

    if (typeof window.plausible === 'function') {
        window.plausible(event.name, { props: event.payload });
    }

    if (typeof window.posthog?.capture === 'function') {
        window.posthog.capture(event.name, event.payload);
    }

    if (typeof window.mixpanel?.track === 'function') {
        window.mixpanel.track(event.name, event.payload);
    }
};

const persistedAnalyticsEvents = new Set([
    'page_view',
    'permission_denied',
    'paywall_triggered_from_photo',
    'store_product_opened',
    'store_checkout_started',
    'store_checkout_validation_failed',
    'store_payment_started',
    'store_payment_succeeded',
    'store_payment_failed',
    'store_payment_canceled',
    'store_payment_unavailable',
    'music_play_started',
    'video_play_started',
    'photo_opened',
    'community_note_opened',
    'free_event_rsvp_succeeded',
    'store_rsvp_succeeded',
    'rsvp_confirmed',
    'ticket_purchased',
    'ticket_checked_in',
]);

const analyticsEndpoint = () => document.querySelector('meta[name="reny-analytics-endpoint"]')?.content
    || '/analytics/events';

const persistAnalyticsEvent = (event) => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!persistedAnalyticsEvents.has(event.name) || typeof fetch !== 'function' || !csrfToken) {
        return;
    }

    const payload = { ...event.payload };

    delete payload.referrer;

    if (payload.reason) {
        payload.reason = normalizeAnalyticsKey(payload.reason).slice(0, 80);
    }

    fetch(analyticsEndpoint(), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ ...event, payload }),
        keepalive: true,
    }).then((response) => {
        if (!response.ok) {
            console.warn(`[analytics] persistence failed (${response.status})`, event.name);
        }
    }).catch(() => {});
};

const analyticsApi = window.renyAnalytics || {};
analyticsApi.events = Array.isArray(analyticsApi.events) ? analyticsApi.events : [];
analyticsApi.sessionId = analyticsSessionId;
analyticsApi.visitorId = analyticsIdentity.visitorId;

const trackEvent = (name, payload = {}, { eventId = null } = {}) => {
    const identity = analyticsIdentity.sessionContext();
    const event = {
        name,
        schema_version: 2,
        visitor_id: identity.visitor_id,
        session_id: identity.session_id,
        event_id: eventId || createAnalyticsId(),
        traffic_source: identity.traffic_source,
        traffic_medium: identity.traffic_medium,
        traffic_campaign: identity.traffic_campaign,
        payload: compactAnalyticsPayload({
            screen: currentAnalyticsScreen(),
            path: window.location.pathname,
            result: 'clicked',
            ...payload,
        }),
        timestamp: new Date().toISOString(),
    };

    analyticsApi.events.push(event);
    dispatchAnalyticsEvent(event);
    persistAnalyticsEvent(event);

    if (analyticsDebugEnabled()) {
        console.info('[analytics]', event.name, event.payload);
    }

    return event;
};

analyticsApi.track = trackEvent;
window.renyAnalytics = analyticsApi;

const analyticsText = (node) => String(node?.textContent || '').replace(/\s+/g, ' ').trim();

const elementAnalyticsLabel = (element) => element.dataset.analyticsLabel
    || element.dataset.youtubeTitle
    || element.dataset.photoTitle
    || element.dataset.freeEventName
    || element.dataset.buyName
    || element.dataset.rsvpName
    || element.dataset.name
    || element.dataset.rsvp
    || element.getAttribute('aria-label')
    || element.closest('[data-title]')?.dataset.title
    || analyticsText(element.closest('article')?.querySelector('h4, h3, h2, strong'))
    || analyticsText(element);

const elementAnalyticsPayload = (element, overrides = {}) => {
    const label = elementAnalyticsLabel(element);

    return compactAnalyticsPayload({
        item_id: element.dataset.analyticsId
            || element.dataset.youtubeId
            || element.dataset.detail
            || element.dataset.freeEventRsvp
            || element.dataset.buy
            || element.dataset.rsvp
            || normalizeAnalyticsKey(label),
        item_type: element.dataset.analyticsType
            || element.dataset.buyType
            || element.dataset.photoType
            || overrides.item_type,
        item_label: label,
        ...overrides,
    });
};

const trackElementEvent = (element, name, payload = {}) => trackEvent(
    name,
    elementAnalyticsPayload(element, payload),
);

const sectionAnalyticsKey = (element) => normalizeAnalyticsKey(
    analyticsText(element.closest('.content-section, section')?.querySelector('h1, h2, h3'))
    || elementAnalyticsLabel(element),
);

const boundInteractions = new WeakMap();

const bindOnce = (element, key, type, handler, options = undefined) => {
    if (!element) {
        return;
    }

    const boundKeys = boundInteractions.get(element) || new Set();
    const interactionKey = `${key}:${type}`;

    if (boundKeys.has(interactionKey)) {
        return;
    }

    element.addEventListener(type, handler, options);
    boundKeys.add(interactionKey);
    boundInteractions.set(element, boundKeys);
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.body?.classList.contains('admin-cms-body')) {
        return;
    }

    trackEvent('page_view', {
        title: document.title,
        referrer: document.referrer || null,
        result: 'viewed',
    });

    if (currentAnalyticsScreen().startsWith('account')) {
        trackEvent('account_viewed', {
            access_state: document.body?.dataset.accessState,
            item_type: 'account',
            item_id: currentAnalyticsScreen(),
            result: 'viewed',
        });
    }

    trackAccessGateViews(document, trackEvent, currentAnalyticsScreen());
}, { once: true });

export {
    analyticsText,
    bindOnce,
    compactAnalyticsPayload,
    createAnalyticsId,
    currentAnalyticsScreen,
    elementAnalyticsLabel,
    elementAnalyticsPayload,
    normalizeAnalyticsKey,
    sectionAnalyticsKey,
    trackElementEvent,
    trackEvent,
};
