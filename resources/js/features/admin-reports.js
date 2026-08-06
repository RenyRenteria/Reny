const reportQueryForUrl = (search, fallbackQuery) => {
    const params = new URLSearchParams(search);

    if (!params.has('preset') && fallbackQuery) {
        return `?${fallbackQuery}`;
    }

    return search;
};

const syncCustomDateInputs = (form) => {
    const custom = form?.querySelector('input[name="preset"][value="custom"]')?.checked === true;

    form?.querySelectorAll('[data-custom-dates] input').forEach((input) => {
        input.disabled = !custom;
    });

    return custom;
};

const initializeAdminReports = (documentRef = document, windowRef = window) => {
    const form = documentRef.querySelector('[data-report-filter]');

    if (!form) {
        return;
    }

    const fallbackQuery = documentRef.querySelector('meta[name="reny-report-default-query"]')?.content;
    const nextSearch = reportQueryForUrl(windowRef.location.search, fallbackQuery);

    if (nextSearch !== windowRef.location.search) {
        windowRef.history.replaceState({}, '', `${windowRef.location.pathname}${nextSearch}${windowRef.location.hash}`);
    }

    syncCustomDateInputs(form);
    form.querySelectorAll('input[name="preset"]').forEach((input) => {
        input.addEventListener('change', () => syncCustomDateInputs(form));
    });
    form.addEventListener('submit', () => {
        documentRef.body.classList.add('is-report-loading');
        documentRef.querySelector('[data-report-loading]')?.setAttribute('aria-hidden', 'false');

        const status = documentRef.querySelector('[data-report-status]');
        if (status) status.textContent = 'Actualizando todos los módulos…';
    });
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initializeAdminReports(), { once: true });
}

export { initializeAdminReports, reportQueryForUrl, syncCustomDateInputs };
