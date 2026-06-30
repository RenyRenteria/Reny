<section class="music-player-layer" id="musicPlayerLayer" hidden data-global-music-player aria-label="Music player">
    <audio id="musicPlayerAudio" preload="metadata"></audio>

    <div class="music-player-artwork" id="musicPlayerArtwork" aria-hidden="true"></div>

    <div class="music-player-copy">
        <span id="musicPlayerState">Ready</span>
        <strong id="musicPlayerTitle">Choose a track</strong>
        <p id="musicPlayerMessage">Play any song or album from Music.</p>
    </div>

    <div class="music-player-controls" aria-label="Playback controls">
        <button class="music-player-control" id="musicPlayerShuffle" type="button" aria-label="Turn shuffle on" aria-pressed="false" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 3h5v5"></path>
                <path d="M4 20l17-17"></path>
                <path d="M21 16v5h-5"></path>
                <path d="M15 15l6 6"></path>
                <path d="M4 4l5 5"></path>
            </svg>
        </button>
        <button class="music-player-control" id="musicPlayerPrevious" type="button" aria-label="Previous track" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 5v14"></path>
                <path d="m19 20-11-8 11-8v16Z"></path>
            </svg>
        </button>
        <button class="music-player-toggle" id="musicPlayerToggle" type="button" aria-label="Play or pause" disabled>
            <span id="musicPlayerToggleIcon" aria-hidden="true"></span>
        </button>
        <button class="music-player-control" id="musicPlayerNext" type="button" aria-label="Next track" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 5v14"></path>
                <path d="m5 4 11 8-11 8V4Z"></path>
            </svg>
        </button>
        <button class="music-player-control" id="musicPlayerRepeat" type="button" aria-label="Turn repeat on" aria-pressed="false" data-repeat-mode="off" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m17 2 4 4-4 4"></path>
                <path d="M3 11V9a3 3 0 0 1 3-3h15"></path>
                <path d="m7 22-4-4 4-4"></path>
                <path d="M21 13v2a3 3 0 0 1-3 3H3"></path>
            </svg>
        </button>
        <button class="music-player-control" id="musicPlayerQueueToggle" type="button" aria-label="Show queue" aria-expanded="false" aria-controls="musicPlayerTracks" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 6h13"></path>
                <path d="M8 12h13"></path>
                <path d="M8 18h13"></path>
                <path d="M3 6h.01"></path>
                <path d="M3 12h.01"></path>
                <path d="M3 18h.01"></path>
            </svg>
        </button>
    </div>

    <div class="music-player-progress">
        <span id="musicPlayerCurrentTime">0:00</span>
        <label class="sr-only" for="musicPlayerProgress">Playback progress</label>
        <input id="musicPlayerProgress" type="range" min="0" max="100" step="0.1" value="0" disabled>
        <span id="musicPlayerDuration">0:00</span>
    </div>

    <div class="music-player-actions">
        <a class="music-player-link music-player-primary" id="musicPlayerCta" href="{{ route('store') }}" hidden>Continue</a>
        <button class="music-player-close" type="button" data-music-player-close aria-label="Close player">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
            </svg>
        </button>
    </div>

    <div class="music-player-loading" id="musicPlayerLoading" role="status" aria-live="polite" hidden>
        Loading playback...
    </div>

    <div class="music-player-tracks" id="musicPlayerTracks" hidden></div>
</section>

<button class="music-player-restore" id="musicPlayerRestore" type="button" aria-label="Show music player" hidden>
    <span class="music-player-restore-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
            <path d="M9 18V5l12-2v13"></path>
            <circle cx="6" cy="18" r="3"></circle>
            <circle cx="18" cy="16" r="3"></circle>
        </svg>
    </span>
    <span class="music-player-restore-copy">
        <strong id="musicPlayerRestoreTitle">Music player</strong>
        <span id="musicPlayerRestoreState">Paused</span>
    </span>
</button>
