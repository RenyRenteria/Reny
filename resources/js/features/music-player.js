import {
    analyticsText,
    elementAnalyticsLabel,
    trackElementEvent,
} from './analytics.js';

const musicPlayerLayer = document.getElementById('musicPlayerLayer');
const musicPlayerAudio = document.getElementById('musicPlayerAudio');
const musicPlayerArtwork = document.getElementById('musicPlayerArtwork');
const musicPlayerTitle = document.getElementById('musicPlayerTitle');
const musicPlayerState = document.getElementById('musicPlayerState');
const musicPlayerMessage = document.getElementById('musicPlayerMessage');
const musicPlayerLoading = document.getElementById('musicPlayerLoading');
const musicPlayerTracks = document.getElementById('musicPlayerTracks');
const musicPlayerCta = document.getElementById('musicPlayerCta');
const musicPlayerToggle = document.getElementById('musicPlayerToggle');
const musicPlayerToggleIcon = document.getElementById('musicPlayerToggleIcon');
const musicPlayerPrevious = document.getElementById('musicPlayerPrevious');
const musicPlayerNext = document.getElementById('musicPlayerNext');
const musicPlayerShuffle = document.getElementById('musicPlayerShuffle');
const musicPlayerRepeat = document.getElementById('musicPlayerRepeat');
const musicPlayerProgress = document.getElementById('musicPlayerProgress');
const musicPlayerCurrentTime = document.getElementById('musicPlayerCurrentTime');
const musicPlayerDuration = document.getElementById('musicPlayerDuration');
let activeMusicButton = null;
let activeMusicTrack = null;
let isSeekingMusic = false;
let musicQueue = [];
let musicQueueIndex = -1;
let isShuffleActive = false;
let repeatMode = 'off';
let musicPlaybackRequestId = 0;
let handledMusicFailureRequestId = 0;
let musicAutoAdvanceAttempts = 0;
let previousMusicClickTimer = null;
let isMusicPlayerLoading = false;

const previousMusicClickWindowMs = 350;

const trapMusicFocus = () => {};

const nextMusicRequestId = () => {
    musicPlaybackRequestId += 1;
    handledMusicFailureRequestId = 0;

    return musicPlaybackRequestId;
};

const isCurrentMusicRequest = (requestId) => requestId === musicPlaybackRequestId;

const formatMediaTime = (seconds) => {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const minutes = Math.floor(seconds / 60);
    const remaining = Math.floor(seconds % 60).toString().padStart(2, '0');

    return `${minutes}:${remaining}`;
};

const musicPlayableTitleFromButton = (button) => {
    const title = button.dataset.analyticsLabel
        || button.dataset.name
        || button.closest('[data-title]')?.dataset.title
        || analyticsText(button.closest('article')?.querySelector('h4, h3, h2, strong'));

    if (title) {
        return String(title).trim() || 'Music item';
    }

    const fallback = elementAnalyticsLabel(button);

    return String(fallback || 'Music item').replace(/^play\s+/i, '').trim() || 'Music item';
};

const isPlayableMusicTrack = (track) => Boolean(track?.audio_url || track?.play_url);

const musicTrackFromButton = (button) => ({
    button,
    key: button.dataset.playUrl || button.dataset.analyticsId || musicPlayableTitleFromButton(button),
    id: button.dataset.analyticsId || '',
    title: musicPlayableTitleFromButton(button),
    play_url: button.dataset.playUrl || '',
    detail_url: button.dataset.detailUrl || window.location.href,
    image_url: button.dataset.imageUrl || '',
    state: button.dataset.accessState || 'playback_error',
    access_label: button.dataset.accessLabel || '',
    message: button.dataset.accessMessage || '',
    cta_label: button.dataset.ctaLabel || '',
    cta_url: button.dataset.ctaUrl || '',
    item_type: button.dataset.analyticsType || (button.classList.contains('mini-play') ? 'single' : 'music'),
});

