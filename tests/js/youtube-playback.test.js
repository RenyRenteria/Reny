import assert from 'node:assert/strict';
import test from 'node:test';

import { createYouTubePlaybackObserver } from '../../resources/js/features/youtube-playback.js';

test('video playback is counted only after YouTube confirms PLAYING and only once', () => {
    let plays = 0;
    const observe = createYouTubePlaybackObserver(() => {
        plays += 1;
    });

    assert.equal(observe(-1), false);
    assert.equal(observe(5), false);
    assert.equal(observe(3), false);
    assert.equal(plays, 0);
    assert.equal(observe(1), true);
    assert.equal(observe(1), false);
    assert.equal(plays, 1);
});
