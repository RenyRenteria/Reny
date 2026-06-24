import {
    bindOnce,
    normalizeAnalyticsKey,
    trackElementEvent,
} from './analytics.js';

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

export { initializePhotoInteractions };
