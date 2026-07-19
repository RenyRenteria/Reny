import {
    analyticsText,
    bindOnce,
    normalizeAnalyticsKey,
    trackElementEvent,
    trackEvent,
} from './analytics.js';

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

const communityCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const communityRequestError = (message, reason = null) => Object.assign(new Error(message), {
    reason: reason || normalizeAnalyticsKey(message),
    userMessage: message,
});

const communityJsonRequest = async (url, { method = 'GET', body = null } = {}) => {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': communityCsrfToken(),
        },
        body: body === null ? null : JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationMessage = Object.values(payload.errors || {})[0]?.[0];
        const fallback = response.status === 401
            ? 'Sign in before using community actions.'
            : response.status === 403
                ? 'Royal Pass required for community actions.'
                : response.status === 419
                    ? 'Refresh this page and try again.'
                    : 'Community action could not be saved. Try again.';

        const error = communityRequestError(validationMessage || payload.message || fallback, payload.message || response.status);
        error.status = response.status;
        throw error;
    }

    return payload;
};

const postCommunityJson = (url, body = {}) => communityJsonRequest(url, {
    method: 'POST',
    body,
});

const setCommunityFormStatus = (form, message = '', isError = false) => {
    const status = form?.querySelector('[data-form-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('is-error', isError);
};

const initializeCommunityToastTriggers = (root = document) => {
    root.querySelectorAll('.community-toast-trigger').forEach((button) => {
        bindOnce(button, 'community-toast-trigger', 'click', () => {
            trackElementEvent(button, 'community_action_clicked', {
                item_type: 'community_action',
                result: 'clicked',
            });
            showCommunityToast(button.dataset.toast || 'Coming soon');
        });
    });
};

const initializeCommunityReactions = (root = document) => {
    root.querySelectorAll('.reaction-button').forEach((button) => {
        bindOnce(button, 'community-reaction', 'click', async () => {
            const countNode = button.querySelector('.reaction-count');
            const currentCount = Number(button.dataset.count || countNode?.textContent || 0);
            const wasReacted = button.classList.contains('is-reacted');
            const nextCount = wasReacted ? currentCount - 1 : currentCount + 1;

            button.dataset.count = String(nextCount);
            button.classList.toggle('is-reacted', !wasReacted);
            button.disabled = true;

            if (countNode) {
                countNode.textContent = String(nextCount);
            }

            try {
                if (button.dataset.endpoint) {
                    await postCommunityJson(button.dataset.endpoint);
                }

                trackElementEvent(button, 'community_like_clicked', {
                    item_type: 'reaction',
                    result: button.classList.contains('is-reacted') ? 'liked' : 'unliked',
                });
            } catch (error) {
                console.error(error);
                button.dataset.count = String(currentCount);
                button.classList.toggle('is-reacted', wasReacted);

                if (countNode) {
                    countNode.textContent = String(currentCount);
                }

                showCommunityToast(error.userMessage || 'Like could not be saved.');
                trackElementEvent(button, 'community_like_clicked', {
                    item_type: 'reaction',
                    reason: error.reason || error.message || 'like_failed',
                    result: 'failed',
                });
            } finally {
                button.disabled = false;
            }
        });
    });
};

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

const initializeCommunityPolls = (root = document) => {
    root.querySelectorAll('[data-community-poll], [data-poll]').forEach((poll) => {
        const options = [...poll.querySelectorAll('.poll-option')];
        const totalNode = poll.querySelector('[data-poll-total]');

        options.forEach((option, selectedIndex) => {
            if (option.tagName !== 'BUTTON') {
                return;
            }

            bindOnce(option, 'community-poll-option', 'click', async () => {
                if (poll.dataset.voted === 'true') {
                    showCommunityToast('You already voted in this poll.');
                    return;
                }

                const previousValues = options.map((currentOption) => ({
                    percent: currentOption.dataset.percent,
                    voted: currentOption.classList.contains('is-voted'),
                    disabled: currentOption.disabled,
                    label: currentOption.querySelector('.poll-option-top strong')?.textContent || '',
                    width: currentOption.querySelector('.poll-meter span')?.style.width || '',
                }));
                const previousVoted = poll.dataset.voted;
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

                options.forEach((currentOption) => {
                    if (currentOption.tagName === 'BUTTON') {
                        currentOption.disabled = true;
                    }
                });
                poll.dataset.voted = 'true';

                try {
                    if (poll.dataset.voteEndpoint) {
                        await postCommunityJson(poll.dataset.voteEndpoint, {
                            option_key: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                            option_label: option.dataset.optionLabel || analyticsText(option),
                        });
                    }

                    if (totalNode) {
                        const currentTotal = Number((totalNode.textContent || '').replace(/[^0-9]/g, '')) || 0;
                        totalNode.textContent = `${currentTotal + 1} total votes`;
                    }

                    showCommunityToast('Vote saved.');
                    trackElementEvent(option, 'community_poll_voted', {
                        item_type: 'poll_option',
                        item_id: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                        result: 'voted',
                    });
                } catch (error) {
                    console.error(error);
                    if (error.status !== 409) {
                        poll.dataset.voted = previousVoted || 'false';
                        options.forEach((currentOption, index) => {
                            const previous = previousValues[index];
                            const percentNode = currentOption.querySelector('.poll-option-top strong');
                            const meter = currentOption.querySelector('.poll-meter span');

                            currentOption.dataset.percent = previous.percent;
                            currentOption.classList.toggle('is-voted', previous.voted);

                            if (currentOption.tagName === 'BUTTON') {
                                currentOption.disabled = previous.disabled;
                            }

                            if (percentNode) {
                                percentNode.textContent = previous.label;
                            }

                            if (meter) {
                                meter.style.width = previous.width;
                            }
                        });
                    }

                    showCommunityToast(error.userMessage || 'Vote could not be saved.');
                    trackElementEvent(option, 'community_poll_voted', {
                        item_type: 'poll_option',
                        item_id: option.dataset.optionKey || normalizeAnalyticsKey(analyticsText(option)),
                        reason: error.reason || error.message || 'vote_failed',
                        result: 'failed',
                    });
                }
            });
        });
    });
};

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

const initializeCommunityMobileTabs = (root = document) => {
    const tabs = [...root.querySelectorAll('[data-community-tab]')];
    const panels = [...root.querySelectorAll('[data-community-panel]')];

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const media = window.matchMedia('(max-width: 860px)');
    let activeTab = tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.communityTab || 'feed';

    const activate = (name) => {
        activeTab = name;

        tabs.forEach((tab) => {
            const isActive = tab.dataset.communityTab === name;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', String(isActive));
            tab.tabIndex = isActive ? 0 : -1;
        });

        panels.forEach((panel) => {
            panel.hidden = media.matches && panel.dataset.communityPanel !== name;
        });
    };

    tabs.forEach((tab, index) => {
        bindOnce(tab, 'community-mobile-tab', 'click', () => activate(tab.dataset.communityTab));
        bindOnce(tab, 'community-mobile-tab-keydown', 'keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const nextTab = tabs[(index + direction + tabs.length) % tabs.length];
            activate(nextTab.dataset.communityTab);
            nextTab.focus();
        });
    });

    const applyViewport = () => {
        if (!document.contains(tabs[0])) {
            media.removeEventListener('change', applyViewport);
            return;
        }

        if (media.matches) {
            activate(activeTab);
        } else {
            panels.forEach((panel) => {
                panel.hidden = false;
            });
        }
    };

    applyViewport();
    media.addEventListener('change', applyViewport);
};

