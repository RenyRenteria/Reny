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

const persistedAnalyticsEvents = new Set(['page_view', 'permission_denied', 'paywall_triggered_from_photo']);

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
        body: JSON.stringify(event),
        keepalive: true,
    }).catch(() => {});
};

const analyticsApi = window.renyAnalytics || {};
analyticsApi.events = Array.isArray(analyticsApi.events) ? analyticsApi.events : [];

const trackEvent = (name, payload = {}) => {
    const event = {
        name,
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
    sectionAnalyticsKey,
    trackElementEvent,
    trackEvent,
};
