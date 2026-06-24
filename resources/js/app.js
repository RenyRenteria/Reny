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

const persistedAnalyticsEvents = new Set(['page_view', 'permission_denied']);

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

const panelIds = new Set(['music', 'community']);

function activateTab(tabId) {
    const activeTab = panelIds.has(tabId) ? tabId : 'music';

    document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        const isActive = panel.dataset.tabPanel === activeTab;
        panel.hidden = !isActive;
        panel.classList.toggle('is-active', isActive);
    });

    document.querySelectorAll('[data-tab-link]').forEach((link) => {
        const isActive = link.dataset.tabLink === activeTab;
        link.classList.toggle('is-active', isActive);

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function tabFromHash() {
    return window.location.hash.replace('#', '');
}

window.addEventListener('hashchange', () => activateTab(tabFromHash()));

document.addEventListener('DOMContentLoaded', () => {
    activateTab(tabFromHash());
});

const videoPlayerElements = () => ({
    layer: document.getElementById('videoPlayerLayer'),
    frame: document.getElementById('videoPlayerFrame'),
    title: document.getElementById('videoPlayerTitle'),
    state: document.getElementById('videoPlayerState'),
    message: document.getElementById('videoPlayerMessage'),
    error: document.getElementById('videoPlayerError'),
    external: document.getElementById('videoPlayerExternal'),
    detail: document.getElementById('videoPlayerDetail'),
});
let focusedBeforeVideoPlayer = null;
let activeVideoButton = null;

const getVideoFocusable = () => [...(videoPlayerElements().layer?.querySelectorAll('button, [href], iframe, [tabindex]:not([tabindex="-1"])') || [])]
    .filter((node) => !node.disabled && node.offsetParent !== null);

const trapVideoFocus = (event) => {
    const focusable = getVideoFocusable();
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
        return;
    }

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const closeVideoPlayer = () => {
    const { layer, frame } = videoPlayerElements();

    if (!layer) {
        return;
    }

    layer.hidden = true;
    layer.setAttribute('inert', '');
    frame?.replaceChildren();
    frame?.setAttribute('hidden', '');
    document.body.classList.remove('has-modal-open');
    focusedBeforeVideoPlayer?.focus();
    activeVideoButton = null;
};

const openVideoPlayerLayer = (button) => {
    const { layer } = videoPlayerElements();

    if (!layer) {
        return;
    }

    focusedBeforeVideoPlayer = document.activeElement;
    activeVideoButton = button;
    layer.hidden = false;
    layer.removeAttribute('inert');
    document.body.classList.add('has-modal-open');
    getVideoFocusable()[0]?.focus();
};

const setVideoPlayerExternal = (button) => {
    const { external, detail } = videoPlayerElements();
    const youtubeUrl = button.dataset.youtubeUrl;
    const detailUrl = button.dataset.detailUrl;

    if (external && youtubeUrl) {
        external.href = youtubeUrl;
        external.hidden = false;
    } else {
        external?.setAttribute('hidden', '');
    }

    if (detail && detailUrl) {
        detail.href = detailUrl;
        detail.hidden = false;
    } else {
        detail?.setAttribute('hidden', '');
    }
};

const renderVideoPlayerError = (button, reason, message) => {
    const { state, message: messageNode, frame, error } = videoPlayerElements();

    state.textContent = 'Video unavailable';
    messageNode.textContent = message;
    frame?.replaceChildren();
    frame?.setAttribute('hidden', '');
    error.textContent = message;
    error.hidden = false;

    trackElementEvent(button, 'video_play_failed', {
        item_type: button.dataset.analyticsType || 'video',
        reason,
        result: 'failed',
    });
};

const openVideoPlayer = (button) => {
    const {
        layer,
        frame,
        title: titleNode,
        state,
        message,
        error,
    } = videoPlayerElements();

    if (!layer || !titleNode || !state || !message || !frame || !error) {
        return;
    }

    const youtubeId = button.dataset.youtubeId;
    const title = button.dataset.youtubeTitle || elementAnalyticsLabel(button);

    trackElementEvent(button, 'video_play_clicked', {
        item_type: button.dataset.analyticsType || 'video',
        result: 'clicked',
    });

    titleNode.textContent = title;
    state.textContent = 'Loading';
    message.textContent = 'Loading the selected video.';
    error.hidden = true;
    frame.hidden = true;
    frame.replaceChildren();
    setVideoPlayerExternal(button);
    openVideoPlayerLayer(button);

    if (!youtubeId) {
        renderVideoPlayerError(
            button,
            'missing_youtube_id',
            'This video is published, but its playable YouTube source is not connected yet.',
        );
        return;
    }

    const iframe = document.createElement('iframe');
    iframe.src = `https://www.youtube.com/embed/${encodeURIComponent(youtubeId)}?autoplay=1&rel=0`;
    iframe.title = title;
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    iframe.allowFullscreen = true;

    iframe.addEventListener('load', () => {
        state.textContent = 'Playing';
        message.textContent = 'Streaming from YouTube.';
        trackElementEvent(button, 'video_play_started', {
            item_type: button.dataset.analyticsType || 'video',
            result: 'started',
        });
    }, { once: true });

    iframe.addEventListener('error', () => {
        renderVideoPlayerError(
            button,
            'iframe_error',
            'The YouTube player could not load. Open the video on YouTube or try again in a moment.',
        );
    }, { once: true });

    frame.append(iframe);
    frame.hidden = false;
};

const initializeVideoInteractions = (root = document) => {
    root.querySelectorAll('[data-video-player]').forEach((button) => {
        bindOnce(button, 'video-player-open', 'click', () => openVideoPlayer(button));
    });

    root.querySelectorAll('a.youtube-pill, a.playlist-link, a.video-card-external').forEach((link) => {
        bindOnce(link, 'video-external-link', 'click', () => {
            const url = new URL(link.href, window.location.href);

            trackElementEvent(link, 'video_external_opened', {
                item_type: link.dataset.analyticsType || (link.classList.contains('playlist-link') ? 'playlist' : 'video'),
                item_id: url.searchParams.get('v') || normalizeAnalyticsKey(link.href),
                destination: url.hostname,
                result: 'external_opened',
            });
        });
    });

    const { external, detail, layer } = videoPlayerElements();

    bindOnce(external, 'video-external-modal-link', 'click', () => {
        if (!activeVideoButton) {
            return;
        }

        const url = new URL(external.href, window.location.href);

        trackElementEvent(activeVideoButton, 'video_external_opened', {
            item_type: activeVideoButton.dataset.analyticsType || 'video',
            item_id: url.searchParams.get('v') || normalizeAnalyticsKey(external.href),
            destination: url.hostname,
            result: 'external_opened',
        });
    });

    bindOnce(detail, 'video-detail-modal-link', 'click', () => {
        if (!activeVideoButton) {
            return;
        }

        trackElementEvent(activeVideoButton, 'video_detail_opened', {
            item_type: activeVideoButton.dataset.analyticsType || 'video',
            destination: detail.href,
            result: 'clicked',
        });
    });

    (layer || root).querySelectorAll('[data-video-player-close]').forEach((button) => {
        bindOnce(button, 'video-player-close', 'click', closeVideoPlayer);
    });

    bindOnce(layer, 'video-player-layer-close', 'click', (event) => {
        if (event.target === layer) {
            closeVideoPlayer();
        }
    });
};

document.querySelectorAll('.view-all').forEach((button) => {
    button.addEventListener('click', () => {
        const screen = currentAnalyticsScreen();
        const eventName = screen === 'videos' ? 'video_view_all_clicked' : 'music_view_all_clicked';

        trackElementEvent(button, eventName, {
            item_type: 'section',
            item_id: sectionAnalyticsKey(button),
            result: 'clicked',
        });
    });
});

document.querySelectorAll('.album-deluxe-button').forEach((link) => {
    link.addEventListener('click', () => {
        const url = new URL(link.href, window.location.href);

        trackElementEvent(link, 'music_deluxe_clicked', {
            item_type: 'album',
            destination: url.pathname,
            result: 'clicked',
        });
    });
});

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

const publicPageRoot = () => document.querySelector('[data-public-page-root]');

const publicPageFragmentIds = [
    'photoLightbox',
    'videoPlayerLayer',
    'communityNoteModal',
    'createGroupModal',
    'communityToast',
    'detailLayer',
    'bagLayer',
    'storeToast',
];

const persistentMusicPlayer = () => document.querySelector('[data-global-music-player]');

const updateHeadMeta = (nextDocument, selector) => {
    const nextMeta = nextDocument.head?.querySelector(selector);
    const currentMeta = document.head?.querySelector(selector);

    if (!nextMeta) {
        return;
    }

    if (currentMeta) {
        currentMeta.setAttribute('content', nextMeta.getAttribute('content') || '');
        return;
    }

    document.head.append(nextMeta.cloneNode(true));
};

const syncPublicPageFragments = (nextDocument) => {
    publicPageFragmentIds.forEach((id) => {
        const current = document.getElementById(id);
        const next = nextDocument.getElementById(id);

        if (!next) {
            current?.remove();
            return;
        }

        if (current) {
            current.replaceWith(next);
            return;
        }

        const player = persistentMusicPlayer();

        if (player) {
            player.before(next);
        } else {
            document.body.append(next);
        }
    });

    updateHeadMeta(nextDocument, 'meta[name="csrf-token"]');
    document.body.classList.remove('has-modal-open');
};

const isPersistentPublicPath = (url) => {
    const paths = new Set([
        '/',
        '/music',
        '/music/albums',
        '/music/singles',
        '/music/playlists',
        '/videos',
        '/photos',
        '/community',
        '/store',
    ]);

    return paths.has(url.pathname)
        || url.pathname.startsWith('/album/')
        || url.pathname.startsWith('/community/clubs/');
};

const navigatePublicPage = async (url, { push = true } = {}) => {
    const root = publicPageRoot();

    if (!root) {
        return false;
    }

    try {
        const response = await fetch(url.href, {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'fetch',
            },
        });

        if (!response.ok) {
            return false;
        }

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextRoot = nextDocument.querySelector('[data-public-page-root]');

        if (!nextRoot) {
            return false;
        }

        root.replaceWith(nextRoot);
        syncPublicPageFragments(nextDocument);
        document.title = nextDocument.title;

        const nextScreen = nextDocument.body?.dataset.analyticsScreen;
        if (nextScreen) {
            document.body.dataset.analyticsScreen = nextScreen;
        }

        if (push) {
            window.history.pushState({}, '', url.href);
        }

        window.scrollTo({ top: 0, behavior: 'auto' });
        trackEvent('page_view', {
            title: document.title,
            referrer: null,
            result: 'viewed',
        });
        initializePublicPage(nextRoot);

        return true;
    } catch (error) {
        console.warn(error);
        return false;
    }
};

document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[href]');

    if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    if (link.target || link.hasAttribute('download')) {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || url.hash || !isPersistentPublicPath(url) || !publicPageRoot()) {
        return;
    }

    event.preventDefault();

    const navigated = await navigatePublicPage(url);

    if (!navigated) {
        window.location.assign(url.href);
    }
});

