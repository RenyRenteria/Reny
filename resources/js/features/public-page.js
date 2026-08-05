import { trackEvent } from './analytics.js';
import { activateTab, tabFromHash } from './tabs.js';
import {
    closeVideoPlayer,
    initializeVideoInteractions,
    trapVideoFocus,
    videoPlayerElements,
} from './video-player.js';
import {
    closeMusicPlayer,
    musicPlayerLayer,
    trapMusicFocus,
} from './music-player.js';
import { initializePhotoInteractions } from './photos.js';
import { initializeCommunityInteractions } from './community.js';
import { initializeStoreInteractions } from './checkout.js';

const publicPageRoot = () => document.querySelector('[data-public-page-root]');

const publicPageFragmentIds = [
    'photoLightbox',
    'videoPlayerLayer',
    'communityNoteModal',
    'createGroupModal',
    'communityToast',
    'detailLayer',
    'freeEventRsvpLayer',
    'purchaseConfirmationLayer',
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
        '/royals',
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

export { initializePublicPage };
