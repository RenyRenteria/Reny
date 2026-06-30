const createMusicPlayerUiState = (initialState = {}) => {
    let isPlayerBarVisible = Boolean(initialState.isPlayerBarVisible);
    let isQueueVisible = Boolean(initialState.isQueueVisible);

    const snapshot = () => ({
        isPlayerBarVisible,
        isQueueVisible,
    });

    return {
        snapshot,
        setPlayerBarVisible(visible) {
            isPlayerBarVisible = Boolean(visible);

            return snapshot();
        },
        setQueueVisible(visible) {
            isQueueVisible = Boolean(visible);

            return snapshot();
        },
        toggleQueue() {
            isQueueVisible = !isQueueVisible;

            return snapshot();
        },
        handlePlaybackToggle() {
            return snapshot();
        },
        handleShuffleToggle() {
            return snapshot();
        },
    };
};

export { createMusicPlayerUiState };
