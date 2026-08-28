import assert from 'node:assert/strict';
import test from 'node:test';

import {
    ANALYTICS_SESSION_TTL_MS,
    createAnalyticsIdentity,
    trafficAcquisition,
} from '../../resources/js/features/analytics-identity.js';

const memoryStorage = () => {
    const values = new Map();

    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
};

test('keeps an anonymous visitor and renews a session after 30 minutes of inactivity', () => {
    const storage = memoryStorage();
    const ids = ['visitor-1', 'session-1', 'session-2'];
    let timestamp = 1_000;
    const identity = createAnalyticsIdentity({
        storage,
        createId: () => ids.shift(),
        href: 'https://renyrenteria.com/music',
        referrer: '',
        siteHostname: 'renyrenteria.com',
        now: () => timestamp,
    });

    const first = identity.sessionContext();
    assert.equal(first.visitor_id, 'visitor-1');
    assert.equal(first.session_id, 'session-1');
    assert.equal(first.traffic_source, 'direct');
    assert.equal(first.traffic_medium, 'none');

    timestamp += ANALYTICS_SESSION_TTL_MS - 1;
    assert.equal(identity.sessionId(), 'session-1');

    timestamp += ANALYTICS_SESSION_TTL_MS + 1;
    const renewed = identity.sessionContext();
    assert.equal(renewed.visitor_id, 'visitor-1');
    assert.equal(renewed.session_id, 'session-2');
});

test('classifies campaigns, organic search, social and referrals without full referrer URLs', () => {
    assert.deepEqual(trafficAcquisition({
        href: 'https://renyrenteria.com/?utm_source=Instagram+Ads&utm_medium=Paid+Social&utm_campaign=Royal+Launch',
        referrer: '',
        siteHostname: 'renyrenteria.com',
    }), {
        source: 'instagram_ads',
        medium: 'paid_social',
        campaign: 'royal_launch',
    });

    assert.deepEqual(trafficAcquisition({
        href: 'https://renyrenteria.com/music',
        referrer: 'https://www.google.com/search?q=reny',
        siteHostname: 'renyrenteria.com',
    }), {
        source: 'google',
        medium: 'organic',
        campaign: null,
    });

    assert.deepEqual(trafficAcquisition({
        href: 'https://renyrenteria.com/music',
        referrer: 'https://revista.example/articulo?email=private@example.com',
        siteHostname: 'renyrenteria.com',
    }), {
        source: 'revista.example',
        medium: 'referral',
        campaign: null,
    });
});

test('starts a new session when a campaign arrives during an active direct session', () => {
    const storage = memoryStorage();
    const ids = ['visitor-1', 'direct-session', 'campaign-session'];
    const common = {
        storage,
        createId: () => ids.shift(),
        referrer: '',
        siteHostname: 'renyrenteria.com',
        now: () => 1_000,
    };
    const direct = createAnalyticsIdentity({
        ...common,
        href: 'https://renyrenteria.com/music',
    }).sessionContext();
    const campaign = createAnalyticsIdentity({
        ...common,
        href: 'https://renyrenteria.com/music?utm_source=newsletter&utm_medium=email&utm_campaign=royal',
    }).sessionContext();

    assert.equal(direct.session_id, 'direct-session');
    assert.equal(campaign.visitor_id, 'visitor-1');
    assert.equal(campaign.session_id, 'campaign-session');
    assert.equal(campaign.traffic_source, 'newsletter');
    assert.equal(campaign.traffic_medium, 'email');
});