const createLiveChatMessage = (message) => {
    const article = document.createElement('article');
    article.className = 'community-live-message';
    article.dataset.chatMessageId = String(message.id);
    article.dataset.chatUserId = String(message.user_id);
    article.classList.toggle('is-self', Boolean(message.is_self));
    article.classList.toggle('is-host', Boolean(message.is_host));

    const avatar = document.createElement('div');
    avatar.className = 'community-chat-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.textContent = message.initials || 'R';

    const content = document.createElement('div');
    const header = document.createElement('header');
    const author = document.createElement('strong');
    author.textContent = message.author || 'Miembro';
    header.append(author);

    if (message.is_host) {
        const host = document.createElement('span');
        host.textContent = 'Host';
        header.append(host);
    }

    const time = document.createElement('time');
    time.textContent = message.time || 'ahora';
    header.append(time);

    const body = document.createElement('p');
    body.textContent = message.text || '';
    content.append(header, body);

    if (message.block_endpoint || message.moderation_endpoint) {
        const actions = document.createElement('div');
        actions.className = 'community-live-message-actions';

        if (message.block_endpoint) {
            const block = document.createElement('button');
            block.type = 'button';
            block.dataset.chatBlockEndpoint = message.block_endpoint;
            block.textContent = 'Bloquear';
            actions.append(block);
        }

        if (message.moderation_endpoint) {
            const moderate = document.createElement('button');
            moderate.type = 'button';
            moderate.dataset.chatModerateEndpoint = message.moderation_endpoint;
            moderate.textContent = 'Ocultar';
            actions.append(moderate);
        }

        content.append(actions);
    }

    article.append(avatar, content);

    return article;
};

