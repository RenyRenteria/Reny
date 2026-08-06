const YOUTUBE_PLAYING_STATE = 1;

const createYouTubePlaybackObserver = (onStarted) => {
    let started = false;

    return (state) => {
        if (started || Number(state) !== YOUTUBE_PLAYING_STATE) {
            return false;
        }

        started = true;
        onStarted();

        return true;
    };
};

export {
    createYouTubePlaybackObserver,
    YOUTUBE_PLAYING_STATE,
};
