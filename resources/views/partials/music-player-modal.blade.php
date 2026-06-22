<section class="music-player-layer" id="musicPlayerLayer" hidden data-global-music-player aria-label="Music player">
    <audio id="musicPlayerAudio" preload="none"></audio>

    <div class="music-player-artwork" id="musicPlayerArtwork" aria-hidden="true"></div>

    <div class="music-player-copy">
        <span id="musicPlayerState">Ready</span>
        <strong id="musicPlayerTitle">Choose a track</strong>
        <p id="musicPlayerMessage">Play any song or album from Music.</p>
    </div>

    <button class="music-player-toggle" id="musicPlayerToggle" type="button" aria-label="Play or pause" disabled>
        <span id="musicPlayerToggleIcon" aria-hidden="true"></span>
    </button>

    <div class="music-player-progress">
        <span id="musicPlayerCurrentTime">0:00</span>
        <label class="sr-only" for="musicPlayerProgress">Playback progress</label>
        <input id="musicPlayerProgress" type="range" min="0" max="100" step="0.1" value="0" disabled>
        <span id="musicPlayerDuration">0:00</span>
    </div>

    <div class="music-player-actions">
        <a class="music-player-link" id="musicPlayerDetail" href="{{ route('music') }}">Details</a>
        <a class="music-player-link music-player-primary" id="musicPlayerCta" href="{{ route('store') }}" hidden>Continue</a>
        <button class="music-player-close" type="button" data-music-player-close aria-label="Close player">Close</button>
    </div>

    <div class="music-player-loading" id="musicPlayerLoading" role="status" aria-live="polite" hidden>
        Loading playback...
    </div>

    <div class="music-player-tracks" id="musicPlayerTracks" hidden></div>
</section>