const normalizeMusicTrack = (track = {}, index = 0) => {
    const title = String(track.title || track.name || 'Music item').trim() || 'Music item';
    const playUrl = String(track.play_url || track.playUrl || '').trim();
    const audioUrl = String(track.audio_url || track.audioUrl || '').trim();
    const detailUrl = String(track.detail_url || track.detailUrl || '').trim();
    const imageUrl = String(track.image_url || track.imageUrl || '').trim();
    const id = String(track.id || '').trim();
    const key = String(track.key || id || audioUrl || playUrl || `${title}-${index}`).trim();

    return {
        key,
        id,
        title,
        play_url: playUrl,
        audio_url: audioUrl,
        detail_url: detailUrl,
        image_url: imageUrl,
        state: track.state || track.access_state || 'ready',
        access_label: track.access_label || track.accessLabel || '',
        message: track.message || track.access_message || track.accessMessage || '',
        cta_label: track.cta_label || track.ctaLabel || '',
        cta_url: track.cta_url || track.ctaUrl || '',
        item_type: track.item_type || track.itemType || track.kind || 'track',
        button: track.button || null,
    };
};

const musicTrackSourceElement = (track) => {
    if (track?.button) {
        return track.button;
    }

    const source = document.createElement('button');
    source.type = 'button';
    source.textContent = track?.title || 'Music item';
    source.setAttribute('aria-label', `Play ${track?.title || 'music item'}`);
    source.dataset.analyticsLabel = track?.title || 'Music item';
    source.dataset.analyticsId = track?.id || track?.key || '';
    source.dataset.analyticsType = track?.item_type || 'track';
    source.dataset.playUrl = track?.play_url || '';
    source.dataset.detailUrl = track?.detail_url || '';
    source.dataset.imageUrl = track?.image_url || '';
    source.dataset.accessState = track?.state || 'ready';
    source.dataset.accessLabel = track?.access_label || 'Ready';
    source.dataset.accessMessage = track?.message || '';
    source.dataset.ctaLabel = track?.cta_label || '';
    source.dataset.ctaUrl = track?.cta_url || '';

    return source;
};

const sameMusicTrack = (left, right) => {
    if (!left || !right) {
        return false;
    }

    return Boolean(
        (left.key && left.key === right.key)
        || (left.audio_url && left.audio_url === right.audio_url)
        || (left.play_url && left.play_url === right.play_url)
    );
};

const setMusicQueue = (tracks = [], currentTrack = null) => {
    const seen = new Set();
    musicQueue = tracks
        .map((track, index) => normalizeMusicTrack(track, index))
        .filter((track) => isPlayableMusicTrack(track))
        .filter((track) => {
            if (seen.has(track.key)) {
                return false;
            }

            seen.add(track.key);
            return true;
        });

    const normalizedCurrent = currentTrack ? normalizeMusicTrack(currentTrack) : null;
    musicQueueIndex = normalizedCurrent
        ? musicQueue.findIndex((track) => sameMusicTrack(track, normalizedCurrent))
        : -1;

    if (musicQueueIndex === -1 && musicQueue.length) {
        musicQueueIndex = 0;
    }
};

const buildMusicQueueFromPage = (currentButton = null) => {
    const buttons = Array.from(document.querySelectorAll('[data-music-play]'))
        .filter((button) => button.dataset.playUrl);

    if (!buttons.length) {
        return;
    }

    setMusicQueue(
        buttons.map((button) => musicTrackFromButton(button)),
        currentButton ? musicTrackFromButton(currentButton) : activeMusicTrack,
    );
};

const syncMusicQueueIndex = (track) => {
    const normalizedTrack = normalizeMusicTrack(track);
    const index = musicQueue.findIndex((queuedTrack) => sameMusicTrack(queuedTrack, normalizedTrack));

    if (index !== -1) {
        musicQueueIndex = index;
    }
};

const canNavigateMusicQueue = () => musicQueue.length > 0;