const renderLiveChatMessages = (container, messages) => {
    const signature = messages.map((message) => `${message.id}:${message.text}`).join('|');

    if (container.dataset.messagesSignature === signature) {
        return false;
    }

    const wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    container.replaceChildren();

    if (messages.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'community-chat-empty';
        empty.dataset.liveChatEmpty = '';

        const title = document.createElement('strong');
        title.textContent = 'El chat está listo';
        const copy = document.createElement('p');
        copy.textContent = 'Sé la primera persona en iniciar la conversación.';
        empty.append(title, copy);
        container.append(empty);
    } else {
        messages.forEach((message) => container.append(createLiveChatMessage(message)));
    }

    container.dataset.messagesSignature = signature;

    if (wasNearBottom || !container.dataset.hasRenderedMessages) {
        container.scrollTop = container.scrollHeight;
    }

    container.dataset.hasRenderedMessages = 'true';

    return true;
};

let liveChatPollingTimer = null;

const initializeCommunityLiveChat = (root = document) => {
    window.clearTimeout(liveChatPollingTimer);

    const chat = root.querySelector('[data-community-live-chat]');
    const container = chat?.querySelector('[data-live-chat-messages]');
    const status = chat?.querySelector('[data-live-chat-status]');
    const form = chat?.querySelector('[data-community-live-chat-form]');

    if (!chat || !container || !chat.dataset.messagesEndpoint) {
        return;
    }

    const refresh = async () => {
        if (!document.contains(chat)) {
            return;
        }

        try {
            const payload = await communityJsonRequest(chat.dataset.messagesEndpoint);
            renderLiveChatMessages(container, payload.messages || []);

            if (status) {
                status.textContent = 'Actualización automática · chat moderado';
            }
        } catch (error) {
            console.warn(error);

            if (status) {
                status.textContent = 'Reconectando el chat...';
            }
        } finally {
            if (document.contains(chat)) {
                liveChatPollingTimer = window.setTimeout(refresh, 5000);
            }
        }
    };

    bindOnce(container, 'community-live-chat-actions', 'click', async (event) => {
        const blockButton = event.target.closest('[data-chat-block-endpoint]');
        const moderateButton = event.target.closest('[data-chat-moderate-endpoint]');

        if (!blockButton && !moderateButton) {
            return;
        }

        const button = blockButton || moderateButton;
        button.disabled = true;

        try {
            if (blockButton) {
                const row = blockButton.closest('[data-chat-user-id]');
                const userId = row?.dataset.chatUserId;
                const payload = await postCommunityJson(blockButton.dataset.chatBlockEndpoint);

                container.querySelectorAll(`[data-chat-user-id="${CSS.escape(userId || '')}"]`).forEach((message) => message.remove());
                container.dataset.messagesSignature = '';
                showCommunityToast(payload.message || 'Usuario bloqueado.');
            } else {
                const payload = await communityJsonRequest(moderateButton.dataset.chatModerateEndpoint, { method: 'DELETE' });
                moderateButton.closest('[data-chat-message-id]')?.remove();
                container.dataset.messagesSignature = '';
                showCommunityToast(payload.message || 'Mensaje ocultado.');
            }
        } catch (error) {
            console.error(error);
            showCommunityToast(error.userMessage || 'No pudimos completar la acción.');
        } finally {
            button.disabled = false;
        }
    });

    bindOnce(form, 'community-live-chat-submit', 'submit', async (event) => {
        event.preventDefault();

        const input = form.querySelector('input[name="body"]');
        const body = input?.value.trim();

        if (!body) {
            setCommunityFormStatus(form, 'Escribe un mensaje primero.', true);
            return;
        }

        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;
        setCommunityFormStatus(form, 'Enviando...');

        try {
            const payload = await postCommunityJson(form.dataset.endpoint, { body });
            input.value = '';
            setCommunityFormStatus(form);

            if (payload.chat_message) {
                const currentMessages = [...container.querySelectorAll('[data-chat-message-id]')]
                    .map((message) => Number(message.dataset.chatMessageId));

                if (!currentMessages.includes(Number(payload.chat_message.id))) {
                    container.querySelector('[data-live-chat-empty]')?.remove();
                    container.append(createLiveChatMessage(payload.chat_message));
                    container.scrollTop = container.scrollHeight;
                    container.dataset.messagesSignature = '';
                }
            }

            showCommunityToast(payload.message || 'Mensaje enviado.');
            trackEvent('community_live_chat_message_sent', {
                item_type: 'live_chat_message',
                result: 'submitted',
            });
        } catch (error) {
            console.error(error);
            setCommunityFormStatus(form, error.userMessage || 'No pudimos enviar el mensaje.', true);
            showCommunityToast(error.userMessage || 'No pudimos enviar el mensaje.');
            trackEvent('community_live_chat_message_sent', {
                item_type: 'live_chat_message',
                reason: error.reason || error.message || 'message_failed',
                result: 'failed',
            });
        } finally {
            submit.disabled = false;
        }
    });

    refresh();
};

