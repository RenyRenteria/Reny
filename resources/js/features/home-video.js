export function initializeHomeVideo(root = document) {
    root.querySelectorAll('[data-home-video]').forEach((trigger) => {
        const frame = trigger.closest('[data-home-video-frame]');
        const videoId = trigger.dataset.homeVideo;
        if (!frame || !/^[\w-]{11}$/.test(videoId)) return;

        const player = document.createElement('iframe');
        // Muted inline playback can start without a gesture, including on mobile.
        // YouTube's controls let visitors enable sound or pause the video.
        player.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&playsinline=1&rel=0`;
        player.title = trigger.dataset.videoTitle;
        player.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        player.referrerPolicy = 'strict-origin-when-cross-origin';
        player.allowFullscreen = true;
        frame.replaceChildren(player);
    });
}