const updateMusicQueueControls = (playbackEnabled = Boolean(musicPlayerAudio?.src)) => {
    if (musicPlayerPrevious) {
        musicPlayerPrevious.disabled = !playbackEnabled || !canNavigateMusicQueue();
    }

    if (musicPlayerNext) {
        musicPlayerNext.disabled = !playbackEnabled || !canNavigateMusicQueue();
    }

    if (musicPlayerShuffle) {
        musicPlayerShuffle.disabled = !playbackEnabled || musicQueue.length < 2;
        musicPlayerShuffle.classList.toggle('is-active', isShuffleActive);
        musicPlayerShuffle.setAttribute('aria-pressed', String(isShuffleActive));
        musicPlayerShuffle.setAttribute('aria-label', isShuffleActive ? 'Turn shuffle off' : 'Turn shuffle on');
    }

    if (musicPlayerRepeat) {
        musicPlayerRepeat.disabled = !playbackEnabled;
        musicPlayerRepeat.classList.toggle('is-active', repeatMode !== 'off');
        musicPlayerRepeat.dataset.repeatMode = repeatMode;
        musicPlayerRepeat.setAttribute('aria-pressed', String(repeatMode !== 'off'));
        musicPlayerRepeat.setAttribute('aria-label', {
            off: 'Turn repeat on',
            all: 'Repeat current track',
            one: 'Turn repeat off',
        }[repeatMode]);
    }
};

const setMusicControlsEnabled = (enabled) => {
    if (musicPlayerToggle) {
        musicPlayerToggle.disabled = !enabled;
    }

    if (musicPlayerProgress) {
        musicPlayerProgress.disabled = !enabled;
    }

    updateMusicQueueControls(enabled || musicQueue.length > 0);
};

