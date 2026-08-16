const root = () => document.querySelector('[data-video-cms]');

export const youtubeIdFromUrl = (value) => {
    try {
        const url = new URL(String(value || '').trim());
        const host = url.hostname.toLowerCase();
        let candidate = null;

        if (['youtu.be', 'www.youtu.be'].includes(host)) {
            candidate = url.pathname.split('/').filter(Boolean)[0];
        } else if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)) {
            candidate = url.searchParams.get('v')
                || url.pathname.match(/^\/(?:shorts|embed)\/([^/]+)/)?.[1];
        }

        return /^[A-Za-z0-9_-]{6,20}$/.test(candidate || '') ? candidate : null;
    } catch {
        return null;
    }
};

const activateTab = (cms, name) => {
    cms.querySelectorAll('[data-video-tab]').forEach((tab) => {
        const active = tab.dataset.videoTab === name;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    cms.querySelectorAll('[data-video-panel]').forEach((panel) => {
        panel.classList.toggle('is-active', panel.dataset.videoPanel === name);
    });
};

const initializeTabs = (cms) => {
    cms.querySelectorAll('[data-video-tab]').forEach((tab) => {
        tab.addEventListener('click', () => activateTab(cms, tab.dataset.videoTab));
    });
    cms.querySelectorAll('[data-video-open-tab]').forEach((button) => {
        button.addEventListener('click', () => activateTab(cms, button.dataset.videoOpenTab));
    });
    activateTab(cms, cms.dataset.videoInitialTab || 'catalog');
};

const initializeFilters = (cms) => {
    const search = cms.querySelector('[data-video-search]');
    const category = cms.querySelector('[data-video-category-select]');
    const status = cms.querySelector('[data-video-status-select]');

    if (!search || !category || !status) {
        return;
    }

    const filter = () => {
        const term = search.value.trim().toLowerCase();

        cms.querySelectorAll('[data-video-group]').forEach((group) => {
            const categoryMatches = category.value === 'all' || group.dataset.videoGroup === category.value;
            let visibleRows = 0;

            group.querySelectorAll('[data-video-row]').forEach((row) => {
                const matches = categoryMatches
                    && (status.value === 'all' || row.dataset.videoStatus === status.value)
                    && (!term || row.dataset.videoSearchValue.includes(term));
                row.hidden = !matches;
                visibleRows += matches ? 1 : 0;
            });

            group.hidden = !categoryMatches || visibleRows === 0;
        });
    };

    const selectCategory = (value) => {
        category.value = value;
        cms.querySelectorAll('[data-video-category-filter]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.videoCategoryFilter === value);
        });
        filter();
    };

    cms.querySelectorAll('[data-video-category-filter]').forEach((button) => {
        button.addEventListener('click', () => selectCategory(button.dataset.videoCategoryFilter));
    });
    category.addEventListener('change', () => selectCategory(category.value));
    status.addEventListener('change', filter);
    search.addEventListener('input', filter);
};

export const orderedVideoIds = (cms) => Array.from(
    cms.querySelectorAll('[data-video-sort-list] [data-video-row]'),
    (row) => row.dataset.videoId,
).filter(Boolean);

export const serializeVideoOrder = (cms, form, createElement = (tagName) => document.createElement(tagName)) => {
    form.querySelectorAll('[data-video-order-input]').forEach((input) => input.remove());

    const videoIds = orderedVideoIds(cms);

    videoIds.forEach((videoId) => {
        const input = createElement('input');
        input.type = 'hidden';
        input.name = 'video_ids[]';
        input.value = videoId;
        input.setAttribute('data-video-order-input', '');
        form.append(input);
    });

    return videoIds;
};

const initializeSorting = (cms) => {
    let dragging = null;
    const status = cms.querySelector('[data-video-order-status]');
    const form = cms.querySelector('[data-video-order-form]');

    cms.querySelectorAll('[data-video-sort-list]').forEach((list) => {
        list.querySelectorAll('[data-video-row]').forEach((row) => {
            row.addEventListener('dragstart', () => {
                dragging = row;
                row.classList.add('is-dragging');
            });
            row.addEventListener('dragend', () => {
                row.classList.remove('is-dragging');
                dragging = null;
                if (status) {
                    status.textContent = 'Hay un nuevo orden pendiente de guardar.';
                }
            });
            row.addEventListener('dragover', (event) => {
                event.preventDefault();

                if (!dragging || dragging === row || dragging.parentElement !== list) {
                    return;
                }

                const box = row.getBoundingClientRect();
                row[ event.clientY > box.top + box.height / 2 ? 'after' : 'before' ](dragging);
            });
        });
    });

    form?.addEventListener('submit', () => serializeVideoOrder(cms, form));
};

const initializeForms = (cms) => {
    cms.querySelectorAll('[data-video-content-form]').forEach((form) => {
        const visibility = form.querySelector('[data-video-visibility]');
        const accessTier = form.querySelector('[data-video-access-tier]');
        const syncAccess = () => {
            if (visibility && accessTier) {
                accessTier.value = visibility.value;
            }
        };

        visibility?.addEventListener('change', syncAccess);
        form.addEventListener('submit', syncAccess);
    });

    cms.querySelectorAll('[data-video-destructive-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm('Este contenido dejará de estar disponible en el catálogo. ¿Continuar?')) {
                event.preventDefault();
            }
        });
    });
};

const initializePreviews = (cms) => {
    cms.querySelectorAll('[data-video-panel]').forEach((panel) => {
        const form = panel.querySelector('[data-video-content-form]');
        const preview = panel.querySelector('[data-video-preview]');

        if (!form || !preview) {
            return;
        }

        const sync = () => {
            const id = youtubeIdFromUrl(form.querySelector('[data-video-url]')?.value);
            const image = preview.querySelector('[data-video-preview-image]');
            const placeholder = image?.previousElementSibling;
            const title = form.querySelector('[data-video-title]')?.value.trim() || 'Nuevo contenido';
            const description = form.querySelector('[data-video-description]')?.value.trim() || 'Descripción corta';
            const category = form.querySelector('[data-video-category]');

            if (image) {
                image.hidden = !id;
                image.src = id ? `https://i.ytimg.com/vi/${id}/hqdefault.jpg` : '';
            }
            if (placeholder) {
                placeholder.hidden = Boolean(id);
            }
            preview.querySelector('[data-video-preview-title]').textContent = title;
            preview.querySelector('[data-video-preview-description]').textContent = description;
            if (category) {
                preview.querySelector('[data-video-preview-category]').textContent = category.options[category.selectedIndex]?.text || 'Video';
            }
        };

        form.querySelectorAll('[data-video-url], [data-video-title], [data-video-description], [data-video-category]').forEach((field) => {
            field.addEventListener('input', sync);
            field.addEventListener('change', sync);
        });
        sync();
    });
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const cms = root();

        if (!cms) {
            return;
        }

        initializeTabs(cms);
        initializeFilters(cms);
        initializeSorting(cms);
        initializeForms(cms);
        initializePreviews(cms);
    }, { once: true });
}
