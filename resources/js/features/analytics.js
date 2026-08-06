const normalizeAnalyticsKey = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .replace(/&[a-z0-9#]+;/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'unknown';

const compactAnalyticsPayload = (payload) => Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined && value !== null && value !== ''),
);

const createAnalyticsId = () => {
    if (typeof window.crypto?.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : ((random & 0x3) | 0x8);

        return value.toString(16);
    });
};

let volatileAnalyticsSessionId = null;

const analyticsSessionId = () => {
    try {
        const key = 'reny_analytics_session_id';
        const stored = window.sessionStorage?.getItem(key);

        if (stored) {
            return stored;
        }

        const created = createAnalyticsId();
        window.sessionStorage?.setItem(key, created);

        return created;
    } catch {
        volatileAnalyticsSessionId ||= createAnalyticsId();

        return volatileAnalyticsSessionId;
    }
};

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
    'store_payment_succeeded',
    'store_payment_failed',
    'music_play_started',
    'video_play_started',
    'free_event_rsvp_succeeded',
    'store_rsvp_succeeded',
]);

const persistedPayloadKeys = new Set([
    'screen',
    'path',
    'result',
    'title',
    'section',
    'item_type',
    'item_id',
    'item_label',
    'photo_id',
    'album_id',
    'source',
    'method',
    'checkout_state',
    'reason',
    'currency',
    'item_count',
    'rsvp_status',
    'ticket_status',
]);

const persistedPayloadStringLimits = {
    screen: 80,
    path: 200,
    result: 40,
    title: 180,
    section: 80,
    item_type: 80,
    item_id: 120,
    item_label: 180,
    photo_id: 120,
    album_id: 120,
    source: 80,
    method: 20,
    checkout_state: 40,
    reason: 120,
    currency: 3,
    rsvp_status: 40,
    ticket_status: 40,
};

const persistenceSafePayload = (payload) => Object.fromEntries(
    Object.entries(payload)
        .filter(([key]) => persistedPayloadKeys.has(key))
        .map(([key, value]) => {
            if (key === 'reason') {
                return [key, normalizeAnalyticsKey(value).slice(0, 120)];
            }

            if (typeof value === 'string') {
                return [key, value.slice(0, persistedPayloadStringLimits[key] || 180)];
            }

            return [key, value];
        }),
);

const analyticsEndpoint = () => document.querySelector('meta[name="reny-analytics-endpoint"]')?.content
    || '/analytics/events';

const persistAnalyticsEvent = (event) => {
    if (!persistedAnalyticsEvents.has(event.name) || typeof fetch !== 'function') {
        return;
    }

    fetch(analyticsEndpoint(), {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...event,
            payload: persistenceSafePayload(event.payload),
        }),
        keepalive: true,
    }).catch(() => {});
};

const analyticsApi = window.renyAnalytics || {};
analyticsApi.events = Array.isArray(analyticsApi.events) ? analyticsApi.events : [];

const trackEvent = (name, payload = {}) => {
    const event = {
        name,
        schema_version: 1,
        event_id: createAnalyticsId(),
        session_id: analyticsSessionId(),
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

    document.querySelectorAll('.access-gate').forEach((gate) => {
        trackEvent('permission_denied', {
            section: gate.dataset.section || currentAnalyticsScreen(),
            item_type: 'access_gate',
            item_id: gate.dataset.section || currentAnalyticsScreen(),
            result: 'blocked',
        });
    });
}, { once: true });

export {
    analyticsText,
    bindOnce,
    compactAnalyticsPayload,
    currentAnalyticsScreen,
    elementAnalyticsLabel,
    elementAnalyticsPayload,
    normalizeAnalyticsKey,
    persistenceSafePayload,
    sectionAnalyticsKey,
    trackElementEvent,
    trackEvent,
};
