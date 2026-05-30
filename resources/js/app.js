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
