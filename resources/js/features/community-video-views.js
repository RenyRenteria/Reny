const COMMUNITY_VIDEO_VIEW_THRESHOLD_MS = 3000;

const createPlaybackThresholdTracker = ({
    thresholdMs = COMMUNITY_VIDEO_VIEW_THRESHOLD_MS,
    now = () => performance.now(),
    schedule = (callback, delay) => window.setTimeout(callback, delay),
    cancel = (timer) => window.clearTimeout(timer),
    onQualified = () => {},
} = {}) => {
    let accumulatedMs = 0;
    let startedAt = null;
    let timer = null;
    let qualified = false;

    const clearScheduled = () => {
        if (timer !== null) {
            cancel(timer);
            timer = null;
        }
    };

    const captureElapsed = () => {
        if (startedAt === null) {
            return;
        }

        const currentTime = now();
        accumulatedMs += Math.max(0, currentTime - startedAt);
        startedAt = currentTime;
    };

    const qualifyIfReady = () => {
        if (qualified || accumulatedMs < thresholdMs) {
            return false;
        }

        qualified = true;
        clearScheduled();
        onQualified();

        return true;
    };

    const scheduleQualification = () => {
        clearScheduled();

        if (qualified || startedAt === null) {
            return;
        }

        timer = schedule(() => {
            timer = null;
            captureElapsed();

            if (!qualifyIfReady()) {
                scheduleQualification();
            }
        }, Math.max(0, thresholdMs - accumulatedMs));
    };

    return {
        start() {
            if (qualified || startedAt !== null) {
                return;
            }

            startedAt = now();
            scheduleQualification();
        },
        stop() {
            captureElapsed();
            startedAt = null;
            clearScheduled();
            qualifyIfReady();
        },
        reset() {
            accumulatedMs = 0;
            startedAt = null;
            qualified = false;
            clearScheduled();
        },
        destroy() {
            startedAt = null;
            clearScheduled();
        },
        snapshot() {
            return { accumulatedMs, qualified, playing: startedAt !== null };
        },
    };
};

export {
    COMMUNITY_VIDEO_VIEW_THRESHOLD_MS,
    createPlaybackThresholdTracker,
};
