import {
    bindOnce,
    elementAnalyticsLabel,
    normalizeAnalyticsKey,
    trackElementEvent,
} from './analytics.js';

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
let activeYouTubePlayer = null;
let youtubePlayerApiPromise = null;
let videoPlayerSequence = 0;

const loadYouTubePlayerApi = () => {
    if (window.YT?.Player) {
        return Promise.resolve(window.YT);
    }

    if (youtubePlayerApiPromise) {
        return youtubePlayerApiPromise;
    }

    youtubePlayerApiPromise = new Promise((resolve, reject) => {
        const previousReady = window.onYouTubeIframeAPIReady;
        const timeout = window.setTimeout(() => reject(new Error('youtube_api_timeout')), 10000);

        window.onYouTubeIframeAPIReady = () => {
            previousReady?.();
            window.clearTimeout(timeout);
            resolve(window.YT);
        };

        let script = document.getElementById('reny-youtube-player-api');

        if (!script) {
            script = document.createElement('script');
            script.id = 'reny-youtube-player-api';
            script.src = 'https://www.youtube.com/iframe_api';
            script.async = true;
            document.head.append(script);
        }

        script.addEventListener('error', () => {
            window.clearTimeout(timeout);
            reject(new Error('youtube_api_unavailable'));
        }, { once: true });
    });

    return youtubePlayerApiPromise;
};

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
    activeYouTubePlayer?.destroy?.();
    activeYouTubePlayer = null;
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
    activeYouTubePlayer = null;
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

    activeYouTubePlayer?.destroy?.();
    activeYouTubePlayer = null;
    const mount = document.createElement('div');
    mount.id = `reny-youtube-player-${++videoPlayerSequence}`;
    frame.replaceChildren(mount);
    frame.hidden = false;

    loadYouTubePlayerApi().then((youtube) => {
        if (activeVideoButton !== button || layer.hidden) {
            return;
        }

        let startedTracked = false;
        activeYouTubePlayer = new youtube.Player(mount, {
            videoId: youtubeId,
            host: 'https://www.youtube.com',
            playerVars: {
                autoplay: 1,
                origin: window.location.origin,
                rel: 0,
            },
            events: {
                onReady: (event) => {
                    const playerFrame = event.target.getIframe();
                    playerFrame.title = title;
                    playerFrame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
                    state.textContent = 'Ready';
                    message.textContent = 'Press play to start the video.';
                },
                onStateChange: (event) => {
                    if (event.data !== youtube.PlayerState.PLAYING || startedTracked) {
                        return;
                    }

                    startedTracked = true;
                    state.textContent = 'Playing';
                    message.textContent = 'Streaming from YouTube.';
                    trackElementEvent(button, 'video_play_started', {
                        item_type: button.dataset.analyticsType || 'video',
                        result: 'started',
                    });
                },
                onError: () => renderVideoPlayerError(
                    button,
                    'youtube_player_error',
                    'The YouTube player could not load. Open the video on YouTube or try again in a moment.',
                ),
            },
        });
    }).catch(() => {
        youtubePlayerApiPromise = null;
        renderVideoPlayerError(
            button,
            'youtube_api_unavailable',
            'The YouTube player could not load. Open the video on YouTube or try again in a moment.',
        );
    });
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

export {
    closeVideoPlayer,
    initializeVideoInteractions,
    trapVideoFocus,
    videoPlayerElements,
};
