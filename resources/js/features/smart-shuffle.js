const metadataFields = {
    artist: ['artist', 'artist_name', 'artistName', 'primary_artist', 'primaryArtist'],
    album: ['album', 'album_title', 'albumTitle', 'release_title', 'releaseTitle'],
};

const valueFromFields = (track, fields) => {
    for (const field of fields) {
        const value = String(track?.[field] || '').trim().toLowerCase();

        if (value) {
            return value;
        }
    }

    return '';
};

const identityFromValue = (value) => {
    if (value && typeof value === 'object') {
        return String(value.id || value.key || value.audio_url || value.audioUrl || value.title || '').trim();
    }

    return String(value || '').trim();
};

const trackIdentity = (track) => identityFromValue(track);

const metadataConflicts = (left, right) => {
    if (!left || !right) {
        return false;
    }

    const leftArtist = valueFromFields(left, metadataFields.artist);
    const rightArtist = valueFromFields(right, metadataFields.artist);

    if (leftArtist && rightArtist && leftArtist === rightArtist) {
        return true;
    }

    const leftAlbum = valueFromFields(left, metadataFields.album);
    const rightAlbum = valueFromFields(right, metadataFields.album);

    return Boolean(leftAlbum && rightAlbum && leftAlbum === rightAlbum);
};

const fisherYates = (tracks, rng) => {
    const shuffled = [...tracks];

    for (let index = shuffled.length - 1; index > 0; index -= 1) {
        const swapIndex = Math.floor(rng() * (index + 1));
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
    }

    return shuffled;
};

const spreadMetadataNeighbors = (tracks, previousTrack = null) => {
    const remaining = [...tracks];
    const ordered = [];
    let lastTrack = previousTrack;

    while (remaining.length) {
        const nonConflictingIndex = remaining.findIndex((track) => !metadataConflicts(lastTrack, track));
        const nextIndex = nonConflictingIndex === -1 ? 0 : nonConflictingIndex;
        const [nextTrack] = remaining.splice(nextIndex, 1);

        ordered.push(nextTrack);
        lastTrack = nextTrack;
    }

    return ordered;
};

const normalizedRecentlyPlayed = (recentlyPlayed) => recentlyPlayed
    .map((track) => identityFromValue(track))
    .filter(Boolean);

const uniquePlayableTracks = (tracks) => {
    const seen = new Set();

    return tracks.filter((track) => {
        const identity = trackIdentity(track);

        if (!identity || seen.has(identity)) {
            return false;
        }

        seen.add(identity);
        return true;
    });
};

const smartShuffle = (tracks, recentlyPlayed = [], options = {}) => {
    const playableTracks = uniquePlayableTracks(Array.isArray(tracks) ? tracks : []);
    const rng = typeof options.rng === 'function' ? options.rng : Math.random;
    const lookbackSize = Number.isInteger(options.lookbackSize)
        ? Math.max(options.lookbackSize, 0)
        : Math.min(5, Math.max(playableTracks.length - 1, 0));
    const recentIds = normalizedRecentlyPlayed(Array.isArray(recentlyPlayed) ? recentlyPlayed : []);
    const playedIds = new Set(recentIds);
    const lookbackIds = new Set(recentIds.slice(-lookbackSize));
    const currentTrack = options.currentTrack || null;
    const currentId = trackIdentity(currentTrack);

    let pool = playableTracks.filter((track) => !playedIds.has(trackIdentity(track)));

    if (!pool.length && lookbackIds.size) {
        pool = playableTracks.filter((track) => !lookbackIds.has(trackIdentity(track)));
    }

    if (!pool.length) {
        pool = [...playableTracks];
    }

    if (currentId && pool.length > 1) {
        const withoutCurrent = pool.filter((track) => trackIdentity(track) !== currentId);

        if (withoutCurrent.length) {
            pool = withoutCurrent;
        }
    }

    return spreadMetadataNeighbors(fisherYates(pool, rng), currentTrack);
};

export {
    smartShuffle,
    trackIdentity,
};
