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

const videoPlayerLayer = document.getElementById('videoPlayerLayer');
const videoPlayerFrame = document.getElementById('videoPlayerFrame');
const videoPlayerTitle = document.getElementById('videoPlayerTitle');
const videoPlayerState = document.getElementById('videoPlayerState');
const videoPlayerMessage = document.getElementById('videoPlayerMessage');
const videoPlayerError = document.getElementById('videoPlayerError');
const videoPlayerExternal = document.getElementById('videoPlayerExternal');
const videoPlayerDetail = document.getElementById('videoPlayerDetail');
let focusedBeforeVideoPlayer = null;
let activeVideoButton = null;

const getVideoFocusable = () => [...(videoPlayerLayer?.querySelectorAll('button, [href], iframe, [tabindex]:not([tabindex="-1"])') || [])]
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
    if (!videoPlayerLayer) {
        return;
    }

    videoPlayerLayer.hidden = true;
    videoPlayerLayer.setAttribute('inert', '');
    videoPlayerFrame?.replaceChildren();
    videoPlayerFrame?.setAttribute('hidden', '');
    document.body.classList.remove('has-modal-open');
    focusedBeforeVideoPlayer?.focus();
    activeVideoButton = null;
};

const openVideoPlayerLayer = (button) => {
    if (!videoPlayerLayer) {
        return;
    }

    focusedBeforeVideoPlayer = document.activeElement;
    activeVideoButton = button;
    videoPlayerLayer.hidden = false;
    videoPlayerLayer.removeAttribute('inert');
    document.body.classList.add('has-modal-open');
    getVideoFocusable()[0]?.focus();
};

const setVideoPlayerExternal = (button) => {
    const youtubeUrl = button.dataset.youtubeUrl;
    const detailUrl = button.dataset.detailUrl;

    if (videoPlayerExternal && youtubeUrl) {
        videoPlayerExternal.href = youtubeUrl;
        videoPlayerExternal.hidden = false;
    } else {
        videoPlayerExternal?.setAttribute('hidden', '');
    }

    if (videoPlayerDetail && detailUrl) {
        videoPlayerDetail.href = detailUrl;
        videoPlayerDetail.hidden = false;
    } else {
        videoPlayerDetail?.setAttribute('hidden', '');
    }
};

const renderVideoPlayerError = (button, reason, message) => {
    videoPlayerState.textContent = 'Video unavailable';
    videoPlayerMessage.textContent = message;
    videoPlayerFrame?.replaceChildren();
    videoPlayerFrame?.setAttribute('hidden', '');
    videoPlayerError.textContent = message;
    videoPlayerError.hidden = false;

    trackElementEvent(button, 'video_play_failed', {
        item_type: button.dataset.analyticsType || 'video',
        reason,
        result: 'failed',
    });
};

const openVideoPlayer = (button) => {
    if (!videoPlayerLayer || !videoPlayerTitle || !videoPlayerState || !videoPlayerMessage || !videoPlayerFrame || !videoPlayerError) {
        return;
    }

    const youtubeId = button.dataset.youtubeId;
    const title = button.dataset.youtubeTitle || elementAnalyticsLabel(button);

    trackElementEvent(button, 'video_play_clicked', {
        item_type: button.dataset.analyticsType || 'video',
        result: 'clicked',
    });

    videoPlayerTitle.textContent = title;
    videoPlayerState.textContent = 'Loading';
    videoPlayerMessage.textContent = 'Loading the selected video.';
    videoPlayerError.hidden = true;
    videoPlayerFrame.hidden = true;
    videoPlayerFrame.replaceChildren();
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
        videoPlayerState.textContent = 'Playing';
        videoPlayerMessage.textContent = 'Streaming from YouTube.';
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

    videoPlayerFrame.append(iframe);
    videoPlayerFrame.hidden = false;
};

document.querySelectorAll('[data-video-player]').forEach((button) => {
    button.addEventListener('click', () => openVideoPlayer(button));
});

document.querySelectorAll('a.youtube-pill, a.playlist-link, a.video-card-external').forEach((link) => {
    link.addEventListener('click', () => {
        const url = new URL(link.href, window.location.href);

        trackElementEvent(link, 'video_external_opened', {
            item_type: link.dataset.analyticsType || (link.classList.contains('playlist-link') ? 'playlist' : 'video'),
            item_id: url.searchParams.get('v') || normalizeAnalyticsKey(link.href),
            destination: url.hostname,
            result: 'external_opened',
        });
    });
});

