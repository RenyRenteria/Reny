export const cloneMusicPlaybackPayload = (payload = {}) => ({
    ...payload,
    queue: Array.isArray(payload.queue) ? payload.queue.map((track) => ({ ...track })) : payload.queue,
    tracks: Array.isArray(payload.tracks) ? [...payload.tracks] : payload.tracks,
});

export const isCacheableMusicPlaybackPayload = (response, payload = {}) => Boolean(
    response?.ok && payload.state === 'ready',
);
