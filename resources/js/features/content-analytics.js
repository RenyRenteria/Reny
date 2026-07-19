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
