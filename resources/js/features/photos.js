import {
    bindOnce,
    normalizeAnalyticsKey,
    trackElementEvent,
    trackEvent,
} from './analytics.js';

const photoSaveStorageKey = 'reny_saved_photos';

let activePhotoTile = null;
let activePhotoIndex = -1;
let photoToastTimeout = null;

const photoTiles = () => [...document.querySelectorAll('.photo-tile')];
const accessiblePhotoTiles = () => photoTiles().filter((tile) => tile.dataset.photoLocked !== 'true');

const photoLightboxElements = () => ({
    layer: document.getElementById('photoLightbox'),
    frame: document.getElementById('photoLightboxFrame'),
    image: document.getElementById('photoLightboxImage'),
    type: document.getElementById('photoLightboxType'),
    title: document.getElementById('photoLightboxTitle'),
    caption: document.getElementById('photoLightboxCaption'),
    close: document.getElementById('photoLightboxClose'),
    previous: document.getElementById('photoLightboxPrev'),
    next: document.getElementById('photoLightboxNext'),
    share: document.getElementById('photoLightboxShare'),
    save: document.getElementById('photoLightboxSave'),
    deepLink: document.getElementById('photoLightboxDeepLink'),
    error: document.getElementById('photoLightboxError'),
    toast: document.getElementById('photoToast'),
});

const showPhotoToast = (message) => {
    const { toast } = photoLightboxElements();

    if (!toast) {
        return;
    }

    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(photoToastTimeout);
    photoToastTimeout = window.setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 1800);
};

const readSavedPhotoKeys = () => {
    try {
        const saved = JSON.parse(window.localStorage?.getItem(photoSaveStorageKey) || '[]');

        return new Set(Array.isArray(saved) ? saved : []);
    } catch {
        return new Set();
    }
};

let savedPhotoKeys = readSavedPhotoKeys();

const writeSavedPhotoKeys = () => {
    try {
        window.localStorage?.setItem(photoSaveStorageKey, JSON.stringify([...savedPhotoKeys]));

        return true;
    } catch {
        return false;
    }
};

const currentPhotoSlugFromUrl = () => {
    try {
        return new URLSearchParams(window.location.search).get('photo');
    } catch {
        return null;
    }
};

const updatePhotoHistory = (tile, { replace = false } = {}) => {
    if (!tile?.dataset.photoSlug || !window.history?.pushState) {
        return;
    }

    try {
        const url = new URL(window.location.href);

        url.searchParams.set('photo', tile.dataset.photoSlug);
        window.history[replace ? 'replaceState' : 'pushState'](
            { photo: tile.dataset.photoSlug },
            '',
            url,
        );
    } catch {
        // Deep links are progressive enhancement; the lightbox still works without history updates.
    }
};

const clearPhotoHistory = () => {
    if (!window.history?.replaceState || window.location.pathname !== '/photos') {
        return;
    }

    try {
        const url = new URL(window.location.href);

        url.searchParams.delete('photo');
        window.history.replaceState({}, '', url);
    } catch {
        // Closing the lightbox should still work if URL parsing is unavailable.
    }
};

const resetPhotoImageError = () => {
    const { frame, image, error } = photoLightboxElements();

    frame?.classList.remove('has-image-error');
    image?.removeAttribute('aria-hidden');

    if (error) {
        error.hidden = true;
    }
};

const showPhotoImageError = () => {
    const { frame, image, error } = photoLightboxElements();

    frame?.classList.add('has-image-error');
    image?.setAttribute('aria-hidden', 'true');

    if (error) {
        error.hidden = false;
    }
};

const markTileImageError = (tile) => {
    tile.classList.add('is-broken');
    tile.querySelector('[data-photo-error]')?.removeAttribute('hidden');

    if (tile.dataset.photoImageErrorTracked === '1') {
        return;
    }

    tile.dataset.photoImageErrorTracked = '1';
    trackElementEvent(tile, 'photo_image_failed', {
        item_type: 'photo',
        reason: 'image_load_error',
        result: 'failed',
    });
};

const photoKey = (tile) => tile?.dataset.photoKey
    || tile?.dataset.photoSlug
    || normalizeAnalyticsKey(tile?.dataset.photoTitle);

const updatePhotoSaveButton = (tile) => {
    const { save } = photoLightboxElements();

    if (!save || !tile) {
        return;
    }

    const isSaved = savedPhotoKeys.has(photoKey(tile));

    save.classList.toggle('is-saved', isSaved);
    save.setAttribute('aria-pressed', isSaved ? 'true' : 'false');

    const label = save.querySelector('span');

    if (label) {
        label.textContent = isSaved ? 'Saved' : 'Save';
    }
};

