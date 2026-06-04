document.querySelectorAll('.video-load-button').forEach((button) => {
    button.addEventListener('click', () => {
        const youtubeId = button.dataset.youtubeId;

        if (!youtubeId) {
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube.com/embed/${youtubeId}?autoplay=1`;
        iframe.title = button.dataset.youtubeTitle || 'Reny Renteria YouTube video';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;

        button.replaceWith(iframe);
    }, { once: true });
});

const photoTiles = document.querySelectorAll('.photo-tile');
const photoLightbox = document.getElementById('photoLightbox');
const photoLightboxImage = document.getElementById('photoLightboxImage');
const photoLightboxType = document.getElementById('photoLightboxType');
const photoLightboxTitle = document.getElementById('photoLightboxTitle');
const photoLightboxCaption = document.getElementById('photoLightboxCaption');
const photoLightboxClose = document.getElementById('photoLightboxClose');
let activePhotoTile = null;

const closePhotoLightbox = () => {
    if (!photoLightbox || !photoLightboxImage) {
        return;
    }

    photoLightbox.classList.remove('is-open');
    photoLightbox.setAttribute('aria-hidden', 'true');
    photoLightboxImage.removeAttribute('src');

    if (activePhotoTile) {
        activePhotoTile.focus();
    }
};

const openPhotoLightbox = (tile) => {
    if (!photoLightbox || !photoLightboxImage || !photoLightboxType || !photoLightboxTitle || !photoLightboxCaption) {
        return;
    }

    activePhotoTile = tile;
    photoLightboxImage.src = tile.dataset.photoSrc;
    photoLightboxImage.alt = tile.dataset.photoTitle || '';
    photoLightboxType.textContent = `${tile.dataset.photoType || 'Photo'} / ${tile.dataset.photoTone || 'gallery'}`;
    photoLightboxTitle.textContent = tile.dataset.photoTitle || 'Photo';
    photoLightboxCaption.textContent = tile.dataset.photoCaption || '';
    photoLightbox.classList.add('is-open');
    photoLightbox.setAttribute('aria-hidden', 'false');
    photoLightboxClose?.focus();
};

photoTiles.forEach((tile) => {
    tile.addEventListener('click', () => {
        const usesTouch = window.matchMedia('(hover: none)').matches;

        if (usesTouch && !tile.classList.contains('is-peeking')) {
            photoTiles.forEach((otherTile) => otherTile.classList.remove('is-peeking'));
            tile.classList.add('is-peeking');
            return;
        }

        openPhotoLightbox(tile);
    });
});

photoLightboxClose?.addEventListener('click', closePhotoLightbox);

photoLightbox?.addEventListener('click', (event) => {
    if (event.target === photoLightbox) {
        closePhotoLightbox();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !photoLightbox?.classList.contains('is-open')) {
        return;
    }

    closePhotoLightbox();
});

const showCommunityToast = (message) => {
    const toast = document.getElementById('communityToast');

    if (!toast) {
        return;
    }

    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(showCommunityToast.timeout);
    showCommunityToast.timeout = window.setTimeout(() => {
        toast.classList.remove('is-visible');
    }, 1800);
};

document.querySelectorAll('.community-toast-trigger').forEach((button) => {
    button.addEventListener('click', () => {
        showCommunityToast(button.dataset.toast || 'Coming soon');
    });
});

document.querySelectorAll('.reaction-button').forEach((button) => {
    button.addEventListener('click', () => {
        const countNode = button.querySelector('.reaction-count');
        const currentCount = Number(button.dataset.count || countNode?.textContent || 0);
        const nextCount = button.classList.contains('is-reacted') ? currentCount - 1 : currentCount + 1;

        button.dataset.count = String(nextCount);
        button.classList.toggle('is-reacted');

        if (countNode) {
            countNode.textContent = String(nextCount);
        }
    });
});

const normalizePollValues = (values) => {
    const total = values.reduce((sum, value) => sum + value, 0) || 1;
    const exactValues = values.map((value) => (value / total) * 100);
    const roundedValues = exactValues.map(Math.floor);
    let remainder = 100 - roundedValues.reduce((sum, value) => sum + value, 0);

    exactValues
        .map((value, index) => ({ index, fraction: value - Math.floor(value) }))
        .sort((a, b) => b.fraction - a.fraction)
        .forEach(({ index }) => {
            if (remainder <= 0) {
                return;
            }

            roundedValues[index] += 1;
            remainder -= 1;
        });

    return roundedValues;
};

document.querySelectorAll('[data-poll]').forEach((poll) => {
    const options = [...poll.querySelectorAll('.poll-option')];

    options.forEach((option, selectedIndex) => {
        option.addEventListener('click', () => {
            const boostedValues = options.map((currentOption, index) => {
                const currentPercent = Number(currentOption.dataset.percent || 0);
                return index === selectedIndex ? currentPercent + 8 : Math.max(1, currentPercent - 4);
            });
            const nextValues = normalizePollValues(boostedValues);

            options.forEach((currentOption, index) => {
                const value = nextValues[index];
                const percentNode = currentOption.querySelector('.poll-option-top strong');
                const meter = currentOption.querySelector('.poll-meter span');

                currentOption.dataset.percent = String(value);
                currentOption.classList.toggle('is-voted', index === selectedIndex);

                if (percentNode) {
                    percentNode.textContent = `${value}%`;
                }

                if (meter) {
                    meter.style.width = `${value}%`;
                }
            });
        });
    });
});

const groupTabs = document.querySelector('.country-groups-list');
const countryName = document.getElementById('countryName');
const countryMembers = document.getElementById('countryMembers');
const countryActivity = document.getElementById('countryActivity');
const countryChatFeed = document.getElementById('countryChatFeed');

const renderChatMessage = (message, isSelf = false) => {
    const article = document.createElement('article');
    article.className = `chat-message${isSelf ? ' is-self' : ''}`;

    const author = document.createElement('strong');
    author.textContent = message.author;

    const text = document.createElement('p');
    text.textContent = message.text;

    article.append(author, text);

    return article;
};

const getCountryTabs = () => [...document.querySelectorAll('.country-group-tab')];

const selectCountryGroup = (tab) => {
    if (!tab || !countryName || !countryMembers || !countryActivity || !countryChatFeed) {
        return;
    }

    getCountryTabs().forEach((currentTab) => {
        const isSelected = currentTab === tab;
        currentTab.classList.toggle('is-active', isSelected);
        currentTab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        currentTab.tabIndex = isSelected ? 0 : -1;
    });

    countryName.textContent = tab.dataset.country || 'Country group';
    countryMembers.textContent = `${tab.dataset.members || '1'} members`;
    countryActivity.textContent = tab.dataset.activity || 'New custom country group';
    countryChatFeed.replaceChildren();

    JSON.parse(tab.dataset.messages || '[]').forEach((message) => {
        countryChatFeed.append(renderChatMessage(message));
    });
};

groupTabs?.addEventListener('click', (event) => {
    const tab = event.target.closest('.country-group-tab');

    if (tab) {
        selectCountryGroup(tab);
    }
});

groupTabs?.addEventListener('keydown', (event) => {
    if (!['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
        return;
    }

    const tabs = getCountryTabs();
    const currentIndex = tabs.indexOf(document.activeElement);

    if (currentIndex === -1) {
        return;
    }

    event.preventDefault();

    let nextIndex = currentIndex;

    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        nextIndex = (currentIndex + 1) % tabs.length;
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (event.key === 'Home') {
        nextIndex = 0;
    } else if (event.key === 'End') {
        nextIndex = tabs.length - 1;
    }

    tabs[nextIndex]?.focus();
    selectCountryGroup(tabs[nextIndex]);
});

document.getElementById('countryReplyForm')?.addEventListener('submit', (event) => {
    event.preventDefault();

    const input = document.getElementById('countryReplyInput');
    const text = input?.value.trim();

    if (!text || !countryChatFeed) {
        return;
    }

    countryChatFeed.append(renderChatMessage({ author: 'You', text }, true));
    countryChatFeed.scrollTop = countryChatFeed.scrollHeight;
    input.value = '';
});

const createGroupModal = document.getElementById('createGroupModal');
const openCreateGroup = document.getElementById('openCreateGroup');
const closeCreateGroup = document.getElementById('closeCreateGroup');
const createGroupForm = document.getElementById('createGroupForm');
const createCountryName = document.getElementById('createCountryName');
let previousCreateGroupFocus = null;

const getCreateGroupFocusable = () => createGroupModal
    ? [...createGroupModal.querySelectorAll('button, input, [href], select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.hasAttribute('disabled'))
    : [];

const closeCreateGroupModal = () => {
    if (!createGroupModal) {
        return;
    }

    createGroupModal.classList.remove('is-open');
    createGroupModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-modal-open');
    previousCreateGroupFocus?.focus();
};

const openCreateGroupModal = () => {
    if (!createGroupModal) {
        return;
    }

    previousCreateGroupFocus = document.activeElement;
    createGroupModal.classList.add('is-open');
    createGroupModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal-open');
    createCountryName?.focus();
};

openCreateGroup?.addEventListener('click', openCreateGroupModal);
closeCreateGroup?.addEventListener('click', closeCreateGroupModal);

createGroupModal?.addEventListener('click', (event) => {
    if (event.target === createGroupModal) {
        closeCreateGroupModal();
    }
});

createGroupModal?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeCreateGroupModal();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = getCreateGroupFocusable();
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
        return;
    }

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

createGroupForm?.addEventListener('submit', (event) => {
    event.preventDefault();

    const formData = new FormData(createGroupForm);
    const country = String(formData.get('country') || '').trim();
    const activity = String(formData.get('activity') || '').trim();

    if (!country || !activity || !groupTabs || !openCreateGroup) {
        return;
    }

    const tab = document.createElement('button');
    const messages = [{ author: 'System', text: `${country} group created. Start the first thread.` }];

    tab.className = 'country-group-tab';
    tab.type = 'button';
    tab.role = 'tab';
    tab.setAttribute('aria-selected', 'false');
    tab.setAttribute('aria-controls', 'country-panel');
    tab.tabIndex = -1;
    tab.dataset.country = country;
    tab.dataset.members = '1';
    tab.dataset.activity = activity;
    tab.dataset.messages = JSON.stringify(messages);

    const title = document.createElement('strong');
    title.textContent = country;

    const members = document.createElement('span');
    members.textContent = '1 member';

    tab.append(title, members);
    groupTabs.insertBefore(tab, openCreateGroup);
    createGroupForm.reset();
    closeCreateGroupModal();
    selectCountryGroup(tab);
    tab.focus();
});