videoPlayerExternal?.addEventListener('click', () => {
    if (!activeVideoButton) {
        return;
    }

    const url = new URL(videoPlayerExternal.href, window.location.href);

    trackElementEvent(activeVideoButton, 'video_external_opened', {
        item_type: activeVideoButton.dataset.analyticsType || 'video',
        item_id: url.searchParams.get('v') || normalizeAnalyticsKey(videoPlayerExternal.href),
        destination: url.hostname,
        result: 'external_opened',
    });
});

videoPlayerDetail?.addEventListener('click', () => {
    if (!activeVideoButton) {
        return;
    }

    trackElementEvent(activeVideoButton, 'video_detail_opened', {
        item_type: activeVideoButton.dataset.analyticsType || 'video',
        destination: videoPlayerDetail.href,
        result: 'clicked',
    });
});

document.querySelectorAll('[data-video-player-close]').forEach((button) => {
    button.addEventListener('click', closeVideoPlayer);
});

videoPlayerLayer?.addEventListener('click', (event) => {
    if (event.target === videoPlayerLayer) {
        closeVideoPlayer();
    }
});

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

const musicPlayerLayer = document.getElementById('musicPlayerLayer');
const musicPlayerAudio = document.getElementById('musicPlayerAudio');
const musicPlayerTitle = document.getElementById('musicPlayerTitle');
const musicPlayerState = document.getElementById('musicPlayerState');
const musicPlayerMessage = document.getElementById('musicPlayerMessage');
const musicPlayerLoading = document.getElementById('musicPlayerLoading');
const musicPlayerTracks = document.getElementById('musicPlayerTracks');
const musicPlayerDetail = document.getElementById('musicPlayerDetail');
const musicPlayerCta = document.getElementById('musicPlayerCta');
let focusedBeforeMusicPlayer = null;
let activeMusicButton = null;

const getMusicFocusable = () => [...(musicPlayerLayer?.querySelectorAll('button, [href], audio, [tabindex]:not([tabindex="-1"])') || [])]
    .filter((node) => !node.disabled && node.offsetParent !== null);

