const customRangeEnabled = (period) => period === 'custom';

const setCustomRangeState = (form, period) => {
    const range = form.querySelector('[data-report-custom-range]');

    if (!range) {
        return;
    }

    const enabled = customRangeEnabled(period);
    range.hidden = !enabled;
    range.querySelectorAll('input').forEach((input) => {
        input.disabled = !enabled;
        input.required = enabled;
    });
};

const initializeReportFilters = (root = document) => {
    root.querySelectorAll('[data-report-filter]').forEach((form) => {
        const selected = form.querySelector('input[name="period"]:checked');
        setCustomRangeState(form, selected?.value || '30d');

        form.querySelectorAll('input[name="period"]').forEach((input) => {
            input.addEventListener('change', () => setCustomRangeState(form, input.value));
        });

        form.addEventListener('submit', () => {
            const status = form.querySelector('[data-report-filter-status]');

            form.classList.add('is-loading');
            form.setAttribute('aria-busy', 'true');
            root.querySelectorAll('[data-report-module]').forEach((module) => {
                module.classList.add('is-loading');
                module.setAttribute('aria-busy', 'true');
                const skeleton = module.querySelector('[data-report-skeleton]');

                if (skeleton) {
                    skeleton.hidden = false;
                }
            });

            if (status) {
                status.textContent = 'Loading the selected report range…';
            }
        });
    });
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initializeReportFilters(document), { once: true });
}

export {
    customRangeEnabled,
    initializeReportFilters,
    setCustomRangeState,
};
