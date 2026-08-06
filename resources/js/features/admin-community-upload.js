import { validateCommunityAttachments } from './admin-community-media.js';

const READY_ATTRIBUTE = 'data-community-upload-progress-ready';
const MAX_OVERALL_UPLOAD_PERCENT = 95;

const clampPercent = (value) => Math.max(0, Math.min(100, Math.round(value)));

export const formatUploadBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    const readableSize = Number.isInteger(size) || size >= 10 || unitIndex === 0
        ? Math.round(size)
        : size.toFixed(1);

    return `${readableSize} ${units[unitIndex]}`;
};

export const distributeProgressAcrossFiles = (items, loaded, total) => {
    const safeItems = Array.from(items || []);
    const totalFileBytes = safeItems.reduce((sum, item) => sum + Math.max(Number(item.size) || 0, 0), 0);
    const requestFraction = total > 0 ? Math.max(0, Math.min(1, loaded / total)) : 0;
    let estimatedUploadedBytes = totalFileBytes * requestFraction;

    return safeItems.map((item) => {
        const size = Math.max(Number(item.size) || 0, 0);
        const itemLoaded = Math.min(size, Math.max(estimatedUploadedBytes, 0));
        const percent = size === 0 ? (requestFraction === 1 ? 100 : 0) : clampPercent((itemLoaded / size) * 100);

        estimatedUploadedBytes -= itemLoaded;

        return {
            percent,
            state: percent >= 100 ? 'uploaded' : (percent > 0 ? 'uploading' : 'pending'),
        };
    });
};

const parseJson = (text) => {
    try {
        return JSON.parse(text || '{}');
    } catch (error) {
        return {};
    }
};

export const communityUploadErrorMessage = (xhr, fallback = 'The upload failed. Please try again.') => {
    const payload = parseJson(xhr?.responseText);
    const errors = payload.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors).flat().filter(Boolean)
        : [];

    if (errors.length > 0) {
        return errors.slice(0, 3).join(' ');
    }

    if (payload.message) {
        return payload.message;
    }

    if (xhr?.status === 413) {
        return 'The server rejected this upload because the request is too large. Check the 1 GB limit per video and the server upload settings.';
    }

    if (xhr?.status === 401 || xhr?.status === 403 || xhr?.status === 419) {
        return 'Your session expired or no longer has permission. Refresh the page and try again.';
    }

    if (xhr?.status >= 500) {
        return 'The server could not finish the upload. Your post was not saved; please try again.';
    }

    return fallback;
};

const progressElements = (form) => {
    const panel = form.querySelector('[data-upload-progress]');

    return {
        panel,
        state: panel?.querySelector('[data-upload-state-label]'),
        message: panel?.querySelector('[data-upload-message]'),
        percent: panel?.querySelector('[data-upload-percent]'),
        track: panel?.querySelector('.upload-progress-track'),
        bar: panel?.querySelector('[data-upload-progress-bar]'),
        fileList: panel?.querySelector('[data-upload-file-list]'),
        cancel: panel?.querySelector('[data-upload-cancel]'),
        retry: panel?.querySelector('[data-upload-retry]'),
    };
};

const selectedFiles = (form) => Array.from(form.querySelectorAll('input[type="file"]'))
    .flatMap((input) => Array.from(input.files || []).map((file) => ({
        file,
        size: file.size || 0,
        kind: input.name === 'cover_image' ? 'Cover image' : 'Post media',
        element: null,
        bar: null,
        status: null,
        percent: 0,
    })));

const renderFiles = (elements, items) => {
    elements.fileList.replaceChildren();

    items.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'upload-progress-file';

        const meta = document.createElement('div');
        const name = document.createElement('strong');
        const detail = document.createElement('span');
        const status = document.createElement('span');
        const track = document.createElement('div');
        const bar = document.createElement('span');

        name.textContent = item.file.name;
        detail.textContent = `${item.kind} · ${formatUploadBytes(item.size)}`;
        status.textContent = 'Waiting';
        track.className = 'upload-progress-file-track';
        track.append(bar);
        meta.append(name, detail);
        row.append(meta, status, track);
        elements.fileList.append(row);

        item.element = row;
        item.bar = bar;
        item.status = status;
    });
};

