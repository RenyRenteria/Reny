import { bindOnce } from './analytics.js';

const initializeAccountHub = () => {
    if (!document.querySelector('.account-stack')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let focusedBeforeAccountModal = null;

    const initialFieldValue = (field) => {
        if (field.tagName === 'SELECT') {
            return [...field.options].find((option) => option.defaultSelected)?.value || field.value;
        }

        return field.defaultValue;
    };

    document.querySelectorAll('[data-account-dirty-form]').forEach((form) => {
        const submit = form.querySelector('[data-account-dirty-submit]');
        const fields = [...form.querySelectorAll('input, select, textarea')]
            .filter((field) => field.name && field.type !== 'hidden');

        const refreshDirtyState = () => {
            const dirty = fields.some((field) => field.value !== initialFieldValue(field));

            if (submit) {
                submit.hidden = !dirty;
            }
        };

        fields.forEach((field) => {
            bindOnce(field, 'account-dirty', 'input', refreshDirtyState);
            bindOnce(field, 'account-dirty', 'change', refreshDirtyState);
        });

        bindOnce(form, 'account-dirty-submit', 'submit', () => {
            if (submit) {
                submit.disabled = true;
            }
        });

        refreshDirtyState();
    });

    const setAvatarStatus = (form, message) => {
        const status = form.querySelector('[data-account-avatar-status]');

        if (status) {
            status.textContent = message;
        }
    };

    const setAvatarPreview = (form, url) => {
        const current = form.querySelector('[data-account-avatar-preview]');

        if (!current) {
            return;
        }

        if (current.tagName === 'IMG') {
            current.src = url;
            return;
        }

        const image = document.createElement('img');
        image.src = url;
        image.alt = '';
        image.decoding = 'async';
        image.setAttribute('data-account-avatar-preview', '');
        current.replaceWith(image);
    };

    const setProfileAvatarDisplays = (url) => {
        document.querySelectorAll('[data-profile-avatar-display]').forEach((display) => {
            let image = display.querySelector('img');

            if (!image) {
                image = document.createElement('img');
                image.alt = '';
                image.decoding = 'async';
                display.replaceChildren(image);
            }

            image.src = url;
        });
    };

    document.querySelectorAll('[data-account-avatar-form]').forEach((form) => {
        const input = form.querySelector('[data-account-avatar-input]');

        bindOnce(input, 'account-avatar-upload', 'change', async () => {
            const file = input.files?.[0];

            if (!file) {
                return;
            }

            const localPreviewUrl = URL.createObjectURL(file);
            setAvatarPreview(form, localPreviewUrl);
            setAvatarStatus(form, 'Uploading...');

            try {
                const body = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors || {})[0]?.[0];

                    throw new Error(validationMessage || payload.message || 'Avatar upload failed.');
                }

                if (payload.avatar_url) {
                    setAvatarPreview(form, payload.avatar_url);
                    setProfileAvatarDisplays(payload.avatar_url);
                }

                setAvatarStatus(form, payload.message || 'Avatar updated.');
            } catch (error) {
                setAvatarStatus(form, error.message || 'Avatar upload failed.');
            } finally {
                URL.revokeObjectURL(localPreviewUrl);
                input.value = '';
            }
        });
    });

    const accountModalFocusable = (modal) => [...modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.disabled && node.offsetParent !== null);

    const openAccountModal = (id) => {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        focusedBeforeAccountModal = document.activeElement;
        modal.hidden = false;
        modal.removeAttribute('inert');
        document.body.classList.add('has-modal-open');
        accountModalFocusable(modal)[0]?.focus();
    };

    const closeAccountModal = (id) => {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('inert', '');
        document.body.classList.remove('has-modal-open');
        focusedBeforeAccountModal?.focus();
    };

    document.querySelectorAll('[data-account-modal-open]').forEach((button) => {
        bindOnce(button, 'account-modal-open', 'click', () => openAccountModal(button.dataset.accountModalOpen));
    });

    document.querySelectorAll('[data-account-modal-close]').forEach((button) => {
        bindOnce(button, 'account-modal-close', 'click', () => closeAccountModal(button.dataset.accountModalClose));
    });

    document.querySelectorAll('.account-modal-layer').forEach((modal) => {
        bindOnce(modal, 'account-modal-backdrop', 'click', (event) => {
            if (event.target === modal) {
                closeAccountModal(modal.id);
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', initializeAccountHub);

export { initializeAccountHub };
