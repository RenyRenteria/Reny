const photoCmsRoot = () => document.querySelector('[data-photo-cms]');

const renderUploadPreviews = (root, files) => {
    const grid = root.querySelector('[data-photo-preview-grid]');

    if (!grid) {
        return;
    }

    grid.replaceChildren();

    files.forEach((file, index) => {
        const card = document.createElement('article');
        card.className = 'photo-upload-preview';

        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = file.name;
        image.onload = () => URL.revokeObjectURL(image.src);

        const name = document.createElement('strong');
        name.textContent = file.name;

        const visibility = document.createElement('select');
        visibility.name = `visibility[${index}]`;
        visibility.innerHTML = `
            <option value="public">Public</option>
            <option value="member_only">Member only</option>
        `;

        const caption = document.createElement('input');
        caption.name = `captions[${index}]`;
        caption.type = 'text';
        caption.maxLength = 500;
        caption.placeholder = 'Caption';

        card.append(image, name, visibility, caption);
        grid.append(card);
    });
};

const initializeUploadForm = (root) => {
    const form = root.querySelector('[data-photo-upload-form]');
    const input = root.querySelector('[data-photo-file-input]');
    const dropzone = root.querySelector('[data-photo-dropzone]');
    const status = root.querySelector('[data-photo-upload-status]');
    const progress = root.querySelector('[data-photo-upload-progress]');
    const progressBar = root.querySelector('[data-photo-upload-progress-bar]');
    const progressLabel = root.querySelector('[data-photo-upload-progress-label]');

    if (!form || !input) {
        return;
    }

    input.addEventListener('change', () => {
        renderUploadPreviews(root, [...input.files]);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, () => {
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();

        if (!event.dataTransfer?.files?.length) {
            return;
        }

        input.files = event.dataTransfer.files;
        renderUploadPreviews(root, [...input.files]);
    });

    form.addEventListener('submit', (event) => {
        if (!input.files.length || typeof XMLHttpRequest === 'undefined') {
            return;
        }

        event.preventDefault();
        const request = new XMLHttpRequest();
        const formData = new FormData(form);

        if (status) {
            status.textContent = 'Uploading photos...';
        }

        if (progress) {
            progress.hidden = false;
        }

        request.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable || !progressBar || !progressLabel) {
                return;
            }

            const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            progressBar.style.width = `${percent}%`;
            progressLabel.textContent = `Uploading ${percent}%`;
        });

        request.addEventListener('load', () => {
            const payload = JSON.parse(request.responseText || '{}');

            if (request.status >= 200 && request.status < 300) {
                if (status) {
                    status.textContent = payload.message || 'Photos uploaded.';
                }

                window.location.assign(payload.redirect_url || window.location.href);
                return;
            }

            const firstError = Object.values(payload.errors || {})[0]?.[0];

            if (status) {
                status.textContent = firstError || payload.message || 'Upload failed.';
            }
        });

        request.addEventListener('error', () => {
            if (status) {
                status.textContent = 'Upload failed.';
            }
        });

        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.send(formData);
    });
};

const initializePhotoSorting = (root) => {
    const list = root.querySelector('[data-photo-sort-list]');

    if (!list) {
        return;
    }

    let dragging = null;

    const syncOrder = () => {
        [...list.querySelectorAll('[data-photo-row]')].forEach((row, index) => {
            const field = row.querySelector('[data-photo-order-field]');
            const visibleOrder = row.querySelector('input[name="order_index"]');

            if (field) {
                field.value = String(index);
            }

            if (visibleOrder) {
                visibleOrder.value = String(index);
            }
        });
    };

    list.querySelectorAll('[data-photo-row]').forEach((row) => {
        row.addEventListener('dragstart', () => {
            dragging = row;
            row.classList.add('is-dragging');
        });

        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            dragging = null;
            syncOrder();
        });

        row.addEventListener('dragover', (event) => {
            event.preventDefault();

            if (!dragging || dragging === row) {
                return;
            }

            const box = row.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;

            if (after) {
                row.after(dragging);
            } else {
                row.before(dragging);
            }
        });
    });

    syncOrder();
};

document.addEventListener('DOMContentLoaded', () => {
    const root = photoCmsRoot();

    if (!root) {
        return;
    }

    initializeUploadForm(root);
    initializePhotoSorting(root);
}, { once: true });