const setFileProgress = (item, percent, state, message = null) => {
    const safePercent = clampPercent(percent);

    item.percent = safePercent;
    item.bar.style.width = `${safePercent}%`;
    item.element.classList.toggle('is-success', state === 'saved');
    item.element.classList.toggle('is-error', ['error', 'canceled'].includes(state));
    item.status.textContent = message || {
        pending: 'Waiting',
        uploading: `Uploading ${safePercent}%`,
        uploaded: 'Uploaded · processing',
        saved: 'Saved',
        error: 'Failed',
        canceled: 'Canceled',
    }[state] || 'Waiting';
};

const setProgress = (elements, state, percent, message) => {
    const safePercent = clampPercent(percent);
    const stateLabel = {
        running: 'Uploading',
        success: 'Complete',
        error: 'Upload failed',
        canceled: 'Canceled',
    }[state] || 'Uploading';

    elements.panel.hidden = false;
    elements.panel.dataset.uploadState = state;
    elements.state.textContent = stateLabel;
    elements.message.textContent = message;
    elements.percent.textContent = `${safePercent}%`;
    elements.bar.style.width = `${safePercent}%`;
    elements.track.setAttribute('aria-valuenow', String(safePercent));
};

const overallFileProgress = (items) => {
    const totalBytes = items.reduce((sum, item) => sum + item.size, 0);

    if (totalBytes <= 0) {
        return 0;
    }

    const uploadedBytes = items.reduce((sum, item) => sum + (item.size * item.percent / 100), 0);

    return Math.min(MAX_OVERALL_UPLOAD_PERCENT, clampPercent((uploadedBytes / totalBytes) * MAX_OVERALL_UPLOAD_PERCENT));
};

const setSubmitState = (form, isSubmitting, submitter) => {
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        if (isSubmitting) {
            button.dataset.uploadWasDisabled = button.disabled ? 'true' : 'false';
            button.disabled = true;
        } else {
            button.disabled = button.dataset.uploadWasDisabled === 'true';
            delete button.dataset.uploadWasDisabled;
        }
    });

    if (!submitter) {
        return;
    }

    if (isSubmitting) {
        submitter.dataset.uploadOriginalText = submitter.textContent;
        submitter.textContent = 'Uploading…';
    } else if (submitter.dataset.uploadOriginalText) {
        submitter.textContent = submitter.dataset.uploadOriginalText;
        delete submitter.dataset.uploadOriginalText;
    }
};

const formActionUrl = (form) => new URL(form.getAttribute('action') || window.location.href, window.location.href).toString();

const formDataFor = (form, submitter) => {
    const formData = new FormData(form);

    if (submitter?.name) {
        formData.set(submitter.name, submitter.value);
    }

    return formData;
};

const csrfToken = (form) => (
    form.querySelector('input[name="_token"]')?.value
    || document.querySelector('meta[name="csrf-token"]')?.content
    || ''
);

const validateForm = (form) => {
    const validAttachments = Array.from(form.querySelectorAll('[data-community-attachments]'))
        .every((input) => validateCommunityAttachments(input, { report: true }));

    return validAttachments && (typeof form.reportValidity !== 'function' || form.reportValidity());
};

