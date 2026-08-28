const ANALYTICS_SESSION_TTL_MS = 30 * 60 * 1000;

const VISITOR_STORAGE_KEY = 'reny_analytics_visitor_v2';
const SESSION_STORAGE_KEY = 'reny_analytics_session_v2';

const validAnalyticsId = (value) => typeof value === 'string'
    && /^[A-Za-z0-9._:-]{1,64}$/.test(value);

const normalizeTrafficValue = (value, fallback = null) => {
    const normalized = String(value || '')
        .trim()
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9._-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 120);

    return normalized || fallback;
};

const normalizedHostname = (value) => {
    try {
        return new URL(value).hostname.toLowerCase().replace(/^www\./, '');
    } catch {
        return '';
    }
};

const matchesHostname = (hostname, domains) => domains.some(
    (domain) => hostname === domain || hostname.endsWith(`.${domain}`),
);

const trafficAcquisition = ({ href, referrer, siteHostname }) => {
    let url;

    try {
        url = new URL(href);
    } catch {
        url = new URL('https://renyrenteria.com/');
    }

    const source = normalizeTrafficValue(url.searchParams.get('utm_source'));
    const medium = normalizeTrafficValue(url.searchParams.get('utm_medium'));
    const campaign = normalizeTrafficValue(url.searchParams.get('utm_campaign'));

    if (source || medium || campaign) {
        return {
            source: source || 'campaign',
            medium: medium || 'campaign',
            campaign,
        };
    }

    const referringHostname = normalizedHostname(referrer);
    const currentHostname = String(siteHostname || url.hostname).toLowerCase().replace(/^www\./, '');

    if (!referringHostname || referringHostname === currentHostname) {
        return { source: 'direct', medium: 'none', campaign: null };
    }

    const searchEngines = ['google.com', 'bing.com', 'search.yahoo.com', 'duckduckgo.com', 'ecosia.org'];
    const socialNetworks = [
        'facebook.com',
        'instagram.com',
        'tiktok.com',
        'youtube.com',
        'x.com',
        'twitter.com',
        'threads.net',
    ];

    if (matchesHostname(referringHostname, searchEngines)) {
        return {
            source: referringHostname.split('.')[0] === 'search' ? 'yahoo' : referringHostname.split('.')[0],
            medium: 'organic',
            campaign: null,
        };
    }

    if (matchesHostname(referringHostname, socialNetworks)) {
        return {
            source: referringHostname.split('.')[0],
            medium: 'social',
            campaign: null,
        };
    }

    return {
        source: normalizeTrafficValue(referringHostname, 'referral'),
        medium: 'referral',
        campaign: null,
    };
};

const createAnalyticsIdentity = ({
    storage,
    createId,
    href,
    referrer,
    siteHostname,
    now = () => Date.now(),
}) => {
    let memoryVisitorId = null;
    let memorySession = null;

    const read = (key) => {
        try {
            return storage?.getItem(key) || null;
        } catch {
            return null;
        }
    };

    const write = (key, value) => {
        try {
            storage?.setItem(key, value);
        } catch {
            // Memory fallbacks keep analytics functional when browser storage is unavailable.
        }
    };

    const visitorId = () => {
        const stored = read(VISITOR_STORAGE_KEY);

        if (validAnalyticsId(stored)) {
            memoryVisitorId = stored;
            return stored;
        }

        memoryVisitorId ||= createId();
        write(VISITOR_STORAGE_KEY, memoryVisitorId);

        return memoryVisitorId;
    };

    const existingSession = () => {
        try {
            return JSON.parse(read(SESSION_STORAGE_KEY) || 'null');
        } catch {
            return null;
        }
    };

    const sessionContext = () => {
        const timestamp = Number(now());
        const currentVisitorId = visitorId();
        const stored = existingSession() || memorySession;
        const age = timestamp - Number(stored?.last_seen_at);
        const incomingAcquisition = trafficAcquisition({ href, referrer, siteHostname });
        const attributionChanged = incomingAcquisition.medium !== 'none'
            && (
                incomingAcquisition.source !== stored?.acquisition?.source
                || incomingAcquisition.medium !== stored?.acquisition?.medium
                || incomingAcquisition.campaign !== stored?.acquisition?.campaign
            );
        const canResume = validAnalyticsId(stored?.id)
            && Number.isFinite(age)
            && age >= 0
            && age <= ANALYTICS_SESSION_TTL_MS
            && !attributionChanged;
        const session = canResume ? stored : {
            id: createId(),
            started_at: timestamp,
            acquisition: incomingAcquisition,
        };

        session.last_seen_at = timestamp;
        memorySession = session;
        write(SESSION_STORAGE_KEY, JSON.stringify(session));

        return {
            visitor_id: currentVisitorId,
            session_id: session.id,
            traffic_source: normalizeTrafficValue(session.acquisition?.source, 'direct'),
            traffic_medium: normalizeTrafficValue(session.acquisition?.medium, 'none'),
            traffic_campaign: normalizeTrafficValue(session.acquisition?.campaign),
        };
    };

    return {
        sessionContext,
        sessionId: () => sessionContext().session_id,
        visitorId,
    };
};

export {
    ANALYTICS_SESSION_TTL_MS,
    createAnalyticsIdentity,
    normalizeTrafficValue,
    trafficAcquisition,
};