const updatePhotoNavigation = () => {
    const { previous, next } = photoLightboxElements();
    const hasMultiplePhotos = accessiblePhotoTiles().length > 1;

    [previous, next].forEach((button) => {
        if (!button) {
            return;
        }

        button.disabled = !hasMultiplePhotos;
        button.setAttribute('aria-disabled', hasMultiplePhotos ? 'false' : 'true');
    });
};

const closePhotoLightbox = ({ restoreFocus = true, updateUrl = true } = {}) => {
    const { layer, image } = photoLightboxElements();

    if (!layer || !image) {
        return;
    }

    layer.classList.remove('is-open');
    layer.setAttribute('aria-hidden', 'true');
    image.removeAttribute('src');
    resetPhotoImageError();
    document.body.classList.remove('has-modal-open');

    if (restoreFocus && activePhotoTile?.isConnected) {
        activePhotoTile.focus();
    }

    activePhotoTile = null;
    activePhotoIndex = -1;

    if (updateUrl) {
        clearPhotoHistory();
    }
};

const openPhotoPaywall = (tile) => {
    trackEvent('paywall_triggered_from_photo', {
        item_type: 'photo',
        item_id: tile.dataset.photoId || normalizeAnalyticsKey(tile.dataset.photoTitle),
        photo_id: tile.dataset.photoId,
        album_id: tile.dataset.photoAlbumId,
        source: 'photos_grid',
        result: 'blocked',
    });

    const trigger = document.querySelector('[data-photo-paywall-trigger][data-buy]');

    if (trigger) {
        trigger.click();
        return;
    }

    window.location.assign('/store/checkout/royal');
};

const openPhotoLightbox = (tile, { updateUrl = true, replaceUrl = false } = {}) => {
    if (tile?.dataset.photoLocked === 'true') {
        openPhotoPaywall(tile);

        return false;
    }

    const {
        layer,
        image,
        type,
        title,
        caption,
        close,
        deepLink,
    } = photoLightboxElements();
    const tiles = accessiblePhotoTiles();
    const nextIndex = tiles.indexOf(tile);

    if (!layer || !image || !type || !title || !caption || nextIndex === -1) {
        return false;
    }

    const wasOpen = layer.classList.contains('is-open');

    activePhotoTile = tile;
    activePhotoIndex = nextIndex;
    resetPhotoImageError();
    image.removeAttribute('src');
    image.alt = tile.dataset.photoTitle || '';

    if (tile.dataset.photoSrc) {
        image.src = tile.dataset.photoSrc;
    } else {
        markTileImageError(tile);
        showPhotoImageError();
    }

    type.textContent = `${tile.dataset.photoType || 'Photo'} / ${tile.dataset.photoTone || 'gallery'}`;
    title.textContent = tile.dataset.photoTitle || 'Photo';
    caption.textContent = tile.dataset.photoCaption || '';
    updatePhotoSaveButton(tile);
    updatePhotoNavigation();

    if (deepLink) {
        deepLink.href = tile.dataset.photoShareUrl || window.location.href;
    }

    layer.classList.add('is-open');
    layer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal-open');

    if (!wasOpen) {
        close?.focus();
    }

    if (updateUrl) {
        updatePhotoHistory(tile, { replace: replaceUrl });
    }

    trackElementEvent(tile, 'photo_opened', {
        item_type: 'photo',
        result: 'opened',
    });

    return true;
};

const openPhotoByOffset = (offset) => {
    const tiles = accessiblePhotoTiles();

    if (tiles.length < 2 || activePhotoIndex === -1) {
        return;
    }

    const nextIndex = (activePhotoIndex + offset + tiles.length) % tiles.length;
    const nextTile = tiles[nextIndex];

    if (openPhotoLightbox(nextTile, { replaceUrl: true })) {
        trackElementEvent(nextTile, 'photo_navigated', {
            item_type: 'photo',
            result: offset > 0 ? 'next' : 'previous',
        });
    }
};

const openPhotoBySlug = (slug, options = {}) => {
    if (!slug) {
        return false;
    }

    const tile = photoTiles().find((candidate) => candidate.dataset.photoSlug === slug);

    if (!tile) {
        return false;
    }

    return openPhotoLightbox(tile, options);
};

const togglePhotoSaved = () => {
    if (!activePhotoTile) {
        return;
    }

    const key = photoKey(activePhotoTile);
    const willSave = !savedPhotoKeys.has(key);

    if (willSave) {
        savedPhotoKeys.add(key);
    } else {
        savedPhotoKeys.delete(key);
    }

    const persisted = writeSavedPhotoKeys();

    updatePhotoSaveButton(activePhotoTile);
    showPhotoToast(willSave
        ? (persisted ? 'Photo saved.' : 'Photo saved for this visit.')
        : 'Photo removed.');
    trackElementEvent(activePhotoTile, 'photo_saved', {
        item_type: 'photo',
        persistence: persisted ? 'local_storage' : 'memory',
        result: willSave ? 'saved' : 'removed',
    });
};