const updateMusicToggle = () => {
    const isPlaying = Boolean(musicPlayerAudio && !musicPlayerAudio.paused && !musicPlayerAudio.ended);

    musicPlayerLayer?.classList.toggle('is-playing', isPlaying);
    musicPlayerToggle?.setAttribute('aria-label', isPlaying ? 'Pause' : 'Play');
    musicPlayerToggleIcon?.classList.toggle('is-playing', isPlaying);
    updateMusicQueueControls(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
};

const updateMusicProgress = () => {
    if (!musicPlayerAudio || isSeekingMusic) {
        return;
    }

    const duration = musicPlayerAudio.duration;
    const current = musicPlayerAudio.currentTime;

    if (musicPlayerCurrentTime) {
        musicPlayerCurrentTime.textContent = formatMediaTime(current);
    }

    if (musicPlayerDuration) {
        musicPlayerDuration.textContent = formatMediaTime(duration);
    }

    if (musicPlayerProgress) {
        musicPlayerProgress.value = Number.isFinite(duration) && duration > 0
            ? String((current / duration) * 100)
            : '0';
    }
};

const setMusicArtwork = (url) => {
    if (!musicPlayerArtwork) {
        return;
    }

    if (!url) {
        musicPlayerArtwork.style.removeProperty('background-image');
        musicPlayerArtwork.classList.remove('has-artwork');
        return;
    }

    musicPlayerArtwork.style.backgroundImage = `url(${JSON.stringify(url)})`;
    musicPlayerArtwork.classList.add('has-artwork');
};

const openMusicPlayer = () => {
    if (!musicPlayerLayer) {
        return;
    }

    musicPlayerLayer.hidden = false;
    musicPlayerLayer.removeAttribute('inert');
    document.body.classList.add('has-music-player');
};

const closeMusicPlayer = () => {
    if (!musicPlayerLayer) {
        return;
    }

    musicPlayerAudio?.pause();
    musicPlayerLayer.hidden = true;
    musicPlayerLayer.setAttribute('inert', '');
    document.body.classList.remove('has-music-player');
    updateMusicToggle();
};

const setMusicLoadingState = (button, options = {}) => {
    const requestId = nextMusicRequestId();

    isMusicPlayerLoading = true;
    activeMusicButton = button;
    activeMusicTrack = normalizeMusicTrack(musicTrackFromButton(button));

    if (!options.preserveQueue) {
        buildMusicQueueFromPage(button);
    }

    musicPlayerTitle.textContent = musicPlayableTitleFromButton(button);
    musicPlayerState.hidden = false;
    musicPlayerState.textContent = 'Loading';
    musicPlayerMessage.hidden = false;
    musicPlayerMessage.textContent = 'Checking access and audio availability.';
    musicPlayerLoading.hidden = false;
    musicPlayerTracks.hidden = true;
    musicPlayerTracks.replaceChildren();
    musicPlayerCta.hidden = true;
    musicPlayerCta.removeAttribute('href');
    setMusicControlsEnabled(false);
    setMusicArtwork(button.dataset.imageUrl);
    musicPlayerAudio.pause();
    musicPlayerAudio.removeAttribute('src');
    musicPlayerAudio.load();
    updateMusicProgress();
    updateMusicToggle();
    openMusicPlayer();

    return requestId;
};

const renderMusicTracks = (tracks = []) => {
    musicPlayerTracks.replaceChildren();

    if (!tracks.length) {
        musicPlayerTracks.hidden = true;
        return;
    }

    const list = document.createElement('ol');
    tracks.slice(0, 12).forEach((track) => {
        const item = document.createElement('li');
        item.textContent = track;
        list.append(item);
    });

    musicPlayerTracks.append(list);
    musicPlayerTracks.hidden = false;
};

const musicPayloadQueue = (payload) => (Array.isArray(payload.queue) ? payload.queue : [])
    .map((track, index) => normalizeMusicTrack(track, index))
    .filter((track) => track.audio_url || track.play_url);

const resolveMusicPlaybackPayload = (payload, button, options = {}) => {
    const sourceTrack = normalizeMusicTrack({
        ...musicTrackFromButton(button),
        ...payload,
        button,
    });
    const payloadQueue = musicPayloadQueue(payload);

    if (!options.preserveQueue) {
        if (payloadQueue.length > 1 || musicQueue.length <= 1) {
            setMusicQueue(payloadQueue.length ? payloadQueue : [sourceTrack], sourceTrack);
        } else {
            syncMusicQueueIndex(sourceTrack);
        }
    } else {
        syncMusicQueueIndex(sourceTrack);
    }

    const queuedTrack = musicQueue[musicQueueIndex];

    if (queuedTrack && (options.preserveQueue || payloadQueue.length > 1)) {
        return {
            ...payload,
            ...queuedTrack,
            audio_url: queuedTrack.audio_url || payload.audio_url || '',
            image_url: queuedTrack.image_url || payload.image_url || '',
            detail_url: queuedTrack.detail_url || payload.detail_url || '',
            play_url: queuedTrack.play_url || payload.play_url || '',
            state: payload.state || queuedTrack.state || 'ready',
            access_label: payload.access_label || queuedTrack.access_label,
            message: payload.message || queuedTrack.message || queuedTrack.title || payload.title,
            cta_label: payload.cta_label || queuedTrack.cta_label,
            cta_url: payload.cta_url || queuedTrack.cta_url,
        };
    }

    return payload;
};

const renderMusicPlayerPayload = (payload, button, options = {}) => {
    const requestId = options.requestId || nextMusicRequestId();

    if (!isCurrentMusicRequest(requestId)) {
        return false;
    }

    const playbackPayload = resolveMusicPlaybackPayload(payload, button, options);
    const state = playbackPayload.state || button.dataset.accessState || 'playback_error';
    const playableState = state === 'ready' && !playbackPayload.audio_url ? 'playback_error' : state;
    const stateLabel = playbackPayload.access_label || button.dataset.accessLabel || state.replace(/_/g, ' ');
    const message = playbackPayload.message || playbackPayload.access_message || button.dataset.accessMessage || 'Playback is not available.';
    const trackList = playbackPayload.tracks || musicQueue.map((track) => track.title);

    activeMusicButton = button;
    activeMusicTrack = normalizeMusicTrack({
        ...musicTrackFromButton(button),
        ...playbackPayload,
        button,
    });

    isMusicPlayerLoading = false;
    musicPlayerLoading.hidden = true;
    musicPlayerTitle.textContent = playbackPayload.title || elementAnalyticsLabel(button);
    musicPlayerState.hidden = playableState === 'ready';
    musicPlayerState.textContent = playableState === 'ready' ? '' : stateLabel;
    musicPlayerMessage.hidden = playableState === 'ready';
    musicPlayerMessage.textContent = playableState === 'ready' ? '' : message;
    setMusicArtwork(playbackPayload.image_url || button.dataset.imageUrl);
    renderMusicTracks(trackList);

    if (playbackPayload.cta_url || button.dataset.ctaUrl) {
        musicPlayerCta.href = playbackPayload.cta_url || button.dataset.ctaUrl;
        musicPlayerCta.textContent = playbackPayload.cta_label || button.dataset.ctaLabel || 'Continue';
        musicPlayerCta.hidden = false;
    } else {
        musicPlayerCta.hidden = true;
        musicPlayerCta.removeAttribute('href');
    }

    if (playableState === 'ready' && playbackPayload.audio_url) {
        if (musicPlayerAudio.src !== playbackPayload.audio_url) {
            musicPlayerAudio.src = playbackPayload.audio_url;
            musicPlayerAudio.load();
        }

        setMusicControlsEnabled(true);
        musicPlayerAudio.play().catch(() => {
            handleMusicPlaybackFailure('play_rejected', requestId);
        }).finally(updateMusicToggle);
        trackElementEvent(button, 'music_play_ready', {
            item_type: button.dataset.analyticsType,
            result: 'ready',
        });
        return true;
    }

    if (options.autoAdvance) {
        handleMusicPlaybackFailure(playableState, requestId);
        return false;
    }

    musicPlayerAudio.pause();
    musicPlayerAudio.removeAttribute('src');
    musicPlayerAudio.load();
    setMusicControlsEnabled(false);
    updateMusicProgress();
    updateMusicToggle();

    trackElementEvent(button, playableState === 'playback_error' ? 'music_play_failed' : 'music_access_blocked', {
        item_type: button.dataset.analyticsType,
        reason: playableState,
        result: playableState === 'playback_error' ? 'failed' : 'blocked',
    });

    return false;
};

const nextMusicQueueIndex = (direction = 1) => {
    if (!musicQueue.length) {
        return -1;
    }

    if (musicQueue.length === 1) {
        return 0;
    }

    if (musicQueueIndex === -1) {
        return direction > 0 ? 0 : musicQueue.length - 1;
    }

    if (isShuffleActive) {
        const availableIndexes = musicQueue
            .map((_, index) => index)
            .filter((index) => index !== musicQueueIndex);

        return availableIndexes[Math.floor(Math.random() * availableIndexes.length)] ?? -1;
    }

    const candidate = musicQueueIndex + direction;

    return (candidate + musicQueue.length) % musicQueue.length;
};

const playMusicQueueIndex = async (index, options = {}) => {
    const track = musicQueue[index];

    if (!track || !musicPlayerLayer || !musicPlayerAudio) {
        return false;
    }

    musicQueueIndex = index;
    const source = musicTrackSourceElement(track);

    if (track.audio_url) {
        return renderMusicPlayerPayload({
            ...track,
            state: track.state || 'ready',
            access_label: track.access_label || '',
            message: track.message || track.title,
        }, source, { preserveQueue: true, autoAdvance: options.autoAdvance });
    }

    if (!track.play_url) {
        if (options.autoAdvance) {
            handleMusicPlaybackFailure('missing_play_url', musicPlaybackRequestId);
        } else {
            updateMusicQueueControls(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
        }
        return false;
    }

    const requestId = setMusicLoadingState(source, { preserveQueue: true });

    try {
        const response = await fetch(track.play_url, {
            headers: {
                'Accept': 'application/json',
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!isCurrentMusicRequest(requestId)) {
            return false;
        }

        return renderMusicPlayerPayload(payload, source, {
            preserveQueue: true,
            autoAdvance: options.autoAdvance,
            requestId,
        });
    } catch (error) {
        console.error(error);
        if (!isCurrentMusicRequest(requestId)) {
            return false;
        }

        return renderMusicPlayerPayload({
            state: 'playback_error',
            access_label: 'Playback error',
            message: 'Playback could not load. Try again in a moment.',
        }, source, { preserveQueue: true, autoAdvance: options.autoAdvance, requestId });
    }
};

const playAdjacentMusicTrack = (direction, options = {}) => {
    const index = nextMusicQueueIndex(direction);

    if (index === -1) {
        updateMusicQueueControls(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
        return;
    }

    playMusicQueueIndex(index, options);
};

const handleMusicPlaybackFailure = (reason = 'playback_error', requestId = musicPlaybackRequestId) => {
    if (!isCurrentMusicRequest(requestId) || handledMusicFailureRequestId === requestId) {
        return;
    }

    handledMusicFailureRequestId = requestId;
    trackElementEvent(activeMusicButton || musicTrackSourceElement(activeMusicTrack), 'music_play_failed', {
        item_type: activeMusicButton?.dataset.analyticsType || activeMusicTrack?.item_type || 'track',
        reason,
        result: 'failed',
    });

    if (musicQueue.length && musicAutoAdvanceAttempts < Math.max(musicQueue.length, 1)) {
        musicAutoAdvanceAttempts += 1;
        playAdjacentMusicTrack(1, { autoAdvance: true });
        return;
    }

    musicAutoAdvanceAttempts = 0;
    musicPlayerState.hidden = false;
    musicPlayerState.textContent = 'Playback error';
    musicPlayerMessage.hidden = false;
    musicPlayerMessage.textContent = 'Skipping was not able to find a playable track.';
    setMusicControlsEnabled(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
    updateMusicToggle();
};

const restartCurrentMusicTrack = () => {
    if (!musicPlayerAudio?.src) {
        return;
    }

    musicPlayerAudio.currentTime = 0;
    updateMusicProgress();

    if (musicPlayerAudio.paused || musicPlayerAudio.ended) {
        const requestId = musicPlaybackRequestId;
        musicPlayerAudio.play().catch(() => handleMusicPlaybackFailure('restart_rejected', requestId));
    }
};

const handlePreviousMusicClick = () => {
    if (previousMusicClickTimer) {
        window.clearTimeout(previousMusicClickTimer);
        previousMusicClickTimer = null;
        musicAutoAdvanceAttempts = 0;
        playAdjacentMusicTrack(-1, { autoAdvance: true });
        return;
    }

    previousMusicClickTimer = window.setTimeout(() => {
        previousMusicClickTimer = null;
        restartCurrentMusicTrack();
    }, previousMusicClickWindowMs);
};

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-music-play]');

    if (!button || !musicPlayerLayer || !musicPlayerAudio) {
        return;
    }

    event.preventDefault();

    trackElementEvent(button, 'music_play_clicked', {
        item_type: button.dataset.analyticsType || (button.classList.contains('mini-play') ? 'single' : 'album'),
        result: 'clicked',
    });

    musicAutoAdvanceAttempts = 0;
    const requestId = setMusicLoadingState(button);

    if (!button.dataset.playUrl) {
        renderMusicPlayerPayload({
            state: button.dataset.accessState || 'playback_error',
            access_label: button.dataset.accessLabel || 'Audio unavailable',
            message: button.dataset.accessMessage || 'This music item is not connected to playback yet.',
            cta_label: button.dataset.ctaLabel,
            cta_url: button.dataset.ctaUrl,
        }, button, { requestId });
        return;
    }

    try {
        const response = await fetch(button.dataset.playUrl, {
            headers: {
                'Accept': 'application/json',
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!isCurrentMusicRequest(requestId)) {
            return;
        }

        renderMusicPlayerPayload(payload, button, { requestId });
    } catch (error) {
        console.error(error);
        if (!isCurrentMusicRequest(requestId)) {
            return;
        }

        renderMusicPlayerPayload({
            state: 'playback_error',
            access_label: 'Playback error',
            message: 'Playback could not load. Try again in a moment.',
        }, button, { requestId });
    }
});

musicPlayerAudio?.addEventListener('play', () => {
    musicAutoAdvanceAttempts = 0;
    updateMusicToggle();

    if (!activeMusicButton) {
        return;
    }

    trackElementEvent(activeMusicButton, 'music_play_started', {
        item_type: activeMusicButton.dataset.analyticsType,
        result: 'started',
    });
}, { once: false });

musicPlayerAudio?.addEventListener('pause', updateMusicToggle);
musicPlayerAudio?.addEventListener('ended', () => {
    updateMusicToggle();

    if (repeatMode === 'one') {
        const requestId = musicPlaybackRequestId;
        musicPlayerAudio.currentTime = 0;
        musicPlayerAudio.play().catch(() => handleMusicPlaybackFailure('repeat_rejected', requestId));
        return;
    }

    playAdjacentMusicTrack(1, { autoAdvance: true });
});
musicPlayerAudio?.addEventListener('loadedmetadata', updateMusicProgress);
musicPlayerAudio?.addEventListener('timeupdate', updateMusicProgress);

musicPlayerAudio?.addEventListener('error', () => {
    if (!activeMusicButton || isMusicPlayerLoading || !musicPlayerAudio.src) {
        return;
    }

    handleMusicPlaybackFailure('audio_element_error', musicPlaybackRequestId);
});

musicPlayerToggle?.addEventListener('click', () => {
    if (!musicPlayerAudio?.src) {
        return;
    }

    if (musicPlayerAudio.paused || musicPlayerAudio.ended) {
        const requestId = musicPlaybackRequestId;
        musicPlayerAudio.play().catch(() => {
            handleMusicPlaybackFailure('play_rejected', requestId);
        });
    } else {
        musicPlayerAudio.pause();
    }
});

musicPlayerPrevious?.addEventListener('click', handlePreviousMusicClick);
musicPlayerNext?.addEventListener('click', () => {
    musicAutoAdvanceAttempts = 0;
    playAdjacentMusicTrack(1, { autoAdvance: true });
});

musicPlayerShuffle?.addEventListener('click', () => {
    isShuffleActive = !isShuffleActive;
    updateMusicQueueControls(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
});

musicPlayerRepeat?.addEventListener('click', () => {
    repeatMode = {
        off: 'all',
        all: 'one',
        one: 'off',
    }[repeatMode] || 'off';
    updateMusicQueueControls(Boolean(musicPlayerAudio?.src) || musicQueue.length > 0);
});

musicPlayerProgress?.addEventListener('input', () => {
    isSeekingMusic = true;
});

musicPlayerProgress?.addEventListener('change', () => {
    if (!musicPlayerAudio || !Number.isFinite(musicPlayerAudio.duration) || musicPlayerAudio.duration <= 0) {
        isSeekingMusic = false;
        return;
    }

    musicPlayerAudio.currentTime = (Number(musicPlayerProgress.value) / 100) * musicPlayerAudio.duration;
    isSeekingMusic = false;
    updateMusicProgress();
});

document.querySelectorAll('[data-music-player-close]').forEach((button) => {
    button.addEventListener('click', closeMusicPlayer);
});

musicPlayerCta?.addEventListener('click', () => {
    if (!activeMusicButton) {
        return;
    }

    trackElementEvent(activeMusicButton, 'music_permission_cta_clicked', {
        item_type: activeMusicButton.dataset.analyticsType,
        destination: musicPlayerCta.href,
        result: 'clicked',
    });
});

export {
    closeMusicPlayer,
    musicPlayerLayer,
    trapMusicFocus,
};
