import { trackElementEvent } from './analytics.js';

// Delegation also handles returns home through the persistent music player.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-home-video]');
    if (!trigger || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const frame = trigger.closest('[data-home-video-frame]');
    const videoId = trigger.dataset.homeVideo;
    if (!frame || !/^[\w-]{11}$/.test(videoId)) return;

    event.preventDefault();
    const player = document.createElement('iframe');
    player.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&playsinline=1&rel=0`;
    player.title = trigger.dataset.videoTitle;
    player.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    player.referrerPolicy = 'strict-origin-when-cross-origin';
    player.allowFullscreen = true;
    trackElementEvent(trigger, 'video_play_clicked', { item_type: 'video', result: 'clicked' });
    frame.replaceChildren(player);
    player.focus({ preventScroll: true });
});
