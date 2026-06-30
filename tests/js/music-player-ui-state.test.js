import assert from 'node:assert/strict';
import test from 'node:test';

import { createMusicPlayerUiState } from '../../resources/js/features/music-player-ui-state.js';

test('music player queue stays closed on mount and playback or shuffle toggles', () => {
    const state = createMusicPlayerUiState();

    assert.equal(state.snapshot().isQueueVisible, false);

    state.handlePlaybackToggle();
    assert.equal(state.snapshot().isQueueVisible, false);

    state.handleShuffleToggle();
    assert.equal(state.snapshot().isQueueVisible, false);

    state.handlePlaybackToggle();
    assert.equal(state.snapshot().isQueueVisible, false);
});

test('music player queue opens only through explicit queue action', () => {
    const state = createMusicPlayerUiState();

    state.setPlayerBarVisible(true);
    assert.equal(state.snapshot().isQueueVisible, false);

    state.toggleQueue();
    assert.equal(state.snapshot().isQueueVisible, true);

    state.toggleQueue();
    assert.equal(state.snapshot().isQueueVisible, false);
});