const initializeCommunityClubLinks = (root = document) => {
    root.querySelectorAll('.club-card a, [data-community-club-open]').forEach((link) => {
        bindOnce(link, 'community-club-open', 'click', () => {
            trackElementEvent(link, 'community_club_opened', {
                item_type: 'country_club',
                item_id: link.closest('[data-club-key]')?.dataset.clubKey || normalizeAnalyticsKey(analyticsText(link)),
                result: 'opened',
            });
        });
    });
};

const initializeCommunityClubJoins = (root = document) => {
    root.querySelectorAll('[data-community-club-join]').forEach((button) => {
        bindOnce(button, 'community-club-join', 'click', async () => {
            if (button.dataset.joined === 'true') {
                showCommunityToast('You already joined this club.');
                return;
            }

            const originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Joining...';

            try {
                const payload = await postCommunityJson(button.dataset.endpoint);
                button.dataset.joined = 'true';
                button.textContent = 'Joined';
                showCommunityToast(payload.message || 'Club joined.');
                trackElementEvent(button, 'community_club_joined', {
                    item_type: 'country_club',
                    item_id: button.dataset.clubKey,
                    result: 'joined',
                });
            } catch (error) {
                console.error(error);
                button.textContent = originalLabel;
                showCommunityToast(error.userMessage || 'Club could not be joined.');
                trackElementEvent(button, 'community_club_joined', {
                    item_type: 'country_club',
                    item_id: button.dataset.clubKey,
                    reason: error.reason || error.message || 'join_failed',
                    result: 'failed',
                });
            } finally {
                button.disabled = false;
            }
        });
    });
};

let previousCreateGroupFocus = null;

const createGroupElements = () => ({
    modal: document.getElementById('createGroupModal'),
    open: document.getElementById('openCreateGroup'),
    close: document.getElementById('closeCreateGroup'),
    form: document.getElementById('createGroupForm'),
    countryName: document.getElementById('createCountryName'),
});

