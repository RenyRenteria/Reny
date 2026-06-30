import assert from 'node:assert/strict';
import test from 'node:test';

import { smartShuffle, trackIdentity } from '../../resources/js/features/smart-shuffle.js';

const tracks = [
    { id: 'one', title: 'One', album: 'A' },
    { id: 'two', title: 'Two', album: 'A' },
    { id: 'three', title: 'Three', album: 'B' },
    { id: 'four', title: 'Four', album: 'B' },
    { id: 'five', title: 'Five', album: 'C' },
];

const seededRandom = (seed) => {
    let state = seed;

    return () => {
        state = (state * 1664525 + 1013904223) % 4294967296;

        return state / 4294967296;
    };
};

test('smartShuffle does not repeat a track inside the same bag', () => {
    const recentlyPlayed = [];
    const played = new Set();

    for (let index = 0; index < tracks.length; index += 1) {
        const [nextTrack] = smartShuffle(tracks, recentlyPlayed, {
            lookbackSize: 2,
            rng: seededRandom(index + 1),
        });
        const identity = trackIdentity(nextTrack);

        assert.ok(identity);
        assert.equal(played.has(identity), false);

        played.add(identity);
        recentlyPlayed.push(identity);
    }

    assert.equal(played.size, tracks.length);
});

test('smartShuffle avoids the current track when alternatives exist', () => {
    const shuffled = smartShuffle(tracks, [], {
        currentTrack: tracks[0],
        rng: seededRandom(42),
    });

    assert.notEqual(trackIdentity(shuffled[0]), 'one');
});

test('smartShuffle keeps first pick reasonably distributed', () => {
    const counts = new Map(tracks.map((track) => [track.id, 0]));

    for (let index = 0; index < 1000; index += 1) {
        const [nextTrack] = smartShuffle(tracks, [], {
            rng: seededRandom(index + 1),
        });
        const identity = trackIdentity(nextTrack);

        counts.set(identity, counts.get(identity) + 1);
    }

    for (const count of counts.values()) {
        assert.ok(count >= 90 && count <= 310, `expected count ${count} to stay within a broad uniformity band`);
    }
});
