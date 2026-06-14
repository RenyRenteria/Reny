const panelIds = new Set(['music', 'community']);

function activateTab(tabId) {
    const activeTab = panelIds.has(tabId) ? tabId : 'music';

    document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        const isActive = panel.dataset.tabPanel === activeTab;
        panel.hidden = !isActive;
        panel.classList.toggle('is-active', isActive);
    });

    document.querySelectorAll('[data-tab-link]').forEach((link) => {
        const isActive = link.dataset.tabLink === activeTab;
        link.classList.toggle('is-active', isActive);

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function tabFromHash() {
    return window.location.hash.replace('#', '');
}

window.addEventListener('hashchange', () => activateTab(tabFromHash()));

document.addEventListener('DOMContentLoaded', () => {
    activateTab(tabFromHash());
});