window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);

    if (isPersistentPublicPath(url)) {
        navigatePublicPage(url, { push: false }).catch(() => {});
    }
});

document.addEventListener('keydown', (event) => {
    const currentVideoPlayerLayer = videoPlayerElements().layer;

    if (currentVideoPlayerLayer && !currentVideoPlayerLayer.hidden) {
        if (event.key === 'Tab') {
            trapVideoFocus(event);
        } else if (event.key === 'Escape') {
            closeVideoPlayer();
        }

        return;
    }

    if (!musicPlayerLayer || musicPlayerLayer.hidden) {
        return;
    }

    if (event.key === 'Tab') {
        trapMusicFocus(event);
    } else if (event.key === 'Escape') {
        closeMusicPlayer();
    }
});

let activePhotoTile = null;

const photoLightboxElements = () => ({
    layer: document.getElementById('photoLightbox'),
    image: document.getElementById('photoLightboxImage'),
    type: document.getElementById('photoLightboxType'),
    title: document.getElementById('photoLightboxTitle'),
    caption: document.getElementById('photoLightboxCaption'),
    close: document.getElementById('photoLightboxClose'),
});

const closePhotoLightbox = () => {
    const { layer, image } = photoLightboxElements();

    if (!layer || !image) {
        return;
    }

    layer.classList.remove('is-open');
    layer.setAttribute('aria-hidden', 'true');
    image.removeAttribute('src');

    if (activePhotoTile) {
        activePhotoTile.focus();
    }
};

const openPhotoLightbox = (tile) => {
    const {
        layer,
        image,
        type,
        title,
        caption,
        close,
    } = photoLightboxElements();

    if (!layer || !image || !type || !title || !caption) {
        return;
    }

    activePhotoTile = tile;
    image.src = tile.dataset.photoSrc;
    image.alt = tile.dataset.photoTitle || '';
    type.textContent = `${tile.dataset.photoType || 'Photo'} / ${tile.dataset.photoTone || 'gallery'}`;
    title.textContent = tile.dataset.photoTitle || 'Photo';
    caption.textContent = tile.dataset.photoCaption || '';
    layer.classList.add('is-open');
    layer.setAttribute('aria-hidden', 'false');
    close?.focus();

    trackElementEvent(tile, 'photo_opened', {
        item_type: 'photo',
        item_id: normalizeAnalyticsKey(tile.dataset.photoTitle),
        result: 'opened',
    });
};

const initializePhotoInteractions = (root = document) => {
    root.querySelectorAll('.photo-tile').forEach((tile) => {
        bindOnce(tile, 'photo-tile-open', 'click', () => {
            const tiles = [...document.querySelectorAll('.photo-tile')];
            const usesTouch = window.matchMedia('(hover: none)').matches;

            if (usesTouch && !tile.classList.contains('is-peeking')) {
                tiles.forEach((otherTile) => otherTile.classList.remove('is-peeking'));
                tile.classList.add('is-peeking');
                return;
            }

            openPhotoLightbox(tile);
        });
    });

    const { layer, close } = photoLightboxElements();

    bindOnce(close, 'photo-lightbox-close', 'click', closePhotoLightbox);
    bindOnce(layer, 'photo-lightbox-backdrop', 'click', (event) => {
        if (event.target === layer) {
            closePhotoLightbox();
        }
    });

    bindOnce(document, 'photo-lightbox-escape', 'keydown', (event) => {
        const currentLayer = photoLightboxElements().layer;

        if (event.key !== 'Escape' || !currentLayer?.classList.contains('is-open')) {
            return;
        }

        closePhotoLightbox();
    });
};

const showCommunityToast = (message) => {
    const toast = document.getElementById('communityToast');

    if (!toast) {
        return;
    }

    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(showCommunityToast.timeout);
    showCommunityToast.timeout = window.setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 1800);
};

const communityCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const communityRequestError = (message, reason = null) => Object.assign(new Error(message), {
    reason: reason || normalizeAnalyticsKey(message),
    userMessage: message,
});

const postCommunityJson = async (url, body = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': communityCsrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {})[0]?.[0];
        const fallback = response.status === 401
            ? 'Sign in before using community actions.'
            : response.status === 403
                ? 'Royal Pass required for community actions.'
                : response.status === 419
                    ? 'Refresh this page and try again.'
                    : 'Community action could not be saved. Try again.';

        const error = communityRequestError(validationMessage || payload.message || fallback, payload.message || response.status);
        error.status = response.status;
        throw error;
    }

    return payload;
};