const trapMusicFocus = (event) => {
    const focusable = getMusicFocusable();
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

const openMusicPlayer = () => {
    if (!musicPlayerLayer) {
        return;
    }

    focusedBeforeMusicPlayer = document.activeElement;
    musicPlayerLayer.hidden = false;
    musicPlayerLayer.removeAttribute('inert');
    document.body.classList.add('has-modal-open');
    getMusicFocusable()[0]?.focus();
};

const closeMusicPlayer = () => {
    if (!musicPlayerLayer) {
        return;
    }

    musicPlayerAudio?.pause();
    musicPlayerLayer.hidden = true;
    musicPlayerLayer.setAttribute('inert', '');
    document.body.classList.remove('has-modal-open');
    focusedBeforeMusicPlayer?.focus();
};

const setMusicLoadingState = (button) => {
    activeMusicButton = button;
    musicPlayerTitle.textContent = elementAnalyticsLabel(button);
    musicPlayerState.textContent = 'Loading';
    musicPlayerMessage.textContent = 'Checking access and audio availability.';
    musicPlayerLoading.hidden = false;
    musicPlayerTracks.hidden = true;
    musicPlayerTracks.replaceChildren();
    musicPlayerCta.hidden = true;
    musicPlayerCta.removeAttribute('href');
    musicPlayerAudio.hidden = true;
    musicPlayerAudio.removeAttribute('src');
    musicPlayerDetail.href = button.dataset.detailUrl || window.location.href;
    openMusicPlayer();
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

const renderMusicPlayerPayload = (payload, button) => {
    const state = payload.state || button.dataset.accessState || 'playback_error';
    const stateLabel = payload.access_label || button.dataset.accessLabel || state.replace(/_/g, ' ');
    const message = payload.message || payload.access_message || button.dataset.accessMessage || 'Playback is not available.';

    musicPlayerLoading.hidden = true;
    musicPlayerTitle.textContent = payload.title || elementAnalyticsLabel(button);
    musicPlayerState.textContent = stateLabel;
    musicPlayerMessage.textContent = message;
    musicPlayerDetail.href = payload.detail_url || button.dataset.detailUrl || window.location.href;
    renderMusicTracks(payload.tracks || []);

    if (payload.cta_url || button.dataset.ctaUrl) {
        musicPlayerCta.href = payload.cta_url || button.dataset.ctaUrl;
        musicPlayerCta.textContent = payload.cta_label || button.dataset.ctaLabel || 'Continue';
        musicPlayerCta.hidden = false;
    } else {
        musicPlayerCta.hidden = true;
        musicPlayerCta.removeAttribute('href');
    }

    if (state === 'ready' && payload.audio_url) {
        musicPlayerAudio.src = payload.audio_url;
        musicPlayerAudio.hidden = false;
        musicPlayerAudio.load();
        trackElementEvent(button, 'music_play_ready', {
            item_type: button.dataset.analyticsType,
            result: 'ready',
        });
        return;
    }

    musicPlayerAudio.hidden = true;
    musicPlayerAudio.removeAttribute('src');

    trackElementEvent(button, state === 'playback_error' ? 'music_play_failed' : 'music_access_blocked', {
        item_type: button.dataset.analyticsType,
        reason: state,
        result: state === 'playback_error' ? 'failed' : 'blocked',
    });
};

document.querySelectorAll('[data-music-play]').forEach((button) => {
    button.addEventListener('click', async () => {
        trackElementEvent(button, 'music_play_clicked', {
            item_type: button.dataset.analyticsType || (button.classList.contains('mini-play') ? 'single' : 'album'),
            result: 'clicked',
        });

        setMusicLoadingState(button);

        if (!button.dataset.playUrl) {
            renderMusicPlayerPayload({
                state: button.dataset.accessState || 'playback_error',
                access_label: button.dataset.accessLabel || 'Audio unavailable',
                message: button.dataset.accessMessage || 'This music item is not connected to playback yet.',
                cta_label: button.dataset.ctaLabel,
                cta_url: button.dataset.ctaUrl,
            }, button);
            return;
        }

        try {
            const response = await fetch(button.dataset.playUrl, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const payload = await response.json().catch(() => ({}));

            renderMusicPlayerPayload(payload, button);
        } catch (error) {
            console.error(error);
            renderMusicPlayerPayload({
                state: 'playback_error',
                access_label: 'Playback error',
                message: 'Playback could not load. Try again in a moment.',
            }, button);
        }
    });
});

musicPlayerAudio?.addEventListener('play', () => {
    if (!activeMusicButton) {
        return;
    }

    trackElementEvent(activeMusicButton, 'music_play_started', {
        item_type: activeMusicButton.dataset.analyticsType,
        result: 'started',
    });
}, { once: false });

musicPlayerAudio?.addEventListener('error', () => {
    if (!activeMusicButton) {
        return;
    }

    musicPlayerState.textContent = 'Playback error';
    musicPlayerMessage.textContent = 'The audio source could not be played.';
    trackElementEvent(activeMusicButton, 'music_play_failed', {
        item_type: activeMusicButton.dataset.analyticsType,
        reason: 'audio_element_error',
        result: 'failed',
    });
});

document.querySelectorAll('[data-music-player-close]').forEach((button) => {
    button.addEventListener('click', closeMusicPlayer);
});

musicPlayerLayer?.addEventListener('click', (event) => {
    if (event.target === musicPlayerLayer) {
        closeMusicPlayer();
    }
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

document.addEventListener('keydown', (event) => {
    if (videoPlayerLayer && !videoPlayerLayer.hidden) {
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

const photoTiles = document.querySelectorAll('.photo-tile');
const photoLightbox = document.getElementById('photoLightbox');
const photoLightboxImage = document.getElementById('photoLightboxImage');
const photoLightboxType = document.getElementById('photoLightboxType');
const photoLightboxTitle = document.getElementById('photoLightboxTitle');
const photoLightboxCaption = document.getElementById('photoLightboxCaption');
const photoLightboxClose = document.getElementById('photoLightboxClose');
let activePhotoTile = null;

const closePhotoLightbox = () => {
    if (!photoLightbox || !photoLightboxImage) {
        return;
    }

    photoLightbox.classList.remove('is-open');
    photoLightbox.setAttribute('aria-hidden', 'true');
    photoLightboxImage.removeAttribute('src');

    if (activePhotoTile) {
        activePhotoTile.focus();
    }
};

const openPhotoLightbox = (tile) => {
    if (!photoLightbox || !photoLightboxImage || !photoLightboxType || !photoLightboxTitle || !photoLightboxCaption) {
        return;
    }

    activePhotoTile = tile;
    photoLightboxImage.src = tile.dataset.photoSrc;
    photoLightboxImage.alt = tile.dataset.photoTitle || '';
    photoLightboxType.textContent = `${tile.dataset.photoType || 'Photo'} / ${tile.dataset.photoTone || 'gallery'}`;
    photoLightboxTitle.textContent = tile.dataset.photoTitle || 'Photo';
    photoLightboxCaption.textContent = tile.dataset.photoCaption || '';
    photoLightbox.classList.add('is-open');
    photoLightbox.setAttribute('aria-hidden', 'false');
    photoLightboxClose?.focus();

    trackElementEvent(tile, 'photo_opened', {
        item_type: 'photo',
        item_id: normalizeAnalyticsKey(tile.dataset.photoTitle),
        result: 'opened',
    });
};

photoTiles.forEach((tile) => {
    tile.addEventListener('click', () => {
        const usesTouch = window.matchMedia('(hover: none)').matches;

        if (usesTouch && !tile.classList.contains('is-peeking')) {
            photoTiles.forEach((otherTile) => otherTile.classList.remove('is-peeking'));
            tile.classList.add('is-peeking');
            return;
        }

        openPhotoLightbox(tile);
    });
});

photoLightboxClose?.addEventListener('click', closePhotoLightbox);

photoLightbox?.addEventListener('click', (event) => {
    if (event.target === photoLightbox) {
        closePhotoLightbox();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !photoLightbox?.classList.contains('is-open')) {
        return;
    }

    closePhotoLightbox();
});

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

document.querySelectorAll('.community-toast-trigger').forEach((button) => {
    button.addEventListener('click', () => {
        trackElementEvent(button, 'community_action_clicked', {
            item_type: 'community_action',
            result: 'clicked',
        });
        showCommunityToast(button.dataset.toast || 'Coming soon');
    });
});

document.querySelectorAll('.reaction-button').forEach((button) => {
    button.addEventListener('click', () => {
        const countNode = button.querySelector('.reaction-count');
        const currentCount = Number(button.dataset.count || countNode?.textContent || 0);
        const nextCount = button.classList.contains('is-reacted') ? currentCount - 1 : currentCount + 1;

        button.dataset.count = String(nextCount);
        button.classList.toggle('is-reacted');

        if (countNode) {
            countNode.textContent = String(nextCount);
        }

        trackElementEvent(button, 'community_like_clicked', {
            item_type: 'reaction',
            result: button.classList.contains('is-reacted') ? 'liked' : 'unliked',
        });
    });
});

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

document.querySelectorAll('[data-poll]').forEach((poll) => {
    const options = [...poll.querySelectorAll('.poll-option')];

    options.forEach((option, selectedIndex) => {
        option.addEventListener('click', () => {
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

            trackElementEvent(option, 'community_poll_voted', {
                item_type: 'poll_option',
                item_id: normalizeAnalyticsKey(analyticsText(option)),
                result: 'voted',
            });
        });
    });
});

const groupTabs = document.querySelector('.country-groups-list');
const countryName = document.getElementById('countryName');
const countryMembers = document.getElementById('countryMembers');
const countryActivity = document.getElementById('countryActivity');
const countryChatFeed = document.getElementById('countryChatFeed');

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

const getCountryTabs = () => [...document.querySelectorAll('.country-group-tab')];

const selectCountryGroup = (tab) => {
    if (!tab || !countryName || !countryMembers || !countryActivity || !countryChatFeed) {
        return;
    }

    getCountryTabs().forEach((currentTab) => {
        const isSelected = currentTab === tab;
        currentTab.classList.toggle('is-active', isSelected);
        currentTab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        currentTab.tabIndex = isSelected ? 0 : -1;
    });

    countryName.textContent = tab.dataset.country || 'Country group';
    countryMembers.textContent = `${tab.dataset.members || '1'} members`;
    countryActivity.textContent = tab.dataset.activity || 'New custom country group';
    countryChatFeed.replaceChildren();

    JSON.parse(tab.dataset.messages || '[]').forEach((message) => {
        countryChatFeed.append(renderChatMessage(message));
    });

    trackElementEvent(tab, 'community_club_opened', {
        item_type: 'country_club',
        item_id: normalizeAnalyticsKey(tab.dataset.country),
        result: 'opened',
    });
};

groupTabs?.addEventListener('click', (event) => {
    const tab = event.target.closest('.country-group-tab');

    if (tab) {
        selectCountryGroup(tab);
    }
});

groupTabs?.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
        return;
    }

    const tabs = getCountryTabs();
    const currentIndex = tabs.indexOf(document.activeElement);

    if (currentIndex === -1) {
        return;
    }

    event.preventDefault();

    let nextIndex = currentIndex;

    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        nextIndex = (currentIndex + 1) % tabs.length;
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
        nextIndex = 0;
    } else if (event.key === 'End') {
        nextIndex = tabs.length - 1;
    }

    tabs[nextIndex]?.focus();
    selectCountryGroup(tabs[nextIndex]);
});

document.getElementById('countryReplyForm')?.addEventListener('submit', (event) => {
    event.preventDefault();

    const input = document.getElementById('countryReplyInput');
    const text = input?.value.trim();

    if (!text || !countryChatFeed) {
        return;
    }

    countryChatFeed.append(renderChatMessage({ author: 'You', text }, true));
    countryChatFeed.scrollTop = countryChatFeed.scrollHeight;
    input.value = '';

    trackEvent('community_reply_submitted', {
        item_type: 'country_club_reply',
        item_id: normalizeAnalyticsKey(countryName?.textContent),
        result: 'submitted',
    });
});

const createGroupModal = document.getElementById('createGroupModal');
const openCreateGroup = document.getElementById('openCreateGroup');
const closeCreateGroup = document.getElementById('closeCreateGroup');
const createGroupForm = document.getElementById('createGroupForm');
const createCountryName = document.getElementById('createCountryName');
let previousCreateGroupFocus = null;

const getCreateGroupFocusable = () => createGroupModal
    ? [...createGroupModal.querySelectorAll('button, input, [href], select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.hasAttribute('disabled'))
    : [];

const closeCreateGroupModal = () => {
    if (!createGroupModal) {
        return;
    }

    createGroupModal.classList.remove('is-open');
    createGroupModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-modal-open');
    previousCreateGroupFocus?.focus();
};

const openCreateGroupModal = () => {
    if (!createGroupModal) {
        return;
    }

    previousCreateGroupFocus = document.activeElement;
    createGroupModal.classList.add('is-open');
    createGroupModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal-open');
    createCountryName?.focus();

    trackEvent('community_create_club_started', {
        item_type: 'country_club',
        result: 'started',
    });
};

openCreateGroup?.addEventListener('click', openCreateGroupModal);
closeCreateGroup?.addEventListener('click', closeCreateGroupModal);

createGroupModal?.addEventListener('click', (event) => {
    if (event.target === createGroupModal) {
        closeCreateGroupModal();
    }
});

createGroupModal?.addEventListener('keydown', (event) => {
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

createGroupForm?.addEventListener('submit', (event) => {
    event.preventDefault();

    const formData = new FormData(createGroupForm);
    const country = String(formData.get('country') || '').trim();
    const activity = String(formData.get('activity') || '').trim();

    if (!country || !activity || !groupTabs || !openCreateGroup) {
        return;
    }

    const tab = document.createElement('button');
    const messages = [{ author: 'System', text: `${country} group created. Start the first thread.` }];

    tab.className = 'country-group-tab';
    tab.type = 'button';
    tab.role = 'tab';
    tab.setAttribute('aria-selected', 'false');
    tab.setAttribute('aria-controls', 'country-panel');
    tab.tabIndex = -1;
    tab.dataset.country = country;
    tab.dataset.members = '1';
    tab.dataset.activity = activity;
    tab.dataset.messages = JSON.stringify(messages);

    const title = document.createElement('strong');
    title.textContent = country;

    const members = document.createElement('span');
    members.textContent = '1 member';

    tab.append(title, members);
    groupTabs.insertBefore(tab, openCreateGroup);
    createGroupForm.reset();
    closeCreateGroupModal();
    selectCountryGroup(tab);
    tab.focus();

    trackElementEvent(tab, 'community_club_created', {
        item_type: 'country_club',
        item_id: normalizeAnalyticsKey(country),
        result: 'created',
    });
});

document.querySelectorAll('.community-content .media-cta').forEach((button) => {
    button.addEventListener('click', () => {
        trackElementEvent(button, 'community_note_opened', {
            item_type: 'reny_note',
            result: 'opened',
        });
    });
});

document.querySelectorAll('.community-content .share').forEach((button) => {
    button.addEventListener('click', () => {
        trackElementEvent(button, 'community_share_clicked', {
            item_type: 'post',
            result: 'clicked',
        });
    });
});

document.querySelectorAll('.vote-card .soft-button').forEach((button) => {
    button.addEventListener('click', () => {
        trackElementEvent(button, 'community_poll_voted', {
            item_type: 'poll',
            result: 'clicked',
        });
    });
});

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

const storeShell = document.querySelector('.store-shell');

if (storeShell) {
    const prices = {
        deluxe: 24,
        singles: 8,
        royal: 4.99,
        merch: 48,
        print: 86,
        concert: 42,
        listening: 18,
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
    let bag = [];
    let activeProduct = null;
    let focusedBeforeStoreModal = null;

    const storeToast = document.getElementById('storeToast');
    const bagCount = document.getElementById('bagCount');
    const bagList = document.getElementById('bagList');
    const bagTotal = document.getElementById('bagTotal');
    const emailField = document.getElementById('emailField');
    const phoneField = document.getElementById('phoneField');
    const localPaymentPanel = document.getElementById('localPaymentPanel');
    const localReferenceField = document.getElementById('localReferenceField');
    const localReceiptField = document.getElementById('localReceiptField');
    const paypalButtons = document.getElementById('paypalButtons');
    const paymentStatus = document.getElementById('paymentStatus');
    const completePurchaseButton = document.getElementById('completePurchase');
    const paymentButtons = [...document.querySelectorAll('.store-payments button[data-payment-method]')];
    const rsvpButtons = [...document.querySelectorAll('[data-rsvp]')];
    const tierLabel = document.getElementById('tierLabel');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let activePaymentMethod = 'paypal';
    let paypalButtonsRendered = false;
    let paypalSdkPromise = null;

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
        };
    });

    products.concert = {
        name: 'Reny Live - Studio Night',
        type: 'Physical event',
        priceKey: 'concert',
        availability: '96 seats',
        points: '+420 pts',
        pass: 'No Royal Pass required',
        access: 'Ticket unlocks in profile',
        summary: 'Upcoming live concert ticket with instant receipt, profile update, and event access.',
    };

    products.listening = {
        name: 'Deluxe Preview Session',
        type: 'Physical event',
        priceKey: 'listening',
        availability: '40 seats',
        points: '+180 pts',
        pass: 'Royal Pass early access',
        access: 'Ticket unlocks in profile',
        summary: 'Intimate listening room preview for the next deluxe release.',
    };

    document.querySelectorAll('[data-price][data-price-value]').forEach((node) => {
        const value = Number.parseFloat(node.dataset.priceValue || '');

        if (Number.isFinite(value)) {
            prices[node.dataset.price] = value;
        }
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        const key = button.dataset.buy;

        if (!key || products[key]) {
            return;
        }

        products[key] = {
            name: button.dataset.buyName || key,
            type: button.dataset.buyType || 'Event',
            priceKey: key,
            availability: 'Available',
            points: '+0 pts',
            pass: 'No Royal Pass required',
            access: 'Ticket unlocks in profile',
            summary: button.dataset.buySummary || 'Event checkout',
        };
    });

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

    const phoneDigits = (value) => (value || '').replace(/\D+/g, '');

    const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    const isValidPhone = (value) => phoneDigits(value).length >= 7;

    const contactPayload = ({ requireBoth = false } = {}) => {
        const email = emailField?.value?.trim() || '';
        const phone = phoneField?.value?.trim() || '';

        if (requireBoth) {
            if (!isValidEmail(email)) {
                throw checkoutError('Add a valid receipt email.', 'validation_failed', 'invalid_email');
            }

            if (!isValidPhone(phone)) {
                throw checkoutError('Add a valid phone number.', 'validation_failed', 'invalid_phone');
            }

            return {
                identifier: email,
                email,
                phone: phoneDigits(phone),
            };
        }

        if (email) {
            if (!isValidEmail(email)) {
                throw checkoutError('Add a valid receipt email.', 'validation_failed', 'invalid_email');
            }

            return { identifier: email, email, phone: phone ? phoneDigits(phone) : '' };
        }

        if (phone) {
            if (!isValidPhone(phone)) {
                throw checkoutError('Add a valid phone number.', 'validation_failed', 'invalid_phone');
            }

            return { identifier: phone, email: '', phone: phoneDigits(phone) };
        }

        throw checkoutError('Add a valid email or phone.', 'validation_failed', 'missing_contact');
    };

    const checkoutPayload = ({ requireBothContacts = false } = {}) => {
        if (!bag.length) {
            throw checkoutError('Add a product first.', 'validation_failed', 'empty_cart');
        }

        const contact = contactPayload({ requireBoth: requireBothContacts });

        return {
            identifier: contact.identifier,
            product_keys: [...bag],
            currency: currency.toUpperCase(),
        };
    };

    const normalizeLocalReference = (value) => (value || '').trim().toUpperCase().replace(/\s+/g, '-');

    const localCheckoutPayload = () => {
        const payload = checkoutPayload({ requireBothContacts: true });
        const reference = normalizeLocalReference(localReferenceField?.value || '');

        if (!/^(?=.{8,40}$)(?=.*[A-Z])(?=.*\d)[A-Z0-9-]+$/.test(reference)) {
            throw checkoutError('Add a bank reference with at least 8 letters/numbers and one digit.', 'validation_failed', 'invalid_reference');
        }

        if (!localReceiptField?.files?.length) {
            throw checkoutError('Attach a receipt image or PDF.', 'validation_failed', 'missing_receipt');
        }

        return {
            ...payload,
            local_reference: reference,
            receipt_name: localReceiptField.files[0].name,
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
            script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(currency.toUpperCase())}&intent=capture`;
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
        if (!paypalButtons || paypalButtonsRendered) {
            setPaymentStatus('Use the PayPal button to approve payment.');
            return;
        }

        const clientId = paypalButtons.dataset.paypalClientId;

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
                setPaymentStatus('Creating PayPal order...');
                trackPaymentState('paypal', 'payment_started', {
                    item_count: payload.product_keys.length,
                    currency: payload.currency,
                });
                const order = await postCheckoutJson(paypalButtons.dataset.createOrderEndpoint, payload);
                setPaymentStatus('Approve payment in PayPal.');

                return order.paypal_order_id;
            },
            onApprove: async (data) => {
                const payload = checkoutPayload();
                setPaymentStatus('Capturing approved PayPal payment...');
                const capture = await postCheckoutJson(paypalButtons.dataset.captureEndpoint, {
                    ...payload,
                    paypal_order_id: data.orderID,
                });

                completeApprovedCheckout(capture);
                trackPaymentState('paypal', 'payment_success', {
                    paypal_order_id: data.orderID,
                });
            },
            onCancel: () => {
                setPaymentStatus('PayPal checkout canceled. No purchase was recorded.');
                showStoreToast('PayPal checkout canceled.');
                trackPaymentState('paypal', 'payment_failed', {
                    reason: 'canceled',
                });
            },
            onError: (error) => {
                console.error(error);
                setPaymentStatus(error.userMessage || 'PayPal checkout failed. No purchase was recorded.');
                showStoreToast(error.userMessage || 'PayPal checkout failed.');
                trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                    reason: error.userMessage || error.message || 'paypal_error',
                });
            },
        }).render(paypalButtons);

        paypalButtonsRendered = true;
        setPaymentStatus('Use the PayPal button to approve payment.');
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
        local: 'Local',
        paypal: 'PayPal',
    }[method] || method);

    const unavailableReason = (method) => paymentButtons.find((button) => button.dataset.paymentMethod === method)?.dataset.unavailableReason
        || `${method}_provider_not_configured`;

    const refreshCheckoutControls = () => {
        const hasItems = bag.length > 0;

        paymentButtons.forEach((button) => {
            button.disabled = !hasItems;
        });

        if (paypalButtons) {
            paypalButtons.hidden = activePaymentMethod !== 'paypal' || !hasItems;
        }

        if (localPaymentPanel) {
            localPaymentPanel.hidden = activePaymentMethod !== 'local' || !hasItems;
        }

        if (!completePurchaseButton) {
            return;
        }

        if (!hasItems) {
            completePurchaseButton.disabled = true;
            completePurchaseButton.textContent = 'Add item to checkout';
            setPaymentStatus('Add a product before choosing payment.');
            return;
        }

        if (activePaymentMethod === 'paypal') {
            completePurchaseButton.disabled = false;
            completePurchaseButton.textContent = 'Load PayPal checkout';
            setPaymentStatus('PayPal approval is required before the Hub is updated.');
            return;
        }

        if (activePaymentMethod === 'local') {
            completePurchaseButton.disabled = false;
            completePurchaseButton.textContent = 'Check local payment';
            setPaymentStatus('Local transfer needs email, phone, reference, and receipt. Provider connection is still required before purchase completion.');
            return;
        }

        completePurchaseButton.disabled = true;
        completePurchaseButton.textContent = `${paymentMethodLabel(activePaymentMethod)} unavailable`;
        setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
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
            checkout_state: method === 'paypal' ? 'selected' : 'unavailable',
            result: 'selected',
        });

        if (method !== 'paypal') {
            trackPaymentState(method, 'unavailable', {
                reason: unavailableReason(method),
            });
        }
    };

    const handleLocalCheckout = () => {
        try {
            localCheckoutPayload();
        } catch (error) {
            setPaymentStatus(error.userMessage || 'Local payment details are incomplete.');
            showStoreToast(error.userMessage || 'Local payment details are incomplete.');
            trackPaymentState('local', 'validation_failed', {
                reason: error.reason || error.message || 'local_validation_failed',
            });
            return;
        }

        setPaymentStatus('Local payment details are valid, but no local provider is connected yet. No purchase was recorded.');
        showStoreToast('Local payment provider is not connected yet.');
        trackPaymentState('local', 'unavailable', {
            reason: unavailableReason('local'),
        });
    };

    const renderBag = () => {
        if (!bagCount || !bagList || !bagTotal) {
            return;
        }

        bagCount.textContent = String(bag.length);
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

            const name = document.createElement('span');
            name.textContent = product.name;

            const price = document.createElement('strong');
            price.textContent = money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : '');

            item.append(name, price);
            bagList.append(item);
        });

        bagTotal.textContent = money(total);
        refreshCheckoutControls();
    };

    const addToBag = (key) => {
        if (!products[key]) {
            return;
        }

        bag.push(key);
        renderBag();
        showStoreToast(`${products[key].name} added.`);
        trackEvent('store_product_added', {
            item_type: products[key].type || 'product',
            item_id: key,
            item_label: products[key].name,
            result: 'added',
        });
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

        document.getElementById('detailBuy').textContent = 'Add to bag';
        openStoreLayer('detailLayer');
    };

    document.querySelectorAll('.currency-button').forEach((button) => {
        button.addEventListener('click', () => {
            currency = button.dataset.currency || 'usd';
            document.querySelectorAll('.currency-button').forEach((node) => {
                node.classList.toggle('is-active', node === button);
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
                node.setAttribute('aria-selected', active ? 'true' : 'false');
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
            addToBag(button.dataset.buy);
            openStoreLayer('bagLayer');
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

        addToBag(activeProduct);
        closeStoreLayer('detailLayer');
        openStoreLayer('bagLayer');
    });

    document.getElementById('openBag')?.addEventListener('click', () => {
        renderBag();
        openStoreLayer('bagLayer');
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

    completePurchaseButton?.addEventListener('click', async (event) => {
        const button = event.currentTarget;

        if (activePaymentMethod === 'local') {
            handleLocalCheckout();
            return;
        }

        if (activePaymentMethod !== 'paypal') {
            setPaymentStatus(`${paymentMethodLabel(activePaymentMethod)} checkout needs a real provider before purchases can complete.`);
            showStoreToast(`${paymentMethodLabel(activePaymentMethod)} is not available yet.`);
            trackPaymentState(activePaymentMethod, 'unavailable', {
                reason: unavailableReason(activePaymentMethod),
            });
            return;
        }

        button.disabled = true;
        button.textContent = 'Loading PayPal...';

        try {
            const payload = checkoutPayload();
            trackPaymentState('paypal', 'payment_started', {
                item_count: payload.product_keys.length,
                currency: payload.currency,
            });
            await renderPayPalButtons();
            showStoreToast('Use PayPal to approve payment.');
        } catch (error) {
            setPaymentStatus(error.userMessage || 'PayPal checkout is unavailable.');
            showStoreToast(error.userMessage || 'PayPal checkout is unavailable.');
            trackPaymentState('paypal', error.checkoutState || 'payment_failed', {
                reason: error.userMessage || error.message || 'checkout_unavailable',
            });
        } finally {
            refreshCheckoutControls();
        }
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeStoreLayer(button.dataset.close));
    });

    document.addEventListener('keydown', (event) => {
        const openLayerId = ['bagLayer', 'detailLayer'].find((id) => {
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
    });

    selectPaymentMethod('paypal', { track: false });
    updateStorePrices();
    renderBag();
}

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
