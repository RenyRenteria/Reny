document.querySelectorAll('.video-load-button').forEach((button) => {
    button.addEventListener('click', () => {
        const youtubeId = button.dataset.youtubeId;

        if (!youtubeId) {
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube.com/embed/${youtubeId}?autoplay=1`;
        iframe.title = button.dataset.youtubeTitle || 'Reny Renteria YouTube video';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;

        button.replaceWith(iframe);
    }, { once: true });
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