const setCommunityFormStatus = (form, message = '', isError = false) => {
    const status = form?.querySelector('[data-form-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('is-error', isError);
};

const initializeCommunityToastTriggers = (root = document) => {
    root.querySelectorAll('.community-toast-trigger').forEach((button) => {
        bindOnce(button, 'community-toast-trigger', 'click', () => {
            trackElementEvent(button, 'community_action_clicked', {
                item_type: 'community_action',
                result: 'clicked',
            });
            showCommunityToast(button.dataset.toast || 'Coming soon');
        });
    });
};

const initializeCommunityReactions = (root = document) => {
    root.querySelectorAll('.reaction-button').forEach((button) => {
        bindOnce(button, 'community-reaction', 'click', async () => {
            const countNode = button.querySelector('.reaction-count');
            const currentCount = Number(button.dataset.count || countNode?.textContent || 0);
            const wasReacted = button.classList.contains('is-reacted');
            const nextCount = wasReacted ? currentCount - 1 : currentCount + 1;

            button.dataset.count = String(nextCount);
            button.classList.toggle('is-reacted', !wasReacted);
            button.disabled = true;

            if (countNode) {
                countNode.textContent = String(nextCount);
            }

            try {
                if (button.dataset.endpoint) {
                    await postCommunityJson(button.dataset.endpoint);
                }

                trackElementEvent(button, 'community_like_clicked', {
                    item_type: 'reaction',
                    result: button.classList.contains('is-reacted') ? 'liked' : 'unliked',
                });
            } catch (error) {
                console.error(error);
                button.dataset.count = String(currentCount);
                button.classList.toggle('is-reacted', wasReacted);

                if (countNode) {
                    countNode.textContent = String(currentCount);
                }

                showCommunityToast(error.userMessage || 'Like could not be saved.');
                trackElementEvent(button, 'community_like_clicked', {
                    item_type: 'reaction',
                    reason: error.reason || error.message || 'like_failed',
                    result: 'failed',
                });
            } finally {
                button.disabled = false;
            }
        });
    });
};

const normalizePollValues = (values) => {
    const total = values.reduce((sum, value) => sum + value, 0) || 1;
    const exactValues = values.map((value) => (value / total) * 100);
    const roundedValues = exactValues.map(Math.floor);
    let remainder = 100 - roundedValues.reduce((sum, value) => sum + value, 0);

    exactValues
        .map((value, index) => ({ index, fraction: value - Math.floor(value) }))
        .sort((a, b) => b.fraction - a.fraction)
        .forEach(({ index }) => {
            if (remainder <= 0) {
                return;
            }

            roundedValues[index] += 1;
            remainder -= 1;
        });

    return roundedValues;
};

const initializeCommunityPolls = (root = document) => {
    root.querySelectorAll('[data-community-poll], [data-poll]').forEach((poll) => {
        const options = [...poll.querySelectorAll('.poll-option')];
        const totalNode = poll.querySelector('[data-poll-total]');

        options.forEach((option, selectedIndex) => {
            if (option.tagName !== 'BUTTON') {
                return;
            }

            bindOnce(option, 'community-poll-option', 'click', async () => {
                if (poll.dataset.voted === 'true') {
                    showCommunityToast('You already voted in this poll.');
                    return;
                }

                const previousValues = options.map((currentOption) => ({
                    percent: currentOption.dataset.percent,
                    voted: currentOption.classList.contains('is-voted'),
                    disabled: currentOption.disabled,
                    label: currentOption.querySelector('.poll-option-top strong')?.textContent || '',
                    width: currentOption.querySelector('.poll-meter span')?.style.width || '',
                }));
                const previousVoted = poll.dataset.voted;
                const boostedValues = options.map((currentOption, index) => {
                    const currentPercent = Number(currentOption.dataset.percent || 0);
                    return index === selectedIndex ? currentPercent + 8 : Math.max(1, currentPercent - 4);
                });
                const nextValues = normalizePollValues(boostedValues);

                options.forEach((currentOption, index) => {
                    const value = nextValues[index];
                    const percentNode = currentOption.querySelector('.poll-option-top strong');
                    const meter = currentOption.querySelector('.poll-meter span');

                    currentOption.dataset.percent = String(value);
                    currentOption.classList.toggle('is-voted', index === selectedIndex);

                    if (percentNode) {
                        percentNode.textContent = `${value}%`;
                    }

                    if (meter) {
                        meter.style.width = `${value}%`;
                    }
                });

                options.forEach((currentOption) => {
                    if (currentOption.tagName === 'BUTTON') {
                        currentOption.disabled = true;
                    }
                });
                poll.dataset.voted = 'true';

                try {
                    if (poll.dataset.voteEndpoint) {
                        await postCommunityJson(poll.dataset.voteEndpoint, {
                            option_key: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                            option_label: option.dataset.optionLabel || analyticsText(option),
                        });
                    }

                    if (totalNode) {
                        const currentTotal = Number((totalNode.textContent || '').replace(/[^0-9]/g, '')) || 0;
                        totalNode.textContent = `${currentTotal + 1} total votes`;
                    }

                    showCommunityToast('Vote saved.');
                    trackElementEvent(option, 'community_poll_voted', {
                        item_type: 'poll_option',
                        item_id: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                        result: 'voted',
                    });
                } catch (error) {
                    console.error(error);
                    if (error.status !== 409) {
                        poll.dataset.voted = previousVoted || 'false';
                        options.forEach((currentOption, index) => {
                            const previous = previousValues[index];
                            const percentNode = currentOption.querySelector('.poll-option-top strong');
                            const meter = currentOption.querySelector('.poll-meter span');

                            currentOption.dataset.percent = previous.percent;
                            currentOption.classList.toggle('is-voted', previous.voted);

                            if (currentOption.tagName === 'BUTTON') {
                                currentOption.disabled = previous.disabled;
                            }

                            if (percentNode) {
                                percentNode.textContent = previous.label;
                            }

                            if (meter) {
                                meter.style.width = previous.width;
                            }
                        });
                    }

                    showCommunityToast(error.userMessage || 'Vote could not be saved.');
                    trackElementEvent(option, 'community_poll_voted', {
                        item_type: 'poll_option',
                        item_id: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                        reason: error.reason || error.message || 'vote_failed',
                        result: 'failed',
                    });
                }
            });
        });
    });
};

const renderChatMessage = (message, isSelf = false) => {
    const article = document.createElement('article');
    article.className = `chat-message${isSelf ? ' is-self' : ''}`;

    const author = document.createElement('strong');
    author.textContent = message.author;

    const text = document.createElement('p');
    text.textContent = message.text;

    article.append(author, text);

    return article;
};

const initializeCommunityClubLinks = (root = document) => {
    root.querySelectorAll('.club-card a, [data-community-club-open]').forEach((link) => {
        bindOnce(link, 'community-club-open', 'click', () => {
            trackElementEvent(link, 'community_club_opened', {
                item_type: 'country_club',
                item_id: link.closest('[data-club-key]')?.dataset.clubKey || normalizeAnalyticsKey(analyticsText(link)),
                result: 'opened',
            });
        });
    });
};

const initializeCommunityClubJoins = (root = document) => {
    root.querySelectorAll('[data-community-club-join]').forEach((button) => {
        bindOnce(button, 'community-club-join', 'click', async () => {
            if (button.dataset.joined === 'true') {
                showCommunityToast('You already joined this club.');
                return;
            }

            const originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Joining...';

            try {
                const payload = await postCommunityJson(button.dataset.endpoint);
                button.dataset.joined = 'true';
                button.textContent = 'Joined';
                showCommunityToast(payload.message || 'Club joined.');
                trackElementEvent(button, 'community_club_joined', {
                    item_type: 'country_club',
                    item_id: button.dataset.clubKey,
                    result: 'joined',
                });
            } catch (error) {
                console.error(error);
                button.textContent = originalLabel;
                showCommunityToast(error.userMessage || 'Club could not be joined.');
                trackElementEvent(button, 'community_club_joined', {
                    item_type: 'country_club',
                    item_id: button.dataset.clubKey,
                    reason: error.reason || error.message || 'join_failed',
                    result: 'failed',
                });
            } finally {
                button.disabled = false;
            }
        });
    });
};

let previousCreateGroupFocus = null;

const createGroupElements = () => ({
    modal: document.getElementById('createGroupModal'),
    open: document.getElementById('openCreateGroup'),
    close: document.getElementById('closeCreateGroup'),
    form: document.getElementById('createGroupForm'),
    countryName: document.getElementById('createCountryName'),
});

const getCreateGroupFocusable = () => createGroupElements().modal
    ? [...createGroupElements().modal.querySelectorAll('button, input, [href], select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.hasAttribute('disabled'))
    : [];

const closeCreateGroupModal = () => {
    const { modal } = createGroupElements();

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-modal-open');
    previousCreateGroupFocus?.focus();
};

const openCreateGroupModal = () => {
    const { modal, countryName } = createGroupElements();

    if (!modal) {
        return;
    }

    previousCreateGroupFocus = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal-open');
    countryName?.focus();

    trackEvent('community_create_club_started', {
        item_type: 'country_club',
        result: 'started',
    });
};

const initializeCreateGroupModal = () => {
    const {
        modal,
        open,
        close,
        form,
    } = createGroupElements();

    bindOnce(open, 'create-group-open', 'click', openCreateGroupModal);
    bindOnce(close, 'create-group-close', 'click', closeCreateGroupModal);

    bindOnce(modal, 'create-group-backdrop', 'click', (event) => {
        if (event.target === modal) {
            closeCreateGroupModal();
        }
    });

    bindOnce(modal, 'create-group-keydown', 'keydown', (event) => {
        if (event.key === 'Escape') {
            closeCreateGroupModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = getCreateGroupFocusable();
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    bindOnce(form, 'create-group-submit', 'submit', (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const country = String(formData.get('name') || '').trim();
        const activity = String(formData.get('activity') || '').trim();

        if (!country || !activity) {
            setCommunityFormStatus(form, 'Add a country and activity.', true);
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        setCommunityFormStatus(form, 'Creating...');

        postCommunityJson(form.dataset.endpoint, {
            name: country,
            activity,
        }).then((payload) => {
            form.reset();
            closeCreateGroupModal();
            showCommunityToast(payload.message || 'Country club created.');
            trackEvent('community_club_created', {
                item_type: 'country_club',
                item_id: payload.club?.key || normalizeAnalyticsKey(country),
                item_label: payload.club?.name || country,
                result: 'created',
            });

            if (payload.club?.detail_url) {
                window.location.assign(payload.club.detail_url);
            }
        }).catch((error) => {
            console.error(error);
            setCommunityFormStatus(form, error.userMessage || 'Country club could not be created.', true);
            showCommunityToast(error.userMessage || 'Country club could not be created.');
            trackEvent('community_club_created', {
                item_type: 'country_club',
                item_id: normalizeAnalyticsKey(country),
                reason: error.reason || error.message || 'create_club_failed',
                result: 'failed',
            });
        }).finally(() => {
            submitButton.disabled = false;
        });
    });
};

const initializeCommunityNotes = (root = document) => {
    root.querySelectorAll('.community-content .media-cta').forEach((button) => {
        bindOnce(button, 'community-note-open', 'click', () => {
            if (button.dataset.noteOpen) {
                const noteModal = document.getElementById('communityNoteModal');
                const noteTitle = document.getElementById('communityNoteTitle');
                const noteBody = document.getElementById('communityNoteBody');

                if (noteModal && noteTitle && noteBody) {
                    noteTitle.textContent = button.dataset.noteTitle || 'Reny note';
                    noteBody.textContent = button.dataset.noteBody || '';
                    noteModal.classList.add('is-open');
                    noteModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('has-modal-open');
                }
            }

            trackElementEvent(button, 'community_note_opened', {
                item_type: 'reny_note',
                result: 'opened',
            });
        });
    });

    bindOnce(document.getElementById('closeCommunityNote'), 'community-note-close', 'click', () => {
        const noteModal = document.getElementById('communityNoteModal');

        noteModal?.classList.remove('is-open');
        noteModal?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('has-modal-open');
    });
};

const initializeCommunityShares = (root = document) => {
    root.querySelectorAll('.community-content .share').forEach((button) => {
        bindOnce(button, 'community-share', 'click', async () => {
            const shareUrl = button.dataset.shareUrl || window.location.href;
            const shareTitle = button.dataset.shareTitle || document.title;

            try {
                if (navigator.share) {
                    await navigator.share({
                        title: shareTitle,
                        url: shareUrl,
                    });
                } else if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(shareUrl);
                    showCommunityToast('Link copied.');
                } else {
                    window.prompt('Copy this link', shareUrl);
                }

                trackElementEvent(button, 'community_share_clicked', {
                    item_type: 'post',
                    result: 'shared',
                });
            } catch (error) {
                const canceled = error?.name === 'AbortError';

                trackElementEvent(button, 'community_share_clicked', {
                    item_type: 'post',
                    reason: canceled ? 'share_canceled' : error.message || 'share_failed',
                    result: canceled ? 'canceled' : 'failed',
                });

                if (!canceled) {
                    showCommunityToast('Share failed. Try copying the URL.');
                }
            }
        });
    });
};

const initializeCommunityReplyForms = (root = document) => {
    root.querySelectorAll('[data-community-reply-form]').forEach((form) => {
        bindOnce(form, 'community-reply-submit', 'submit', async (event) => {
            event.preventDefault();

            const input = form.querySelector('input[name="body"]');
            const body = input?.value.trim();

            if (!body) {
                setCommunityFormStatus(form, 'Write a reply first.', true);
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setCommunityFormStatus(form, 'Posting...');

            try {
                const payload = await postCommunityJson(form.dataset.endpoint, { body });
                const countNode = document.querySelector(`[data-reply-count="${form.dataset.postKey}"] span`);
                const currentCount = Number((countNode?.textContent || '').replace(/[^0-9]/g, '')) || 0;

                if (countNode) {
                    countNode.textContent = `${currentCount + 1} replies`;
                }

                input.value = '';
                setCommunityFormStatus(form, payload.message || 'Reply posted.');
                showCommunityToast(payload.message || 'Reply posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'post_reply',
                    item_id: form.dataset.postKey,
                    result: 'submitted',
                });
            } catch (error) {
                console.error(error);
                setCommunityFormStatus(form, error.userMessage || 'Reply could not be posted.', true);
                showCommunityToast(error.userMessage || 'Reply could not be posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'post_reply',
                    item_id: form.dataset.postKey,
                    reason: error.reason || error.message || 'reply_failed',
                    result: 'failed',
                });
            } finally {
                submitButton.disabled = false;
            }
        });
    });
};

const initializeCommunityClubMessageForms = (root = document) => {
    root.querySelectorAll('[data-community-club-message]').forEach((form) => {
        bindOnce(form, 'community-club-message-submit', 'submit', async (event) => {
            event.preventDefault();

            const input = form.querySelector('input[name="body"]');
            const body = input?.value.trim();
            const countryChatFeed = document.getElementById('countryChatFeed');

            if (!body || !countryChatFeed) {
                setCommunityFormStatus(form, 'Write a message first.', true);
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setCommunityFormStatus(form, 'Posting...');

            try {
                const payload = await postCommunityJson(form.dataset.endpoint, { body });
                countryChatFeed.append(renderChatMessage({ author: payload.author || 'You', text: payload.text || body }, true));
                countryChatFeed.scrollTop = countryChatFeed.scrollHeight;
                input.value = '';
                setCommunityFormStatus(form, payload.message || 'Message posted.');
                showCommunityToast(payload.message || 'Message posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'country_club_reply',
                    item_id: form.dataset.clubKey,
                    result: 'submitted',
                });
            } catch (error) {
                console.error(error);
                setCommunityFormStatus(form, error.userMessage || 'Message could not be posted.', true);
                showCommunityToast(error.userMessage || 'Message could not be posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'country_club_reply',
                    item_id: form.dataset.clubKey,
                    reason: error.reason || error.message || 'club_reply_failed',
                    result: 'failed',
                });
            } finally {
                submitButton.disabled = false;
            }
        });
    });
};

const initializeCommunitySoftPollButtons = (root = document) => {
    root.querySelectorAll('.vote-card .soft-button').forEach((button) => {
        bindOnce(button, 'community-soft-poll', 'click', () => {
            trackElementEvent(button, 'community_poll_voted', {
                item_type: 'poll',
                result: 'clicked',
            });
        });
    });
};

const initializeCommunityInteractions = (root = document) => {
    initializeCommunityToastTriggers(root);
    initializeCommunityReactions(root);
    initializeCommunityPolls(root);
    initializeCommunityClubLinks(root);
    initializeCommunityClubJoins(root);
    initializeCreateGroupModal();
    initializeCommunityNotes(root);
    initializeCommunityShares(root);
    initializeCommunityReplyForms(root);
    initializeCommunityClubMessageForms(root);
    initializeCommunitySoftPollButtons(root);
};

function initializePublicPage(root = document) {
    activateTab(tabFromHash());
    initializeVideoInteractions(root);
    initializePhotoInteractions(root);
    initializeCommunityInteractions(root);
    initializeStoreInteractions(root);
}

document.addEventListener('DOMContentLoaded', () => {
    initializePublicPage();
}, { once: true });

document.querySelectorAll('.auth-form').forEach((form) => {
    form.addEventListener('submit', () => {
        const action = new URL(form.action, window.location.href).pathname;
        const eventName = action.includes('register')
            ? 'auth_register_started'
            : action.includes('forgot-password')
                ? 'auth_password_recovery_started'
                : 'auth_login_started';

        trackEvent(eventName, {
            item_type: 'auth_form',
            item_id: normalizeAnalyticsKey(action),
            result: 'submitted',
        });
    });
});

document.querySelectorAll('.member-card-link, .account-action').forEach((link) => {
    link.addEventListener('click', () => {
        const action = link.href.includes('/login') ? 'auth_login_started' : 'account_navigation_clicked';

        trackElementEvent(link, action, {
            item_type: 'account_link',
            destination: new URL(link.href, window.location.href).pathname,
            result: 'clicked',
        });
    });
});

document.querySelectorAll('.access-gate-button').forEach((link) => {
    link.addEventListener('click', () => {
        trackElementEvent(link, 'paywall_cta_clicked', {
            item_type: 'access_gate',
            section: link.closest('.access-gate')?.dataset.section || currentAnalyticsScreen(),
            result: 'clicked',
        });
    });
});

const initializeStoreInteractions = (root = document) => {
    const scope = root === document ? document : root;
    const storeShell = scope.querySelector?.('.store-shell') || document.querySelector('.store-shell');
    const storeCheckoutLayer = document.getElementById('bagLayer');
    const commerceRoot = storeShell
        || storeCheckoutLayer
        || scope.querySelector?.('[data-free-event-rsvp]')
        || scope.querySelector?.('[data-buy]')
        || scope.querySelector?.('[data-rsvp]')
        || document.querySelector('[data-free-event-rsvp]')
        || document.querySelector('[data-buy]')
        || document.querySelector('[data-rsvp]');

    if (!commerceRoot) {
        return;
    }

    const prices = {
        deluxe: 24,
        singles: 8,
        royal: 4.99,
        merch: 48,
        print: 86,
        concert: 0,
        listening: 15,
        making: 0,
    };

    const currencies = {
        usd: { symbol: '$', rate: 1, decimals: 0 },
        eur: { symbol: '€', rate: 0.92, decimals: 0 },
        gbp: { symbol: '£', rate: 0.78, decimals: 0 },
        dop: { symbol: 'RD$', rate: 59, decimals: 0 },
    };

    const products = {};
    let currency = 'usd';
    const settlementCurrency = 'usd';
    let bag = [];
    let activeProduct = null;
    let activeFreeEventButton = null;
    let focusedBeforeStoreModal = null;

    const storeToast = document.getElementById('storeToast');
    const bagCount = document.getElementById('bagCount');
    const bagList = document.getElementById('bagList');
    const bagTotal = document.getElementById('bagTotal');
    const nameField = document.getElementById('nameField');
    const emailField = document.getElementById('emailField');
    const phoneField = document.getElementById('phoneField');
    const countryField = document.getElementById('countryField');
    const freeEventRsvpLayer = document.getElementById('freeEventRsvpLayer');
    const freeEventRsvpForm = document.getElementById('freeEventRsvpForm');
    const freeEventRsvpTitle = document.getElementById('freeEventRsvpTitle');
    const freeEventRsvpEventName = document.getElementById('freeEventRsvpEventName');
    const freeEventRsvpName = document.getElementById('freeEventRsvpName');
    const freeEventRsvpEmail = document.getElementById('freeEventRsvpEmail');
    const freeEventRsvpCountry = document.getElementById('freeEventRsvpCountry');
    const freeEventRsvpSubmit = document.getElementById('freeEventRsvpSubmit');
    const freeEventRsvpStatus = document.getElementById('freeEventRsvpStatus');
    const paypalButtons = document.getElementById('paypalButtons');
    const paymentStatus = document.getElementById('paymentStatus');
    const paymentButtons = [...document.querySelectorAll('.store-payments button[data-payment-method]')];
    const freeEventRsvpButtons = [...document.querySelectorAll('[data-free-event-rsvp]')];
    const rsvpButtons = [...document.querySelectorAll('[data-rsvp]')];
    const countdownNodes = [...document.querySelectorAll('[data-countdown-at]')];
    const tierLabel = document.getElementById('tierLabel');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let activePaymentMethod = 'paypal';
    let paypalButtonsRendered = false;
    let paypalButtonsLoading = false;
    let paypalSdkPromise = null;
    let activePayPalOrderId = null;

    document.querySelectorAll('[data-detail]').forEach((button) => {
        products[button.dataset.detail] = {
            name: button.dataset.name,
            type: button.dataset.type,
            priceKey: button.dataset.priceKey,
            availability: button.dataset.availability,
            points: button.dataset.points,
            pass: button.dataset.pass,
            access: button.dataset.access,
            summary: button.dataset.summary,
            image: button.dataset.image,
            cta: button.dataset.cta || 'Add to bag',
        };
    });

    document.querySelectorAll('[data-price][data-price-value]').forEach((node) => {
        const value = Number.parseFloat(node.dataset.priceValue || '');

        if (Number.isFinite(value)) {
            prices[node.dataset.price] = value;
        }
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        const key = button.dataset.buy;

        if (!key) {
            return;
        }

        const buyPriceValue = Number.parseFloat(button.dataset.buyPriceValue || '');

        if (Number.isFinite(buyPriceValue)) {
            prices[key] = buyPriceValue;
        }

        products[key] = {
            ...products[key],
            name: button.dataset.buyName || products[key]?.name || key,
            type: button.dataset.buyType || products[key]?.type || 'Product',
            priceKey: key,
            availability: products[key]?.availability || 'Available',
            points: products[key]?.points || '+0 pts',
            pass: products[key]?.pass || 'No Royal Pass required',
            access: products[key]?.access || 'Checkout unlocks in profile',
            summary: button.dataset.buySummary || products[key]?.summary || 'Store checkout',
            image: button.dataset.buyImage || products[key]?.image,
            cta: button.textContent?.trim() || products[key]?.cta || 'Add to bag',
        };
    });

    const countdownLabel = (target, endedLabel) => {
        const seconds = Math.max(0, Math.floor((target.getTime() - Date.now()) / 1000));

        if (!Number.isFinite(seconds) || seconds <= 0) {
            return endedLabel || 'Today';
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);

        if (days > 0) {
            return `${days}D ${hours}H`;
        }

        const minutes = Math.floor((seconds % 3600) / 60);

        if (hours > 0) {
            return `${hours}H ${minutes}M`;
        }

        return `${Math.max(1, minutes)}M`;
    };

    const renderCountdowns = () => {
        countdownNodes.forEach((node) => {
            const target = new Date(node.dataset.countdownAt || '');

            if (Number.isNaN(target.getTime())) {
                return;
            }

            node.textContent = countdownLabel(target, node.dataset.countdownEndedLabel);
        });
    };

    if (countdownNodes.length > 0) {
        renderCountdowns();
        window.clearInterval(window.renyStoreCountdownInterval);
        window.renyStoreCountdownInterval = window.setInterval(renderCountdowns, 60000);
    }

    const money = (value, suffix = '') => {
        const current = currencies[currency];
        const converted = value * current.rate;
        const hasFractionalAmount = Math.abs(converted - Math.round(converted)) > Number.EPSILON;
        const decimals = hasFractionalAmount ? Math.max(current.decimals, 2) : current.decimals;
        const amount = converted.toLocaleString('en-US', {
            maximumFractionDigits: decimals,
            minimumFractionDigits: decimals,
        });

        return `${current.symbol}${amount}${suffix}`;
    };

    const showStoreToast = (message) => {
        if (!storeToast) {
            return;
        }

        storeToast.textContent = message;
        storeToast.classList.add('is-visible');
        window.clearTimeout(showStoreToast.timeout);
        showStoreToast.timeout = window.setTimeout(() => {
            storeToast.classList.remove('is-visible');
        }, 2200);
    };

    const setPaymentStatus = (message) => {
        if (paymentStatus) {
            paymentStatus.textContent = message;
        }
    };

    const checkoutError = (message, checkoutState = 'validation_failed', reason = null) => Object.assign(new Error(message), {
        checkoutState,
        reason: reason || normalizeAnalyticsKey(message),
        userMessage: message,
    });

    const trackPaymentState = (method, checkoutState, details = {}) => {
        const eventName = checkoutState === 'payment_success'
            ? 'store_payment_succeeded'
            : checkoutState === 'payment_started'
                ? 'store_checkout_started'
                : 'store_payment_failed';

        trackEvent(eventName, {
            item_type: checkoutState === 'payment_started' ? 'checkout' : 'payment_method',
            item_id: method,
            method,
            checkout_state: checkoutState,
            result: checkoutState,
            ...details,
        });
    };

    const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    const normalizeInternationalPhone = (value) => String(value || '').trim().replace(/[()\s.-]/g, '');

    const isValidPhone = (value) => /^\+[1-9][0-9]{6,14}$/.test(normalizeInternationalPhone(value));

    const markFieldValidity = (field, valid) => {
        if (!field) {
            return;
        }

        field.setAttribute('aria-invalid', valid ? 'false' : 'true');
    };

    const customerDetailsComplete = () => {
        const name = nameField?.value?.trim() || '';
        const email = emailField?.value?.trim() || '';
        const phone = phoneField?.value?.trim() || '';
        const country = countryField?.value?.trim() || '';

        return name.length > 0 && isValidEmail(email) && isValidPhone(phone) && country.length > 0;
    };

    const contactPayload = () => {
        const name = nameField?.value?.trim() || '';
        const email = emailField?.value?.trim() || '';
        const phone = phoneField?.value?.trim() || '';
        const country = countryField?.value?.trim() || '';

        if (!name) {
            markFieldValidity(nameField, false);
            throw checkoutError('Add your name.', 'validation_failed', 'missing_name');
        }

        if (!isValidEmail(email)) {
            markFieldValidity(emailField, false);
            throw checkoutError('Add a valid receipt email.', 'validation_failed', 'invalid_email');
        }

        if (!isValidPhone(phone)) {
            markFieldValidity(phoneField, false);
            throw checkoutError('Add a valid international phone number.', 'validation_failed', 'invalid_phone');
        }

        if (!country) {
            markFieldValidity(countryField, false);
            throw checkoutError('Select your country.', 'validation_failed', 'missing_country');
        }

        return {
            identifier: email,
            customer_name: name,
            customer_email: email,
            customer_phone: normalizeInternationalPhone(phone),
            customer_country: country,
        };
    };

    const checkoutPayload = () => {
        if (!bag.length) {
            throw checkoutError('Add a product first.', 'validation_failed', 'empty_cart');
        }

        const contact = contactPayload();

        return {
            identifier: contact.identifier,
            customer_name: contact.customer_name,
            customer_email: contact.customer_email,
            customer_phone: contact.customer_phone,
            customer_country: contact.customer_country,
            product_keys: [...bag],
            currency: settlementCurrency.toUpperCase(),
        };
    };

    const postCheckoutJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const checkoutState = response.status === 422 ? 'validation_failed' : 'payment_failed';

            throw checkoutError(validationMessage || payload.message || 'Checkout failed.', checkoutState, payload.message || 'checkout_request_failed');
        }

        return payload;
    };

    const cancelPendingPayPalOrder = async () => {
        const paypalOrderId = activePayPalOrderId;
        const endpoint = paypalButtons?.dataset.cancelOrderEndpoint;
        activePayPalOrderId = null;

        if (!paypalOrderId || !endpoint) {
            return;
        }

        await postCheckoutJson(endpoint, {
            paypal_order_id: paypalOrderId,
        });
    };

    const rsvpError = (message, reason = null) => Object.assign(new Error(message), {
        reason: reason || normalizeAnalyticsKey(message),
        userMessage: message,
    });

    const postRsvpJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const fallback = response.status === 401
                ? 'Sign in before saving RSVP.'
                : response.status === 419
                    ? 'Refresh this page and try RSVP again.'
                    : 'RSVP could not be saved. Try again.';

            throw rsvpError(validationMessage || payload.message || fallback, payload.message || response.status);
        }

        return payload;
    };

    const postFreeEventRsvpJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];
            const fallback = response.status === 419
                ? 'Refresh this page and try again.'
                : 'Registration could not be saved. Try again.';

            throw rsvpError(validationMessage || payload.message || fallback, payload.message || response.status);
        }

        return payload;
    };

    const loadPayPalSdk = (clientId) => {
        if (window.paypal?.Buttons) {
            return Promise.resolve(window.paypal);
        }

        if (paypalSdkPromise) {
            return paypalSdkPromise;
        }

        paypalSdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = 'paypal-sdk';
            script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(settlementCurrency.toUpperCase())}&intent=capture`;
            script.async = true;
            script.onload = () => {
                if (window.paypal?.Buttons) {
                    resolve(window.paypal);
                    return;
                }

                reject(checkoutError('PayPal checkout could not load.', 'unavailable', 'paypal_sdk_unavailable'));
            };
            script.onerror = () => reject(checkoutError('PayPal checkout could not load.', 'unavailable', 'paypal_sdk_unavailable'));
            document.head.append(script);
        });

        return paypalSdkPromise;
    };

    const completeApprovedCheckout = (payload) => {
        if (tierLabel && payload.royal_status === 'royal_active') {
            tierLabel.textContent = 'ROYAL MEMBER';
        }

        bag = [];
        renderBag();
        closeStoreLayer('bagLayer');
        showStoreToast('PayPal confirmed. Hub updated.');

        if (payload.account_url) {
            window.location.assign(payload.account_url);
        }
    };

    const renderPayPalButtons = async () => {
        if (!paypalButtons) {
            return;
        }

        if (paypalButtonsRendered) {
            setPaymentStatus(customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.');
            return;
        }

        if (paypalButtonsLoading) {
            return;
        }

        paypalButtonsLoading = true;
        const clientId = paypalButtons.dataset.paypalClientId;

        try {
            if (!clientId) {
                trackPaymentState('paypal', 'unavailable', {
                    reason: 'paypal_not_configured',
                });
                throw checkoutError('PayPal is not configured.', 'unavailable', 'paypal_not_configured');
            }

            setPaymentStatus('Loading PayPal checkout...');
            const paypal = await loadPayPalSdk(clientId);
            paypalButtons.replaceChildren();

            await paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                },
                createOrder: async () => {
                    const payload = checkoutPayload();
                    [nameField, emailField, phoneField, countryField].forEach((field) => markFieldValidity(field, true));
                    setPaymentStatus('Creating PayPal order...');
                    trackPaymentState('paypal', 'payment_started', {
                        item_count: payload.product_keys.length,
                        currency: payload.currency,
                    });
                    const order = await postCheckoutJson(paypalButtons.dataset.createOrderEndpoint, payload);
                    activePayPalOrderId = order.paypal_order_id;
                    setPaymentStatus('Approve payment in PayPal.');

                    return order.paypal_order_id;
                },
                onApprove: async (data) => {
                    const payload = checkoutPayload();
                    const paypalOrderId = data.orderID || activePayPalOrderId;
                    setPaymentStatus('Capturing approved PayPal payment...');
                    const capture = await postCheckoutJson(paypalButtons.dataset.captureEndpoint, {
                        ...payload,
                        paypal_order_id: paypalOrderId,
                    });

                    activePayPalOrderId = null;
                    completeApprovedCheckout(capture);
                    trackPaymentState('paypal', 'payment_success', {
                        paypal_order_id: paypalOrderId,
                    });
                },
                onCancel: async () => {
                    await cancelPendingPayPalOrder().catch((error) => console.warn(error));
                    setPaymentStatus('PayPal checkout canceled. No purchase was recorded.');
                    showStoreToast('PayPal checkout canceled.');
                    trackPaymentState('paypal', 'payment_failed', {
                        reason: 'canceled',
                    });
                },
                onError: (error) => {
                    console.error(error);
                    cancelPendingPayPalOrder().catch((cancelError) => console.warn(cancelError));
                    setPaymentStatus(error.userMessage || 'PayPal checkout failed. No purchase was recorded.');
                    showStoreToast(error.userMessage || 'PayPal checkout failed.');
                    trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                        reason: error.userMessage || error.message || 'paypal_error',
                    });
                },
            }).render(paypalButtons);

            paypalButtonsRendered = true;
            setPaymentStatus(customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.');
        } finally {
            paypalButtonsLoading = false;
        }
    };

    const updateStorePrices = () => {
        document.querySelectorAll('[data-price]').forEach((node) => {
            const key = node.dataset.price;
            const suffix = key === 'royal' ? '/mo' : '';

            node.textContent = money(prices[key] || 0, suffix);
        });
    };

    const getStoreFocusable = (layer) => [...layer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.disabled && node.offsetParent !== null);

    const trapStoreFocus = (layer, event) => {
        const focusable = getStoreFocusable(layer);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const openStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        focusedBeforeStoreModal = document.activeElement;
        layer.hidden = false;
        layer.removeAttribute('inert');
        document.body.classList.add('has-modal-open');
        getStoreFocusable(layer)[0]?.focus();
    };

    const closeStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        layer.hidden = true;
        layer.setAttribute('inert', '');
        document.body.classList.remove('has-modal-open');
        focusedBeforeStoreModal?.focus();
    };

    const paymentMethodLabel = (method) => ({
        apple_pay: 'Apple Pay',
        card: 'Card',
        paypal: 'PayPal',
    }[method] || method);

    const unavailableReason = (method) => paymentButtons.find((button) => button.dataset.paymentMethod === method)?.dataset.unavailableReason
        || `${method}_provider_not_configured`;

    const isPaymentMethodAvailable = (method) => paymentButtons.find((button) => button.dataset.paymentMethod === method)?.dataset.providerAvailable === 'true';

    const refreshCheckoutControls = ({ preserveStatus = false } = {}) => {
        const hasItems = bag.length > 0;

        paymentButtons.forEach((button) => {
            button.disabled = !hasItems;
        });

        if (paypalButtons) {
            paypalButtons.hidden = activePaymentMethod !== 'paypal' || !hasItems;
        }

        if (!hasItems) {
            if (!preserveStatus) {
                setPaymentStatus('Add a product to enable PayPal checkout.');
            }
            return;
        }

        if (activePaymentMethod === 'paypal') {
            if (!preserveStatus) {
                setPaymentStatus(paypalButtonsRendered
                    ? (customerDetailsComplete() ? 'Use the PayPal button to approve payment.' : 'Add customer details, then approve with PayPal.')
                    : 'Loading PayPal checkout...');
            }
            return;
        }

        if (!preserveStatus) {
            setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
        }
    };

    const selectPaymentMethod = (method, { track = true } = {}) => {
        activePaymentMethod = method;

        paymentButtons.forEach((button) => {
            const active = button.dataset.paymentMethod === method;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });

        refreshCheckoutControls();

        if (!track) {
            return;
        }

        trackEvent('store_payment_method_selected', {
            item_type: 'payment_method',
            item_id: method,
            method,
            checkout_state: isPaymentMethodAvailable(method) ? 'selected' : 'unavailable',
            result: 'selected',
        });

        if (!isPaymentMethodAvailable(method)) {
            trackPaymentState(method, 'unavailable', {
                reason: unavailableReason(method),
            });
        }
    };

    const renderBag = () => {
        if (!bagList || !bagTotal) {
            return;
        }

        if (bagCount) {
            bagCount.textContent = String(bag.length);
        }
        bagList.replaceChildren();

        if (!bag.length) {
            const empty = document.createElement('div');
            empty.className = 'store-bag-item';
            empty.innerHTML = `<span>Bag is empty</span><strong>${money(0)}</strong>`;
            bagList.append(empty);
            bagTotal.textContent = money(0);
            refreshCheckoutControls();
            return;
        }

        let total = 0;

        bag.forEach((key) => {
            const product = products[key];

            if (!product) {
                return;
            }

            const priceKey = product.priceKey || key;
            total += prices[priceKey] || 0;

            const item = document.createElement('div');
            item.className = 'store-bag-item';

            const copy = document.createElement('div');
            copy.className = 'store-bag-copy';

            const name = document.createElement('strong');
            name.textContent = product.name;

            const meta = document.createElement('span');
            meta.textContent = product.type || 'Product';

            const summary = document.createElement('p');
            summary.textContent = product.summary || 'Store checkout';

            const price = document.createElement('strong');
            price.className = 'store-bag-price';
            price.textContent = money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : '');

            if (product.image) {
                const image = document.createElement('img');
                image.className = 'store-bag-image';
                image.src = product.image;
                image.alt = '';
                image.decoding = 'async';
                item.append(image);
            }

            copy.append(name, meta, summary);
            item.append(copy, price);
            bagList.append(item);
        });

        bagTotal.textContent = money(total);
        refreshCheckoutControls();
    };

    const setCheckoutProduct = (key) => {
        if (!products[key]) {
            return false;
        }

        bag = [key];
        renderBag();

        return true;
    };

    const addToBag = (key) => {
        if (!setCheckoutProduct(key)) {
            return false;
        }

        trackEvent('store_product_added', {
            item_type: products[key].type || 'product',
            item_id: key,
            item_label: products[key].name,
            result: 'added',
        });

        return true;
    };

    const openBuyUrl = (button) => {
        if (!button.dataset.buyUrl) {
            return false;
        }

        const url = new URL(button.dataset.buyUrl, window.location.href);

        trackElementEvent(button, 'store_checkout_link_opened', {
            item_type: button.dataset.buyType || 'product',
            item_id: button.dataset.buy,
            destination: url.pathname,
            result: 'opened',
        });

        window.location.assign(url.href);

        return true;
    };

    const initializeVisiblePayPalCheckout = async () => {
        if (activePaymentMethod !== 'paypal') {
            setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
            trackPaymentState(activePaymentMethod, 'unavailable', {
                reason: unavailableReason(activePaymentMethod),
            });
            return;
        }

        try {
            await renderPayPalButtons();
        } catch (error) {
            setPaymentStatus(error.userMessage || 'PayPal checkout is unavailable.');
            showStoreToast(error.userMessage || 'PayPal checkout is unavailable.');
            trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                reason: error.userMessage || error.message || 'checkout_unavailable',
            });
        } finally {
            refreshCheckoutControls({ preserveStatus: true });
        }
    };

    const openCheckoutModal = (key, { source = 'buy_button', itemType = 'checkout' } = {}) => {
        if (!storeCheckoutLayer || !setCheckoutProduct(key)) {
            return false;
        }

        const product = products[key];
        selectPaymentMethod('paypal', { track: false });
        openStoreLayer('bagLayer');
        trackEvent('store_checkout_started', {
            item_type: itemType || product?.type || 'checkout',
            item_id: key,
            item_label: product?.name,
            item_count: bag.length,
            source,
            result: 'opened',
        });
        void initializeVisiblePayPalCheckout();

        return true;
    };

    const startCheckoutFromBuyButton = (button, { source = 'buy_button' } = {}) => {
        if (openCheckoutModal(button.dataset.buy, {
            source,
            itemType: button.dataset.buyType || 'checkout',
        })) {
            return true;
        }

        if (openBuyUrl(button)) {
            return true;
        }

        if (addToBag(button.dataset.buy)) {
            openStoreLayer('bagLayer');
            return true;
        }

        return false;
    };

    const openProductDetail = (key) => {
        const product = products[key];

        if (!product) {
            return;
        }

        activeProduct = key;
        document.getElementById('detailTitle').textContent = product.name;
        document.getElementById('detailText').textContent = product.summary;

        const detailImage = document.getElementById('detailImage');

        if (detailImage && product.image) {
            detailImage.src = product.image;
            detailImage.alt = product.name;
        }

        const grid = document.getElementById('detailGrid');
        const priceKey = product.priceKey || key;

        grid.replaceChildren();
        [
            [money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : ''), 'Price'],
            [product.type, 'Type'],
            [product.availability, 'Availability'],
            [product.points, 'Points'],
            [product.pass, 'Royal Pass'],
            [product.access, 'Access'],
        ].forEach(([value, label]) => {
            const cell = document.createElement('div');
            const strong = document.createElement('strong');
            const span = document.createElement('span');

            strong.textContent = value;
            span.textContent = label;
            cell.append(strong, span);
            grid.append(cell);
        });

        document.getElementById('detailBuy').textContent = product.cta || 'Checkout with PayPal';
        openStoreLayer('detailLayer');
    };

    document.querySelectorAll('.currency-button').forEach((button) => {
        button.addEventListener('click', () => {
            currency = button.dataset.currency || 'usd';
            document.querySelectorAll('.currency-button').forEach((node) => {
                const active = node === button;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            updateStorePrices();
            renderBag();
            trackElementEvent(button, 'store_currency_selected', {
                item_type: 'currency',
                item_id: currency,
                result: 'selected',
            });
        });
    });

    document.querySelectorAll('.store-filter').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter || 'all';

            document.querySelectorAll('.store-filter').forEach((node) => {
                const active = node === button;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            document.querySelectorAll('.store-product-card').forEach((card) => {
                const categories = (card.dataset.category || '').split(' ');
                card.hidden = filter !== 'all' && !categories.includes(filter);
            });

            trackElementEvent(button, 'store_filter_selected', {
                item_type: 'product_filter',
                item_id: filter,
                result: 'selected',
            });
        });
    });

    document.querySelectorAll('[data-detail]').forEach((button) => {
        button.addEventListener('click', () => {
            trackElementEvent(button, 'store_product_opened', {
                item_type: button.dataset.type || 'product',
                item_id: button.dataset.detail,
                result: 'opened',
            });
            openProductDetail(button.dataset.detail);
        });
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        button.addEventListener('click', () => {
            startCheckoutFromBuyButton(button);
        });
    });

    document.querySelectorAll('[data-copy-current-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyUrl || window.location.href;
            const successLabel = button.dataset.copySuccess || 'Link copied';
            const originalLabel = button.textContent;

            try {
                if (!navigator.clipboard?.writeText) {
                    throw new Error('clipboard_unavailable');
                }

                await navigator.clipboard.writeText(value);
                button.textContent = successLabel;
                showStoreToast(successLabel);
                trackElementEvent(button, 'store_checkout_link_copied', {
                    item_type: 'checkout_link',
                    destination: new URL(value, window.location.href).pathname,
                    result: 'copied',
                });
            } catch (error) {
                console.warn(error);
                showStoreToast('Copy the checkout URL from the address bar.');
                trackElementEvent(button, 'store_checkout_link_copy_failed', {
                    item_type: 'checkout_link',
                    reason: error.message || 'clipboard_unavailable',
                    result: 'failed',
                });
            } finally {
                window.setTimeout(() => {
                    button.textContent = originalLabel;
                }, 1800);
            }
        });
    });

    const setRsvpStatus = (button, message, { tone = 'neutral', code = null, accountUrl = null } = {}) => {
        const status = document.getElementById(button.dataset.rsvpStatusTarget);

        if (!status) {
            return;
        }

        status.classList.toggle('is-confirmed', tone === 'confirmed');
        status.classList.toggle('is-error', tone === 'error');
        status.replaceChildren();

        const text = document.createElement('span');
        text.textContent = code ? `${message} Code ${code}` : message;
        status.append(text);

        if (accountUrl) {
            const link = document.createElement('a');
            link.href = accountUrl;
            link.textContent = 'View in account';
            status.append(' ', link);
        }
    };

    const renderRsvpSuccess = (button, payload) => {
        const ticket = payload.ticket || {};
        const event = payload.event || {};
        const statusLabel = String(ticket.status || 'reserved').replace(/_/g, ' ');

        button.dataset.rsvpConfirmed = 'true';
        button.textContent = 'RSVP confirmed';
        setRsvpStatus(button, `${event.name || button.dataset.rsvpName || 'Event'} reserved - ${statusLabel}.`, {
            tone: 'confirmed',
            code: ticket.code,
            accountUrl: payload.account_url,
        });
    };

    const setFreeEventRsvpStatus = (message, isError = false) => {
        if (!freeEventRsvpStatus) {
            return;
        }

        freeEventRsvpStatus.textContent = message || '';
        freeEventRsvpStatus.classList.toggle('is-error', isError);
    };

    const resetFreeEventRsvpForm = () => {
        freeEventRsvpForm?.reset();
        setFreeEventRsvpStatus('');
        [freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => markFieldValidity(field, true));
    };

    const openFreeEventRsvpModal = (button) => {
        if (!freeEventRsvpLayer || !freeEventRsvpForm) {
            return false;
        }

        activeFreeEventButton = button;
        resetFreeEventRsvpForm();
        freeEventRsvpForm.dataset.eventKey = button.dataset.freeEventRsvp || '';
        freeEventRsvpForm.dataset.eventName = button.dataset.freeEventName || elementAnalyticsLabel(button);

        if (freeEventRsvpTitle) {
            freeEventRsvpTitle.textContent = button.dataset.freeEventName || 'Get Tickets';
        }

        if (freeEventRsvpEventName) {
            freeEventRsvpEventName.textContent = button.dataset.freeEventName || '';
        }

        openStoreLayer('freeEventRsvpLayer');

        return true;
    };

    const freeEventRsvpPayload = () => {
        const name = freeEventRsvpName?.value?.trim() || '';
        const email = freeEventRsvpEmail?.value?.trim() || '';
        const country = freeEventRsvpCountry?.value?.trim() || '';

        if (!name) {
            markFieldValidity(freeEventRsvpName, false);
            throw rsvpError('Agrega tu nombre.', 'missing_name');
        }

        if (!isValidEmail(email)) {
            markFieldValidity(freeEventRsvpEmail, false);
            throw rsvpError('Agrega un correo válido.', 'invalid_email');
        }

        if (!country) {
            markFieldValidity(freeEventRsvpCountry, false);
            throw rsvpError('Selecciona tu país.', 'missing_country');
        }

        return {
            event_key: freeEventRsvpForm?.dataset.eventKey || '',
            event_name: freeEventRsvpForm?.dataset.eventName || '',
            name,
            email,
            country,
        };
    };

    freeEventRsvpButtons.forEach((button) => {
        button.addEventListener('click', () => {
            trackElementEvent(button, 'free_event_rsvp_started', {
                item_type: 'event',
                item_id: button.dataset.freeEventRsvp,
                result: 'started',
            });

            openFreeEventRsvpModal(button);
        });
    });

    freeEventRsvpForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!activeFreeEventButton) {
            return;
        }

        const endpoint = activeFreeEventButton.dataset.freeEventRsvpEndpoint
            || freeEventRsvpForm.dataset.freeEventRsvpEndpoint;
        const originalLabel = freeEventRsvpSubmit?.textContent || 'Registrarme';

        try {
            const payload = freeEventRsvpPayload();
            [freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => markFieldValidity(field, true));

            if (freeEventRsvpSubmit) {
                freeEventRsvpSubmit.disabled = true;
                freeEventRsvpSubmit.textContent = 'Guardando...';
            }

            const response = await postFreeEventRsvpJson(endpoint, payload);
            const message = response.message || 'Te has registrado con éxito! Te esperamos.';

            activeFreeEventButton.textContent = response.status === 'already_registered' ? 'Ya registrado' : 'Registrado';
            setFreeEventRsvpStatus(message);
            showStoreToast(message);
            trackElementEvent(activeFreeEventButton, 'free_event_rsvp_succeeded', {
                item_type: 'event',
                item_id: activeFreeEventButton.dataset.freeEventRsvp,
                rsvp_status: response.status,
                result: 'succeeded',
            });

            window.setTimeout(() => closeStoreLayer('freeEventRsvpLayer'), 900);
        } catch (error) {
            console.error(error);
            const message = error.userMessage || 'Registration could not be saved. Try again.';

            setFreeEventRsvpStatus(message, true);
            showStoreToast(message);
            trackElementEvent(activeFreeEventButton, 'free_event_rsvp_failed', {
                item_type: 'event',
                item_id: activeFreeEventButton.dataset.freeEventRsvp,
                reason: error.reason || error.message || 'free_event_rsvp_failed',
                result: 'failed',
            });
        } finally {
            if (freeEventRsvpSubmit) {
                freeEventRsvpSubmit.disabled = false;
                freeEventRsvpSubmit.textContent = originalLabel;
            }
        }
    });

    rsvpButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const originalLabel = button.textContent;

            trackElementEvent(button, 'store_rsvp_started', {
                item_type: 'event',
                item_id: button.dataset.rsvp,
                result: 'started',
            });

            button.disabled = true;
            button.textContent = 'Saving RSVP...';

            try {
                const payload = await postRsvpJson(button.dataset.rsvpEndpoint, {
                    event_key: button.dataset.rsvp,
                    event_name: button.dataset.rsvpName || elementAnalyticsLabel(button),
                });

                renderRsvpSuccess(button, payload);
                showStoreToast(payload.message || 'RSVP confirmed.');
                trackElementEvent(button, 'store_rsvp_succeeded', {
                    item_type: 'event',
                    item_id: button.dataset.rsvp,
                    ticket_status: payload.ticket?.status,
                    rsvp_status: payload.ticket?.rsvp_status,
                    result: 'succeeded',
                });
            } catch (error) {
                console.error(error);
                const message = error.userMessage || 'RSVP could not be saved. Try again.';

                setRsvpStatus(button, message, { tone: 'error' });
                showStoreToast(message);
                trackElementEvent(button, 'store_rsvp_failed', {
                    item_type: 'event',
                    item_id: button.dataset.rsvp,
                    reason: error.reason || error.message || 'rsvp_failed',
                    result: 'failed',
                });

                if (button.dataset.rsvpConfirmed !== 'true') {
                    button.textContent = originalLabel;
                }
            } finally {
                button.disabled = false;
            }
        });
    });

    document.getElementById('detailBuy')?.addEventListener('click', () => {
        if (!activeProduct) {
            return;
        }

        closeStoreLayer('detailLayer');
        openCheckoutModal(activeProduct, {
            source: 'detail_modal',
            itemType: products[activeProduct]?.type || 'product',
        });
    });

    document.getElementById('openBag')?.addEventListener('click', () => {
        renderBag();
        openStoreLayer('bagLayer');
        if (bag.length) {
            void initializeVisiblePayPalCheckout();
        }
        trackEvent('store_checkout_started', {
            item_type: 'checkout',
            item_count: bag.length,
            result: bag.length ? 'opened' : 'empty',
        });
    });

    paymentButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectPaymentMethod(button.dataset.paymentMethod || 'paypal');
        });
    });

    const openRequestedCheckout = () => {
        const autoOpenButton = document.querySelector('[data-auto-open-checkout="true"][data-buy]');

        if (autoOpenButton && products[autoOpenButton.dataset.buy]) {
            openCheckoutModal(autoOpenButton.dataset.buy, { source: 'dedicated_checkout_url' });
            return;
        }

        const requestedProduct = new URLSearchParams(window.location.search).get('buy');

        if (!requestedProduct || !products[requestedProduct]) {
            return;
        }

        openCheckoutModal(requestedProduct, { source: 'query_buy', itemType: 'checkout' });
    };

    const openAutoCheckout = () => {
        const button = document.querySelector('[data-auto-open-checkout][data-buy]');

        if (!button || bag.length > 0) {
            return;
        }

        startCheckoutFromBuyButton(button, { source: 'shareable_checkout' });
    };

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeStoreLayer(button.dataset.close));
    });

    window.renyStoreKeydownAbort?.abort();
    window.renyStoreKeydownAbort = new AbortController();
    document.addEventListener('keydown', (event) => {
        const openLayerId = ['bagLayer', 'detailLayer', 'freeEventRsvpLayer'].find((id) => {
            const layer = document.getElementById(id);
            return layer && !layer.hidden;
        });

        if (!openLayerId) {
            return;
        }

        const layer = document.getElementById(openLayerId);

        if (event.key === 'Tab') {
            trapStoreFocus(layer, event);
        } else if (event.key === 'Escape') {
            closeStoreLayer(openLayerId);
        }
    }, { signal: window.renyStoreKeydownAbort.signal });

    selectPaymentMethod('paypal', { track: false });
    updateStorePrices();
    renderBag();
    [nameField, emailField, phoneField, countryField, freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].forEach((field) => {
        field?.addEventListener('input', () => {
            markFieldValidity(field, true);
            refreshCheckoutControls();
            if ([freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].includes(field)) {
                setFreeEventRsvpStatus('');
            }
        });
        field?.addEventListener('change', () => {
            markFieldValidity(field, true);
            refreshCheckoutControls();
            if ([freeEventRsvpName, freeEventRsvpEmail, freeEventRsvpCountry].includes(field)) {
                setFreeEventRsvpStatus('');
            }
        });
    });
    openRequestedCheckout();
    openAutoCheckout();
};

const adminSectionThemes = {
    dashboard: 'neutral',
    contenido: 'music',
    editor: 'music',
    biblioteca: 'video',
    productos: 'royal',
    royalpass: 'royal',
    usuarios: 'community',
    comunidad: 'community',
    eventos: 'events',
    puntos: 'community',
    pagos: 'royal',
    notificaciones: 'community',
    equipo: 'neutral',
    historial: 'neutral',
    ajustes: 'neutral',
};

const adminToast = (title, message, type = 'info') => {
    const toast = document.getElementById('toastNotification');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');

    if (!toast || !toastTitle || !toastMessage || !toastIcon) {
        return;
    }

    toastTitle.textContent = title;
    toastMessage.textContent = message;
    toastIcon.textContent = type === 'success' ? '✓' : type === 'danger' ? '!' : 'i';
    toast.dataset.type = type;
    toast.classList.add('is-visible');
    toast.setAttribute('aria-hidden', 'false');

    window.clearTimeout(window.renyAdminToastTimer);
    window.renyAdminToastTimer = window.setTimeout(() => {
        toast.classList.remove('is-visible');
        toast.setAttribute('aria-hidden', 'true');
    }, 3200);
};

const activateAdminSection = (sectionId, updateHash = true) => {
    const target = document.getElementById(`sec-${sectionId}`);

    if (!target) {
        return false;
    }

    document.querySelectorAll('[data-admin-section-panel]').forEach((section) => {
        section.classList.toggle('is-active', section === target);
    });

    document.querySelectorAll('[data-admin-nav]').forEach((link) => {
        link.classList.toggle('is-active', link.dataset.adminNav === sectionId);
    });

    document.querySelectorAll('.ds-main-tab').forEach((tab) => {
        tab.classList.toggle('is-selected', tab.dataset.adminNav === sectionId);
    });

    document.body.dataset.theme = adminSectionThemes[sectionId] || 'neutral';
    document.body.dataset.adminCurrentSection = sectionId;

    if (updateHash) {
        if (sectionId === 'dashboard') {
            history.replaceState(null, '', window.location.pathname);
        } else {
            history.replaceState(null, '', `#${sectionId}`);
        }
    }

    document.getElementById('sidebar')?.classList.remove('is-open');
    document.getElementById('sidebarOverlay')?.classList.remove('is-visible');

    return true;
};