const shareActivePhoto = async () => {
    if (!activePhotoTile) {
        return;
    }

    const tile = activePhotoTile;
    const shareUrl = tile.dataset.photoShareUrl || window.location.href;
    const shareTitle = tile.dataset.photoTitle || document.title;

    try {
        if (navigator.share) {
            await navigator.share({ title: shareTitle, url: shareUrl });
        } else if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(shareUrl);
            showPhotoToast('Link copied.');
        } else {
            const copied = window.prompt('Copy this link', shareUrl);

            if (copied === null) {
                return;
            }
        }

        trackElementEvent(tile, 'photo_shared', {
            item_type: 'photo',
            result: 'shared',
        });
    } catch (error) {
        const canceled = error?.name === 'AbortError';

        trackElementEvent(tile, 'photo_shared', {
            item_type: 'photo',
            reason: canceled ? 'share_canceled' : error?.message || 'share_failed',
            result: canceled ? 'canceled' : 'failed',
        });

        if (!canceled) {
            showPhotoToast('Share failed. Use Photo link instead.');
        }
    }
};

const initializePhotoInteractions = (root = document) => {
    if (activePhotoTile && !activePhotoTile.isConnected) {
        activePhotoTile = null;
        activePhotoIndex = -1;
    }

    root.querySelectorAll('.photo-tile').forEach((tile) => {
        const image = tile.querySelector('img');

        bindOnce(image, 'photo-image-error', 'error', () => markTileImageError(tile));

        if (!image?.getAttribute('src') || (image.complete && image.naturalWidth === 0)) {
            markTileImageError(tile);
        }

        bindOnce(tile, 'photo-tile-open', 'click', () => {
            if (tile.dataset.photoLocked === 'true') {
                openPhotoPaywall(tile);
                return;
            }

            const usesTouch = window.matchMedia('(hover: none)').matches;

            if (usesTouch && !tile.classList.contains('is-peeking')) {
                photoTiles().forEach((otherTile) => otherTile.classList.remove('is-peeking'));
                tile.classList.add('is-peeking');
                return;
            }

            openPhotoLightbox(tile);
        });
    });

    const {
        layer,
        image,
        close,
        previous,
        next,
        share,
        save,
        deepLink,
    } = photoLightboxElements();

    bindOnce(close, 'photo-lightbox-close', 'click', closePhotoLightbox);
    bindOnce(previous, 'photo-lightbox-previous', 'click', () => openPhotoByOffset(-1));
    bindOnce(next, 'photo-lightbox-next', 'click', () => openPhotoByOffset(1));
    bindOnce(save, 'photo-lightbox-save', 'click', togglePhotoSaved);
    bindOnce(share, 'photo-lightbox-share', 'click', shareActivePhoto);
    bindOnce(deepLink, 'photo-lightbox-deep-link', 'click', () => {
        if (!activePhotoTile) {
            return;
        }

        trackElementEvent(activePhotoTile, 'photo_deep_link_opened', {
            item_type: 'photo',
            result: 'opened',
        });
    });
    bindOnce(image, 'photo-lightbox-image-error', 'error', () => {
        if (activePhotoTile) {
            markTileImageError(activePhotoTile);
        }

        showPhotoImageError();
    });
    bindOnce(layer, 'photo-lightbox-backdrop', 'click', (event) => {
        if (event.target === layer) {
            closePhotoLightbox();
        }
    });

    bindOnce(document, 'photo-lightbox-keys', 'keydown', (event) => {
        const currentLayer = photoLightboxElements().layer;

        if (!currentLayer?.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            closePhotoLightbox();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            openPhotoByOffset(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            openPhotoByOffset(1);
        }
    });

    bindOnce(window, 'photo-lightbox-history', 'popstate', () => {
        if (window.location.pathname !== '/photos') {
            return;
        }

        const slug = currentPhotoSlugFromUrl();

        if (slug) {
            openPhotoBySlug(slug, { updateUrl: false });
        } else {
            closePhotoLightbox({ restoreFocus: false, updateUrl: false });
        }
    });

    if (window.location.pathname === '/photos') {
        const deepLinkedSlug = currentPhotoSlugFromUrl();

        if (deepLinkedSlug) {
            window.queueMicrotask(() => {
                openPhotoBySlug(deepLinkedSlug, { updateUrl: false });
            });
        }
    }
};

export { initializePhotoInteractions };
