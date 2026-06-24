const adminSectionThemes = {
    dashboard: 'neutral',
    contenido: 'music',
    editor: 'music',
    biblioteca: 'video',
    productos: 'royal',
    royalpass: 'royal',
    usuarios: 'community',
    comunidad: 'community',
    eventos: 'events',
    puntos: 'community',
    pagos: 'royal',
    notificaciones: 'community',
    equipo: 'neutral',
    historial: 'neutral',
    ajustes: 'neutral',
};

const adminToast = (title, message, type = 'info') => {
    const toast = document.getElementById('toastNotification');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');

    if (!toast || !toastTitle || !toastMessage || !toastIcon) {
        return;
    }

    toastTitle.textContent = title;
    toastMessage.textContent = message;
    toastIcon.textContent = type === 'success' ? '✓' : type === 'danger' ? '!' : 'i';
    toast.dataset.type = type;
    toast.classList.add('is-visible');
    toast.setAttribute('aria-hidden', 'false');

    window.clearTimeout(window.renyAdminToastTimer);
    window.renyAdminToastTimer = window.setTimeout(() => {
        toast.classList.remove('is-visible');
        toast.setAttribute('aria-hidden', 'true');
    }, 3200);
};

const activateAdminSection = (sectionId, updateHash = true) => {
    const target = document.getElementById(`sec-${sectionId}`);

    if (!target) {
        return false;
    }

    document.querySelectorAll('[data-admin-section-panel]').forEach((section) => {
        section.classList.toggle('is-active', section === target);
    });

    document.querySelectorAll('[data-admin-nav]').forEach((link) => {
        link.classList.toggle('is-active', link.dataset.adminNav === sectionId);
    });

    document.querySelectorAll('.ds-main-tab').forEach((tab) => {
        tab.classList.toggle('is-selected', tab.dataset.adminNav === sectionId);
    });

    document.body.dataset.theme = adminSectionThemes[sectionId] || 'neutral';
    document.body.dataset.adminCurrentSection = sectionId;

    if (updateHash) {
        if (sectionId === 'dashboard') {
            history.replaceState(null, '', window.location.pathname);
        } else {
            history.replaceState(null, '', `#${sectionId}`);
        }
    }

    document.getElementById('sidebar')?.classList.remove('is-open');
    document.getElementById('sidebarOverlay')?.classList.remove('is-visible');

    return true;
};

const syncAdminTypeFields = () => {
    const typeSelect = document.querySelector('#content-type');
    const fieldsets = Array.from(document.querySelectorAll('[data-type-fieldset]'));

    if (!typeSelect || !fieldsets.length) {
        return;
    }

    fieldsets.forEach((fieldset) => {
        const isActive = fieldset.dataset.typeFieldset === typeSelect.value;
        fieldset.hidden = !isActive;
        fieldset.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !isActive;
        });
    });
};

const syncAdminPreview = () => {
    const titleField = document.getElementById('postTitle');
    const descField = document.getElementById('postDesc');
    const accessField = document.getElementById('postAccess');
    const titleDisplay = document.getElementById('previewTitleDisplay');
    const descDisplay = document.getElementById('previewDescDisplay');
    const accessDisplay = document.getElementById('previewAccessDisplay');

    if (titleDisplay && titleField) {
        titleDisplay.textContent = titleField.value || 'Titulo de prueba';
    }

    if (descDisplay && descField) {
        descDisplay.textContent = descField.value || 'Descripcion de prueba...';
    }

    if (accessDisplay && accessField) {
        accessDisplay.textContent = accessField.options[accessField.selectedIndex]?.textContent || 'Libre';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('admin-cms-body')) {
        return;
    }

    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar?.classList.toggle('is-open');
            overlay?.classList.toggle('is-visible');
        });
    });

    document.querySelectorAll('[data-admin-close-toast]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('toastNotification')?.classList.remove('is-visible');
        });
    });

    document.querySelectorAll('[data-admin-nav]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const sectionId = link.dataset.adminNav;

            if (sectionId && activateAdminSection(sectionId)) {
                event.preventDefault();
            }
        });
    });

    const hashSection = window.location.hash.replace('#', '');
    if (hashSection) {
        activateAdminSection(hashSection, false);
    } else {
        activateAdminSection(document.body.dataset.adminCurrentSection || 'dashboard', false);
    }

    window.addEventListener('hashchange', () => {
        activateAdminSection(window.location.hash.replace('#', ''), false);
    });

    document.querySelectorAll('[data-admin-filter-scope]').forEach((scope) => {
        scope.querySelectorAll('[data-admin-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                const filter = button.dataset.adminFilter;
                const cardsRoot = scope.nextElementSibling;

                scope.querySelectorAll('[data-admin-filter]').forEach((node) => {
                    node.classList.toggle('is-active', node === button);
                });

                cardsRoot?.querySelectorAll('.content-item').forEach((item) => {
                    item.hidden = filter !== 'todos' && item.dataset.type !== filter;
                });
            });
        });
    });

    document.querySelectorAll('[data-admin-toast]').forEach((button) => {
        button.addEventListener('click', () => {
            const [title, message, type] = button.dataset.adminToast.split('|');
            adminToast(title, message, type || 'info');
        });
    });

    document.querySelector('#content-type')?.addEventListener('change', syncAdminTypeFields);
    syncAdminTypeFields();

    ['postTitle', 'postDesc', 'postAccess'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', syncAdminPreview);
        document.getElementById(id)?.addEventListener('change', syncAdminPreview);
    });
    syncAdminPreview();

    document.querySelectorAll('[data-admin-action-select]').forEach((select) => {
        const syncSchedule = () => {
            const form = select.closest('form');
            const scheduleField = form?.querySelector('[data-admin-schedule-field]');

            if (scheduleField) {
                scheduleField.hidden = select.value !== 'schedule';
            }
        };

        select.addEventListener('change', syncSchedule);
        syncSchedule();
    });

    const notifTitle = document.getElementById('notifTitle');
    const notifMsg = document.getElementById('notifMsg');
    const notifTitlePreview = document.querySelector('[data-admin-notif-title]');
    const notifMsgPreview = document.querySelector('[data-admin-notif-message]');

    const syncNotificationPreview = () => {
        if (notifTitlePreview && notifTitle) {
            notifTitlePreview.textContent = notifTitle.value;
        }

        if (notifMsgPreview && notifMsg) {
            notifMsgPreview.textContent = notifMsg.value;
        }
    };

    notifTitle?.addEventListener('input', syncNotificationPreview);
    notifMsg?.addEventListener('input', syncNotificationPreview);
    document.querySelector('[data-admin-notification-send]')?.addEventListener('click', () => {
        adminToast('Notificacion lista', 'Preview actualizado correctamente.', 'success');
    });
});