const syncAdminTypeFields = () => {
    const typeSelect = document.querySelector('#content-type');
    const fieldsets = Array.from(document.querySelectorAll('[data-type-fieldset]'));

    if (!typeSelect || !fieldsets.length) {
        return;
    }

    fieldsets.forEach((fieldset) => {
        const isActive = fieldset.dataset.typeFieldset === typeSelect.value;
        fieldset.hidden = !isActive;
        fieldset.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !isActive;
        });
    });
};

const syncAdminPreview = () => {
    const titleField = document.getElementById('postTitle');
    const descField = document.getElementById('postDesc');
    const accessField = document.getElementById('postAccess');
    const titleDisplay = document.getElementById('previewTitleDisplay');
    const descDisplay = document.getElementById('previewDescDisplay');
    const accessDisplay = document.getElementById('previewAccessDisplay');

    if (titleDisplay && titleField) {
        titleDisplay.textContent = titleField.value || 'Titulo de prueba';
    }

    if (descDisplay && descField) {
        descDisplay.textContent = descField.value || 'Descripcion de prueba...';
    }

    if (accessDisplay && accessField) {
        accessDisplay.textContent = accessField.options[accessField.selectedIndex]?.textContent || 'Libre';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('admin-cms-body')) {
        return;
    }

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar?.classList.toggle('is-open');
            overlay?.classList.toggle('is-visible');
        });
    });

    document.querySelectorAll('[data-admin-close-toast]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('toastNotification')?.classList.remove('is-visible');
        });
    });

    document.querySelectorAll('[data-admin-nav]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const sectionId = link.dataset.adminNav;

            if (sectionId && activateAdminSection(sectionId)) {
                event.preventDefault();
            }
        });
    });

    const hashSection = window.location.hash.replace('#', '');
    if (hashSection) {
        activateAdminSection(hashSection, false);
    } else {
        activateAdminSection(document.body.dataset.adminCurrentSection || 'dashboard', false);
    }

    window.addEventListener('hashchange', () => {
        activateAdminSection(window.location.hash.replace('#', ''), false);
    });

    document.querySelectorAll('[data-admin-filter-scope]').forEach((scope) => {
        scope.querySelectorAll('[data-admin-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                const filter = button.dataset.adminFilter;
                const cardsRoot = scope.nextElementSibling;

                scope.querySelectorAll('[data-admin-filter]').forEach((node) => {
                    node.classList.toggle('is-active', node === button);
                });

                cardsRoot?.querySelectorAll('.content-item').forEach((item) => {
                    item.hidden = filter !== 'todos' && item.dataset.type !== filter;
                });
            });
        });
    });

    document.querySelectorAll('[data-admin-toast]').forEach((button) => {
        button.addEventListener('click', () => {
            const [title, message, type] = button.dataset.adminToast.split('|');
            adminToast(title, message, type || 'info');
        });
    });

    document.querySelector('#content-type')?.addEventListener('change', syncAdminTypeFields);
    syncAdminTypeFields();

    ['postTitle', 'postDesc', 'postAccess'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', syncAdminPreview);
        document.getElementById(id)?.addEventListener('change', syncAdminPreview);
    });
    syncAdminPreview();

    document.querySelectorAll('[data-admin-action-select]').forEach((select) => {
        const syncSchedule = () => {
            const form = select.closest('form');
            const scheduleField = form?.querySelector('[data-admin-schedule-field]');

            if (scheduleField) {
                scheduleField.hidden = select.value !== 'schedule';
            }
        };

        select.addEventListener('change', syncSchedule);
        syncSchedule();
    });

    const notifTitle = document.getElementById('notifTitle');
    const notifMsg = document.getElementById('notifMsg');
    const notifTitlePreview = document.querySelector('[data-admin-notif-title]');
    const notifMsgPreview = document.querySelector('[data-admin-notif-message]');

    const syncNotificationPreview = () => {
        if (notifTitlePreview && notifTitle) {
            notifTitlePreview.textContent = notifTitle.value;
        }

        if (notifMsgPreview && notifMsg) {
            notifMsgPreview.textContent = notifMsg.value;
        }
    };

    notifTitle?.addEventListener('input', syncNotificationPreview);
    notifMsg?.addEventListener('input', syncNotificationPreview);
    document.querySelector('[data-admin-notification-send]')?.addEventListener('click', () => {
        adminToast('Notificacion lista', 'Preview actualizado correctamente.', 'success');
    });
});
