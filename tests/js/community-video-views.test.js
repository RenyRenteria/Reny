import assert from 'node:assert/strict';
import test from 'node:test';

import { createPlaybackThresholdTracker } from '../../resources/js/features/community-video-views.js';

const controlledTracker = (onQualified) => {
    let currentTime = 0;
    let scheduled = null;

    return {
        advance(milliseconds) {
            currentTime += milliseconds;
        },
        fireTimer() {
            const callback = scheduled;
            scheduled = null;
            callback?.();
        },
        tracker: createPlaybackThresholdTracker({
            now: () => currentTime,
            schedule: (callback) => {
                scheduled = callback;

                return callback;
            },
            cancel: (timer) => {
                if (scheduled === timer) {
                    scheduled = null;
                }
            },
            onQualified,
        }),
    };
};

test('qualifies one view after three seconds of continuous playback', () => {
    let qualified = 0;
    const playback = controlledTracker(() => qualified += 1);

    playback.tracker.start();
    playback.advance(2999);
    playback.fireTimer();

    assert.equal(qualified, 0);

    playback.advance(1);
    playback.fireTimer();

    assert.equal(qualified, 1);
    assert.equal(playback.tracker.snapshot().qualified, true);
});

test('accumulates actual playback time across pauses and qualifies only once', () => {
    let qualified = 0;
    const playback = controlledTracker(() => qualified += 1);

    playback.tracker.start();
    playback.advance(1800);
    playback.tracker.stop();
    playback.advance(5000);
    playback.tracker.start();
    playback.advance(1200);
    playback.fireTimer();
    playback.tracker.stop();
    playback.tracker.start();

    assert.equal(qualified, 1);
    assert.equal(playback.tracker.snapshot().qualified, true);
});

test('qualifies again after the completed playback is reset', () => {
    let qualified = 0;
    const playback = controlledTracker(() => qualified += 1);

    playback.tracker.start();
    playback.advance(3000);
    playback.fireTimer();
    playback.tracker.stop();
    playback.tracker.reset();

    playback.tracker.start();
    playback.advance(3000);
    playback.fireTimer();

    assert.equal(qualified, 2);
    assert.equal(playback.tracker.snapshot().qualified, true);
});
