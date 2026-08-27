import assert from 'node:assert/strict';
import test from 'node:test';
import { trackAccessGateViews } from '../../resources/js/features/access-gate-analytics.js';

const gate = (section) => ({
    dataset: { section },
    matches: () => false,
    querySelectorAll: () => [],
});

test('tracks every access gate in a newly rendered SPA page', () => {
    const gates = [gate('music-premium'), gate('music-downloads'), gate('community')];
    const root = {
        matches: () => false,
        querySelectorAll: (selector) => selector === '.access-gate' ? gates : [],
    };
    const events = [];

    const count = trackAccessGateViews(root, (name, payload) => events.push({ name, payload }));

    assert.equal(count, 3);
    assert.deepEqual(events.map((event) => event.name), [
        'permission_denied',
        'permission_denied',
        'permission_denied',
    ]);
    assert.deepEqual(events.map((event) => event.payload.section), [
        'music-premium',
        'music-downloads',
        'community',
    ]);
});

test('tracks a root that is itself an access gate', () => {
    const root = {
        dataset: { section: 'royals' },
        matches: (selector) => selector === '.access-gate',
        querySelectorAll: () => [],
    };
    const events = [];

    assert.equal(trackAccessGateViews(root, (name, payload) => events.push({ name, payload })), 1);
    assert.equal(events[0].payload.item_id, 'royals');
});
