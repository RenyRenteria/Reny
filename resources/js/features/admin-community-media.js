export const ONE_GIBIBYTE = 1024 ** 3;

const videoExtensions = new Set(['mov', 'mp4', 'webm']);

export const isVideoFile = (file) => {
    const extension = String(file?.name || '').split('.').pop()?.toLowerCase() || '';

    return String(file?.type || '').startsWith('video/') || videoExtensions.has(extension);
};

export const oversizedVideo = (files, maxBytes = ONE_GIBIBYTE) => (
    Array.from(files || []).find((file) => isVideoFile(file) && file.size > maxBytes) || null
);

export const validateCommunityAttachments = (input, { report = false } = {}) => {
    const maxBytes = Number(input?.dataset?.maxVideoBytes) || ONE_GIBIBYTE;
    const file = oversizedVideo(input?.files, maxBytes);
    const message = file
        ? `“${file.name}” is larger than the 1 GB maximum per video.`
        : '';

    input?.setCustomValidity(message);

    if (message && report) {
        input?.reportValidity();
    }

    return message === '';
};

export const initializeCommunityMediaValidation = (root = document) => {
    root.querySelectorAll('[data-community-attachments]').forEach((input) => {
        input.addEventListener('change', () => {
            validateCommunityAttachments(input, { report: true });
        });

        input.form?.addEventListener('submit', (event) => {
            if (!validateCommunityAttachments(input, { report: true })) {
                event.preventDefault();
            }
        });
    });
};
