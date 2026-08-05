import {
    currentAnalyticsScreen,
    normalizeAnalyticsKey,
    trackElementEvent,
    trackEvent,
} from './analytics.js';

document.querySelectorAll('.auth-form').forEach((form) => {
    form.addEventListener('submit', () => {
        const action = new URL(form.action, window.location.href).pathname;
        const eventName = action.includes('register')
            ? 'auth_register_started'
            : action.includes('reset-password')
                ? 'auth_password_reset_submitted'
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
