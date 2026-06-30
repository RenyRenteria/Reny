import assert from 'node:assert/strict';
import test from 'node:test';

import {
    cloneMusicPlaybackPayload,
    isCacheableMusicPlaybackPayload,
} from '../../resources/js/features/music-playback-cache.js';

test('music playback cache stores only ready successful payloads', () => {
    assert.equal(
        isCacheableMusicPlaybackPayload({ ok: true }, { state: 'ready', audio_url: 'https://audio.test/open.mp3' }),
        true,
    );

    assert.equal(
        isCacheableMusicPlaybackPayload({ ok: false, status: 401 }, { state: 'login_required' }),
        false,
    );

    assert.equal(
        isCacheableMusicPlaybackPayload({ ok: false, status: 403 }, { state: 'royal_required' }),
        false,
    );

    assert.equal(
        isCacheableMusicPlaybackPayload({ ok: false, status: 422 }, { state: 'playback_error' }),
        false,
    );

    assert.equal(
        isCacheableMusicPlaybackPayload({ ok: true }, { state: 'content_locked' }),
        false,
    );
});

test('music playback cache clones queue entries before storing', () => {
    const payload = {
        state: 'ready',
        queue: [{ id: '1', title: 'First track' }],
        tracks: ['1'],
    };

    const cloned = cloneMusicPlaybackPayload(payload);

    payload.queue[0].title = 'Changed';
    payload.tracks.push('2');

    assert.equal(cloned.queue[0].title, 'First track');
    assert.deepEqual(cloned.tracks, ['1']);
});