const getCreateGroupFocusable = () => createGroupElements().modal
    ? [...createGroupElements().modal.querySelectorAll('button, input, [href], select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.hasAttribute('disabled'))
    : [];

const closeCreateGroupModal = () => {
    const { modal } = createGroupElements();

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-modal-open');
    previousCreateGroupFocus?.focus();
};

const openCreateGroupModal = () => {
    const { modal, countryName } = createGroupElements();

    if (!modal) {
        return;
    }

    previousCreateGroupFocus = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-modal-open');
    countryName?.focus();

    trackEvent('community_create_club_started', {
        item_type: 'country_club',
        result: 'started',
    });
};

const initializeCreateGroupModal = () => {
    const {
        modal,
        open,
        close,
        form,
    } = createGroupElements();

    bindOnce(open, 'create-group-open', 'click', openCreateGroupModal);
    bindOnce(close, 'create-group-close', 'click', closeCreateGroupModal);

    bindOnce(modal, 'create-group-backdrop', 'click', (event) => {
        if (event.target === modal) {
            closeCreateGroupModal();
        }
    });

    bindOnce(modal, 'create-group-keydown', 'keydown', (event) => {
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

    bindOnce(form, 'create-group-submit', 'submit', (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const country = String(formData.get('name') || '').trim();
        const activity = String(formData.get('activity') || '').trim();

        if (!country || !activity) {
            setCommunityFormStatus(form, 'Add a country and activity.', true);
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        setCommunityFormStatus(form, 'Creating...');

        postCommunityJson(form.dataset.endpoint, {
            name: country,
            activity,
        }).then((payload) => {
            form.reset();
            closeCreateGroupModal();
            showCommunityToast(payload.message || 'Country club created.');
            trackEvent('community_club_created', {
                item_type: 'country_club',
                item_id: payload.club?.key || normalizeAnalyticsKey(country),
                item_label: payload.club?.name || country,
                result: 'created',
            });

            if (payload.club?.detail_url) {
                window.location.assign(payload.club.detail_url);
            }
        }).catch((error) => {
            console.error(error);
            setCommunityFormStatus(form, error.userMessage || 'Country club could not be created.', true);
            showCommunityToast(error.userMessage || 'Country club could not be created.');
            trackEvent('community_club_created', {
                item_type: 'country_club',
                item_id: normalizeAnalyticsKey(country),
                reason: error.reason || error.message || 'create_club_failed',
                result: 'failed',
            });
        }).finally(() => {
            submitButton.disabled = false;
        });
    });
};

const initializeCommunityNotes = (root = document) => {
    root.querySelectorAll('.community-content .media-cta, .community-content .community-post-media-cta').forEach((button) => {
        bindOnce(button, 'community-note-open', 'click', () => {
            if (button.dataset.noteOpen) {
                const noteModal = document.getElementById('communityNoteModal');
                const noteTitle = document.getElementById('communityNoteTitle');
                const noteBody = document.getElementById('communityNoteBody');

                if (noteModal && noteTitle && noteBody) {
                    noteTitle.textContent = button.dataset.noteTitle || 'Reny note';
                    noteBody.textContent = button.dataset.noteBody || '';
                    noteModal.classList.add('is-open');
                    noteModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('has-modal-open');
                }
            }

            trackElementEvent(button, 'community_note_opened', {
                item_type: 'reny_note',
                result: 'opened',
            });
        });
    });

    bindOnce(document.getElementById('closeCommunityNote'), 'community-note-close', 'click', () => {
        const noteModal = document.getElementById('communityNoteModal');

        noteModal?.classList.remove('is-open');
        noteModal?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('has-modal-open');
    });
};

const initializeCommunityShares = (root = document) => {
    root.querySelectorAll('.community-content .share').forEach((button) => {
        bindOnce(button, 'community-share', 'click', async () => {
            const shareUrl = button.dataset.shareUrl || window.location.href;
            const shareTitle = button.dataset.shareTitle || document.title;

            try {
                if (navigator.share) {
                    await navigator.share({
                        title: shareTitle,
                        url: shareUrl,
                    });
                } else if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(shareUrl);
                    showCommunityToast('Link copied.');
                } else {
                    window.prompt('Copy this link', shareUrl);
                }

                trackElementEvent(button, 'community_share_clicked', {
                    item_type: 'post',
                    result: 'shared',
                });
            } catch (error) {
                const canceled = error?.name === 'AbortError';

                trackElementEvent(button, 'community_share_clicked', {
                    item_type: 'post',
                    reason: canceled ? 'share_canceled' : error.message || 'share_failed',
                    result: canceled ? 'canceled' : 'failed',
                });

                if (!canceled) {
                    showCommunityToast('Share failed. Try copying the URL.');
                }
            }
        });
    });
};

const initializeCommunityReplyForms = (root = document) => {
    root.querySelectorAll('[data-community-reply-form]').forEach((form) => {
        bindOnce(form, 'community-reply-submit', 'submit', async (event) => {
            event.preventDefault();

            const input = form.querySelector('input[name="body"]');
            const body = input?.value.trim();

            if (!body) {
                setCommunityFormStatus(form, 'Write a reply first.', true);
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setCommunityFormStatus(form, 'Posting...');

            try {
                const payload = await postCommunityJson(form.dataset.endpoint, { body });
                const countNode = document.querySelector(`[data-reply-count="${form.dataset.postKey}"] span`);
                const currentCount = Number((countNode?.textContent || '').replace(/[^0-9]/g, '')) || 0;

                if (countNode) {
                    countNode.textContent = `${currentCount + 1} respuestas`;
                }

                input.value = '';
                setCommunityFormStatus(form, payload.message || 'Reply posted.');
                showCommunityToast(payload.message || 'Reply posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'post_reply',
                    item_id: form.dataset.postKey,
                    result: 'submitted',
                });
            } catch (error) {
                console.error(error);
                setCommunityFormStatus(form, error.userMessage || 'Reply could not be posted.', true);
                showCommunityToast(error.userMessage || 'Reply could not be posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'post_reply',
                    item_id: form.dataset.postKey,
                    reason: error.reason || error.message || 'reply_failed',
                    result: 'failed',
                });
            } finally {
                submitButton.disabled = false;
            }
        });
    });
};

const initializeCommunityClubMessageForms = (root = document) => {
    root.querySelectorAll('[data-community-club-message]').forEach((form) => {
        bindOnce(form, 'community-club-message-submit', 'submit', async (event) => {
            event.preventDefault();

            const input = form.querySelector('input[name="body"]');
            const body = input?.value.trim();
            const countryChatFeed = document.getElementById('countryChatFeed');

            if (!body || !countryChatFeed) {
                setCommunityFormStatus(form, 'Write a message first.', true);
                return;
            }

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setCommunityFormStatus(form, 'Posting...');

            try {
                const payload = await postCommunityJson(form.dataset.endpoint, { body });
                countryChatFeed.append(renderChatMessage({ author: payload.author || 'You', text: payload.text || body }, true));
                countryChatFeed.scrollTop = countryChatFeed.scrollHeight;
                input.value = '';
                setCommunityFormStatus(form, payload.message || 'Message posted.');
                showCommunityToast(payload.message || 'Message posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'country_club_reply',
                    item_id: form.dataset.clubKey,
                    result: 'submitted',
                });
            } catch (error) {
                console.error(error);
                setCommunityFormStatus(form, error.userMessage || 'Message could not be posted.', true);
                showCommunityToast(error.userMessage || 'Message could not be posted.');
                trackEvent('community_reply_submitted', {
                    item_type: 'country_club_reply',
                    item_id: form.dataset.clubKey,
                    reason: error.reason || error.message || 'club_reply_failed',
                    result: 'failed',
                });
            } finally {
                submitButton.disabled = false;
            }
        });
    });
};

const initializeCommunitySoftPollButtons = (root = document) => {
    root.querySelectorAll('.vote-card .soft-button').forEach((button) => {
        bindOnce(button, 'community-soft-poll', 'click', () => {
            trackElementEvent(button, 'community_poll_voted', {
                item_type: 'poll',
                result: 'clicked',
            });
        });
    });
};

const initializeCommunityInteractions = (root = document) => {
    initializeCommunityMobileTabs(root);
    initializeCommunityLiveChat(root);
    initializeCommunityToastTriggers(root);
    initializeCommunityReactions(root);
    initializeCommunityPolls(root);
    initializeCommunityClubLinks(root);
    initializeCommunityClubJoins(root);
    initializeCreateGroupModal();
    initializeCommunityNotes(root);
    initializeCommunityShares(root);
    initializeCommunityReplyForms(root);
    initializeCommunityClubMessageForms(root);
    initializeCommunitySoftPollButtons(root);
};

export { initializeCommunityInteractions };
