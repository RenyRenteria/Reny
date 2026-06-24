import {
    currentAnalyticsScreen,
    sectionAnalyticsKey,
    trackElementEvent,
} from './analytics.js';

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