export const initializeCommunityUploadProgress = (root = document) => {
    root.querySelectorAll(`[data-community-upload-progress-form]:not([${READY_ATTRIBUTE}])`).forEach((form) => {
        form.setAttribute(READY_ATTRIBUTE, 'true');

        let activeXhr = null;
        let clickedSubmitter = null;
        let lastSubmitter = null;

        form.addEventListener('click', (event) => {
            const submitter = event.target.closest('button[type="submit"], input[type="submit"]');

            if (submitter) {
                clickedSubmitter = submitter;
            }
        });

        const startUpload = (submitter) => {
            if (form.dataset.uploadActive === 'true' || !submitter || !validateForm(form)) {
                return;
            }

            const elements = progressElements(form);
            const items = selectedFiles(form);

            if (!elements.panel || items.length === 0) {
                return;
            }

            form.dataset.uploadActive = 'true';
            lastSubmitter = submitter;
            renderFiles(elements, items);
            setSubmitState(form, true, submitter);
            setProgress(elements, 'running', 0, `Preparing ${items.length} file${items.length === 1 ? '' : 's'}…`);
            elements.cancel.hidden = false;
            elements.cancel.disabled = false;
            elements.retry.hidden = true;

            const xhr = new XMLHttpRequest();
            const token = csrfToken(form);
            activeXhr = xhr;

            const finishFailure = (state, message) => {
                items.forEach((item) => setFileProgress(item, item.percent, state));
                elements.cancel.hidden = true;
                elements.retry.hidden = false;
                setSubmitState(form, false, submitter);
                setProgress(elements, state, overallFileProgress(items), message);
                delete form.dataset.uploadActive;
                activeXhr = null;
            };

            elements.cancel.onclick = () => activeXhr?.abort();
            elements.retry.onclick = () => {
                elements.retry.hidden = true;
                startUpload(lastSubmitter);
            };

            xhr.upload.addEventListener('progress', (event) => {
                const total = event.lengthComputable && event.total > 0
                    ? event.total
                    : items.reduce((sum, item) => sum + item.size, 0);
                const loaded = Math.min(event.loaded || 0, total);
                const fraction = total > 0 ? loaded / total : 0;

                distributeProgressAcrossFiles(items, loaded, total).forEach((fileProgress, index) => {
                    setFileProgress(items[index], fileProgress.percent, fileProgress.state);
                });

                const overallPercent = Math.max(fraction > 0 ? 1 : 0, Math.min(MAX_OVERALL_UPLOAD_PERCENT, clampPercent(fraction * MAX_OVERALL_UPLOAD_PERCENT)));
                const uploadingItem = items.find((item) => item.status?.textContent.startsWith('Uploading'));
                const pendingItem = items.find((item) => item.status?.textContent === 'Waiting');
                const message = uploadingItem
                    ? `Uploading ${uploadingItem.file.name}…`
                    : (pendingItem ? `Preparing ${pendingItem.file.name}…` : 'Files uploaded. Processing them on the server…');

                if (fraction >= 1) {
                    elements.cancel.disabled = true;
                }

                setProgress(elements, 'running', overallPercent, message);
            });

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    const payload = parseJson(xhr.responseText);

                    items.forEach((item) => setFileProgress(item, 100, 'saved'));
                    elements.cancel.hidden = true;
                    elements.retry.hidden = true;
                    setProgress(elements, 'success', 100, payload.message || 'Files uploaded and post saved.');
                    activeXhr = null;

                    window.setTimeout(() => {
                        window.location.assign(payload.redirect_url || xhr.responseURL || window.location.href);
                    }, 650);

                    return;
                }

                finishFailure('error', communityUploadErrorMessage(xhr));
            };

            xhr.onerror = () => finishFailure('error', 'The network connection was interrupted. Your post was not saved; please try again.');
            xhr.ontimeout = () => finishFailure('error', 'The upload took too long and stopped. Your post was not saved; please try again.');
            xhr.onabort = () => finishFailure('canceled', 'Upload canceled. Your post was not saved.');

            xhr.open((form.getAttribute('method') || 'POST').toUpperCase(), formActionUrl(form), true);
            xhr.timeout = 60 * 60 * 1000;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            if (token) {
                xhr.setRequestHeader('X-CSRF-TOKEN', token);
            }

            xhr.send(formDataFor(form, submitter));
        };

        form.addEventListener('submit', (event) => {
            const submitter = event.submitter
                || clickedSubmitter
                || form.querySelector('button[type="submit"]:not(:disabled), input[type="submit"]:not(:disabled)');

            if (!submitter || selectedFiles(form).length === 0) {
                return;
            }

            if (typeof XMLHttpRequest === 'undefined' || typeof FormData === 'undefined') {
                return;
            }

            event.preventDefault();
            startUpload(submitter);
        });
    });
};
