const panelIds = new Set(['music', 'community']);

function activateTab(tabId) {
    const activeTab = panelIds.has(tabId) ? tabId : 'music';

    document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        const isActive = panel.dataset.tabPanel === activeTab;
        panel.hidden = !isActive;
        panel.classList.toggle('is-active', isActive);
    });

    document.querySelectorAll('[data-tab-link]').forEach((link) => {
        const isActive = link.dataset.tabLink === activeTab;
        link.classList.toggle('is-active', isActive);

        if (isActive) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

function tabFromHash() {
    return window.location.hash.replace('#', '');
}

window.addEventListener('hashchange', () => activateTab(tabFromHash()));

document.addEventListener('DOMContentLoaded', () => {
    activateTab(tabFromHash());
});

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

const storeShell = document.querySelector('.store-shell');

if (storeShell) {
    const prices = {
        deluxe: 24,
        singles: 8,
        royal: 4.99,
        merch: 48,
        print: 86,
        concert: 42,
        listening: 18,
        making: 0,
    };
    const storePricesPayload = document.getElementById('storePricesPayload');

    if (storePricesPayload?.textContent) {
        Object.assign(prices, JSON.parse(storePricesPayload.textContent));
    }

    const currencies = {
        usd: { symbol: '$', rate: 1, decimals: 0 },
        eur: { symbol: '€', rate: 0.92, decimals: 0 },
        gbp: { symbol: '£', rate: 0.78, decimals: 0 },
        dop: { symbol: 'RD$', rate: 59, decimals: 0 },
    };

    const products = {};
    let currency = 'usd';
    let bag = [];
    let activeProduct = null;
    let focusedBeforeStoreModal = null;

    const storeToast = document.getElementById('storeToast');
    const bagCount = document.getElementById('bagCount');
    const bagList = document.getElementById('bagList');
    const bagTotal = document.getElementById('bagTotal');
    const paypalButtons = document.getElementById('paypalButtons');
    const paypalStatus = document.getElementById('paypalStatus');
    const tierLabel = document.getElementById('tierLabel');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let paypalButtonsRendered = false;
    let paypalSdkPromise = null;

    document.querySelectorAll('[data-detail]').forEach((button) => {
        products[button.dataset.detail] = {
            name: button.dataset.name,
            type: button.dataset.type,
            priceKey: button.dataset.priceKey,
            availability: button.dataset.availability,
            points: button.dataset.points,
            pass: button.dataset.pass,
            access: button.dataset.access,
            summary: button.dataset.summary,
            image: button.dataset.image,
        };
    });

    document.querySelectorAll('[data-buy][data-event-name]').forEach((button) => {
        products[button.dataset.buy] = {
            name: button.dataset.eventName,
            type: button.dataset.eventType || 'Event',
            priceKey: button.dataset.buy,
            availability: button.dataset.eventAvailability || 'Scheduled',
            points: '+0 pts',
            pass: 'No Royal Pass required',
            access: 'Ticket unlocks in profile',
            summary: button.dataset.eventSummary || 'Scheduled event',
            image: button.dataset.eventImage,
        };
    });

    if (!products.concert) {
        products.concert = {
            name: 'Reny Live - Studio Night',
            type: 'Physical event',
            priceKey: 'concert',
            availability: '96 seats',
            points: '+420 pts',
            pass: 'No Royal Pass required',
            access: 'Ticket unlocks in profile',
            summary: 'Upcoming live concert ticket with instant receipt, profile update, and event access.',
        };
    }

    if (!products.listening) {
        products.listening = {
            name: 'Deluxe Preview Session',
            type: 'Physical event',
            priceKey: 'listening',
            availability: '40 seats',
            points: '+180 pts',
            pass: 'Royal Pass early access',
            access: 'Ticket unlocks in profile',
            summary: 'Intimate listening room preview for the next deluxe release.',
        };
    }

    const money = (value, suffix = '') => {
        const current = currencies[currency];
        const converted = value * current.rate;
        const hasFractionalAmount = Math.abs(converted - Math.round(converted)) > Number.EPSILON;
        const decimals = hasFractionalAmount ? Math.max(current.decimals, 2) : current.decimals;
        const amount = converted.toLocaleString('en-US', {
            maximumFractionDigits: decimals,
            minimumFractionDigits: decimals,
        });

        return `${current.symbol}${amount}${suffix}`;
    };

    const showStoreToast = (message) => {
        if (!storeToast) {
            return;
        }

        storeToast.textContent = message;
        storeToast.classList.add('is-visible');
        window.clearTimeout(showStoreToast.timeout);
        showStoreToast.timeout = window.setTimeout(() => {
            storeToast.classList.remove('is-visible');
        }, 2200);
    };

    const setPayPalStatus = (message) => {
        if (paypalStatus) {
            paypalStatus.textContent = message;
        }
    };

    const checkoutError = (message) => Object.assign(new Error(message), { userMessage: message });

    const checkoutPayload = () => {
        if (!bag.length) {
            throw checkoutError('Add a product first.');
        }

        const identifier = document.getElementById('emailField')?.value?.trim();

        if (!identifier) {
            throw checkoutError('Add email or phone.');
        }

        return {
            identifier,
            product_keys: [...bag],
            currency: currency.toUpperCase(),
        };
    };

    const postCheckoutJson = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors || {})[0]?.[0];

            throw checkoutError(validationMessage || payload.message || 'Checkout failed.');
        }

        return payload;
    };

    const loadPayPalSdk = (clientId) => {
        if (window.paypal?.Buttons) {
            return Promise.resolve(window.paypal);
        }

        if (paypalSdkPromise) {
            return paypalSdkPromise;
        }

        paypalSdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.id = 'paypal-sdk';
            script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(currency.toUpperCase())}&intent=capture`;
            script.async = true;
            script.onload = () => {
                if (window.paypal?.Buttons) {
                    resolve(window.paypal);
                    return;
                }

                reject(checkoutError('PayPal checkout could not load.'));
            };
            script.onerror = () => reject(checkoutError('PayPal checkout could not load.'));
            document.head.append(script);
        });

        return paypalSdkPromise;
    };

    const completeApprovedCheckout = (payload) => {
        if (tierLabel && payload.royal_status === 'royal_active') {
            tierLabel.textContent = 'ROYAL MEMBER';
        }

        bag = [];
        renderBag();
        closeStoreLayer('bagLayer');
        showStoreToast('PayPal confirmed. Hub updated.');

        if (payload.account_url) {
            window.location.assign(payload.account_url);
        }
    };

    const renderPayPalButtons = async () => {
        if (!paypalButtons || paypalButtonsRendered) {
            setPayPalStatus('Use the PayPal button to approve payment.');
            return;
        }

        const clientId = paypalButtons.dataset.paypalClientId;

        if (!clientId) {
            throw checkoutError('PayPal is not configured.');
        }

        setPayPalStatus('Loading PayPal checkout...');
        const paypal = await loadPayPalSdk(clientId);
        paypalButtons.replaceChildren();

        await paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'paypal',
            },
            createOrder: async () => {
                const payload = checkoutPayload();
                setPayPalStatus('Creating PayPal order...');
                const order = await postCheckoutJson(paypalButtons.dataset.createOrderEndpoint, payload);
                setPayPalStatus('Approve payment in PayPal.');

                return order.paypal_order_id;
            },
            onApprove: async (data) => {
                const payload = checkoutPayload();
                setPayPalStatus('Capturing approved PayPal payment...');
                const capture = await postCheckoutJson(paypalButtons.dataset.captureEndpoint, {
                    ...payload,
                    paypal_order_id: data.orderID,
                });

                completeApprovedCheckout(capture);
            },
            onCancel: () => {
                setPayPalStatus('PayPal checkout canceled. No purchase was recorded.');
                showStoreToast('PayPal checkout canceled.');
            },
            onError: (error) => {
                console.error(error);
                setPayPalStatus(error.userMessage || 'PayPal checkout failed. No purchase was recorded.');
                showStoreToast(error.userMessage || 'PayPal checkout failed.');
            },
        }).render(paypalButtons);

        paypalButtonsRendered = true;
        setPayPalStatus('Use the PayPal button to approve payment.');
    };

    const updateStorePrices = () => {
        document.querySelectorAll('[data-price]').forEach((node) => {
            const key = node.dataset.price;
            const suffix = key === 'royal' ? '/mo' : '';

            node.textContent = money(prices[key] || 0, suffix);
        });
    };

    const getStoreFocusable = (layer) => [...layer.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((node) => !node.disabled && node.offsetParent !== null);

    const trapStoreFocus = (layer, event) => {
        const focusable = getStoreFocusable(layer);
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
    };

    const openStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        focusedBeforeStoreModal = document.activeElement;
        layer.hidden = false;
        layer.removeAttribute('inert');
        document.body.classList.add('has-modal-open');
        getStoreFocusable(layer)[0]?.focus();
    };

    const closeStoreLayer = (id) => {
        const layer = document.getElementById(id);

        if (!layer) {
            return;
        }

        layer.hidden = true;
        layer.setAttribute('inert', '');
        document.body.classList.remove('has-modal-open');
        focusedBeforeStoreModal?.focus();
    };

    const renderBag = () => {
        if (!bagCount || !bagList || !bagTotal) {
            return;
        }

        bagCount.textContent = String(bag.length);
        bagList.replaceChildren();

        if (!bag.length) {
            const empty = document.createElement('div');
            empty.className = 'store-bag-item';
            empty.innerHTML = `<span>Bag is empty</span><strong>${money(0)}</strong>`;
            bagList.append(empty);
            bagTotal.textContent = money(0);
            return;
        }

        let total = 0;

        bag.forEach((key) => {
            const product = products[key];

            if (!product) {
                return;
            }

            const priceKey = product.priceKey || key;
            total += prices[priceKey] || 0;

            const item = document.createElement('div');
            item.className = 'store-bag-item';

            const name = document.createElement('span');
            name.textContent = product.name;

            const price = document.createElement('strong');
            price.textContent = money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : '');

            item.append(name, price);
            bagList.append(item);
        });

        bagTotal.textContent = money(total);
    };

    const addToBag = (key) => {
        if (!products[key]) {
            return;
        }

        bag.push(key);
        renderBag();
        showStoreToast(`${products[key].name} added.`);
    };

    const openProductDetail = (key) => {
        const product = products[key];

        if (!product) {
            return;
        }

        activeProduct = key;
        document.getElementById('detailTitle').textContent = product.name;
        document.getElementById('detailText').textContent = product.summary;

        const detailImage = document.getElementById('detailImage');

        if (detailImage && product.image) {
            detailImage.src = product.image;
            detailImage.alt = product.name;
        }

        const grid = document.getElementById('detailGrid');
        const priceKey = product.priceKey || key;

        grid.replaceChildren();
        [
            [money(prices[priceKey] || 0, priceKey === 'royal' ? '/mo' : ''), 'Price'],
            [product.type, 'Type'],
            [product.availability, 'Availability'],
            [product.points, 'Points'],
            [product.pass, 'Royal Pass'],
            [product.access, 'Access'],
        ].forEach(([value, label]) => {
            const cell = document.createElement('div');
            const strong = document.createElement('strong');
            const span = document.createElement('span');

            strong.textContent = value;
            span.textContent = label;
            cell.append(strong, span);
            grid.append(cell);
        });

        document.getElementById('detailBuy').textContent = 'Add to bag';
        openStoreLayer('detailLayer');
    };

    document.querySelectorAll('.currency-button').forEach((button) => {
        button.addEventListener('click', () => {
            currency = button.dataset.currency || 'usd';
            document.querySelectorAll('.currency-button').forEach((node) => {
                node.classList.toggle('is-active', node === button);
            });
            updateStorePrices();
            renderBag();
        });
    });

    document.querySelectorAll('.store-filter').forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.dataset.filter || 'all';

            document.querySelectorAll('.store-filter').forEach((node) => {
                const active = node === button;
                node.classList.toggle('is-active', active);
                node.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            document.querySelectorAll('.store-product-card').forEach((card) => {
                const categories = (card.dataset.category || '').split(' ');
                card.hidden = filter !== 'all' && !categories.includes(filter);
            });
        });
    });

    document.querySelectorAll('[data-detail]').forEach((button) => {
        button.addEventListener('click', () => openProductDetail(button.dataset.detail));
    });

    document.querySelectorAll('[data-buy]').forEach((button) => {
        button.addEventListener('click', () => {
            addToBag(button.dataset.buy);
            openStoreLayer('bagLayer');
        });
    });

    document.querySelectorAll('[data-rsvp]').forEach((button) => {
        button.addEventListener('click', () => {
            showStoreToast(`${button.dataset.rsvp} RSVP saved.`);
        });
    });

    document.getElementById('detailBuy')?.addEventListener('click', () => {
        if (!activeProduct) {
            return;
        }

        addToBag(activeProduct);
        closeStoreLayer('detailLayer');
        openStoreLayer('bagLayer');
    });

    document.getElementById('openBag')?.addEventListener('click', () => {
        renderBag();
        openStoreLayer('bagLayer');
    });

    document.querySelectorAll('.store-payments button').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.store-payments button').forEach((node) => {
                node.classList.toggle('is-active', node === button);
            });
        });
    });

    document.getElementById('completePurchase')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Loading PayPal...';

        try {
            checkoutPayload();
            await renderPayPalButtons();
            showStoreToast('Use PayPal to approve payment.');
        } catch (error) {
            setPayPalStatus(error.userMessage || 'PayPal checkout is unavailable.');
            showStoreToast(error.userMessage || 'PayPal checkout is unavailable.');
        } finally {
            button.disabled = false;
            button.textContent = 'Load PayPal checkout';
        }
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => closeStoreLayer(button.dataset.close));
    });

    document.addEventListener('keydown', (event) => {
        const openLayerId = ['bagLayer', 'detailLayer'].find((id) => {
            const layer = document.getElementById(id);
            return layer && !layer.hidden;
        });

        if (!openLayerId) {
            return;
        }

        const layer = document.getElementById(openLayerId);

        if (event.key === 'Tab') {
            trapStoreFocus(layer, event);
        } else if (event.key === 'Escape') {
            closeStoreLayer(openLayerId);
        }
    });

    updateStorePrices();
    renderBag();
}
