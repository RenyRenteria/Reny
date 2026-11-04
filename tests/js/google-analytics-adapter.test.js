import assert from 'node:assert/strict';
import test from 'node:test';

test('GA4 owns page views while the adapter keeps internal views and forwards interactions', async () => {
    const googleEvents = [];
    const plausibleEvents = [];
    const persistedEvents = [];
    const originalWindow = globalThis.window;
    const originalDocument = globalThis.document;
    const originalFetch = globalThis.fetch;

    globalThis.window = {
        location: new URL('https://renyrenteria.com/'),
        dataLayer: [],
        gtag: (...args) => googleEvents.push(args),
        plausible: (...args) => plausibleEvents.push(args),
    };
    globalThis.document = {
        referrer: '',
        body: { dataset: { analyticsScreen: 'home' } },
        querySelector: () => ({ content: 'test-token' }),
        addEventListener: () => {},
    };
    globalThis.fetch = async (url, options) => {
        persistedEvents.push(JSON.parse(options.body));
        return { ok: true };
    };

    try {
        const { trackEvent } = await import('../../resources/js/features/analytics.js');

        trackEvent('page_view', { title: 'Home', result: 'viewed' });
        window.location = new URL('https://renyrenteria.com/music');
        trackEvent('page_view', { title: 'Music', result: 'viewed' });
        trackEvent('music_play_clicked', { item_id: 'test-song' });

        assert.deepEqual(googleEvents.map((event) => event.slice(0, 2)), [
            ['event', 'music_play_clicked'],
        ]);
        assert.equal(googleEvents[0][2].item_id, 'test-song');
        assert.deepEqual(persistedEvents.map((event) => event.payload.path), ['/', '/music']);
        assert.deepEqual(window.dataLayer.map((event) => event.event), [
            'page_view', 'page_view', 'music_play_clicked',
        ]);
        assert.deepEqual(plausibleEvents.map((event) => event[0]), [
            'page_view', 'page_view', 'music_play_clicked',
        ]);
    } finally {
        globalThis.window = originalWindow;
        globalThis.document = originalDocument;
        globalThis.fetch = originalFetch;
    }
});
