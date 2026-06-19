<section class="music-player-layer" id="musicPlayerLayer" hidden inert>
    <div class="music-player-dialog" role="dialog" aria-modal="true" aria-labelledby="musicPlayerTitle">
        <div class="music-player-head">
            <div>
                <span id="musicPlayerState">Loading</span>
                <h2 id="musicPlayerTitle">Preparing playback</h2>
            </div>
            <button class="music-player-close" type="button" data-music-player-close aria-label="Close player">Close</button>
        </div>

        <div class="music-player-body">
            <p id="musicPlayerMessage">Checking access and audio availability.</p>

            <audio id="musicPlayerAudio" controls preload="none" hidden></audio>

            <div class="music-player-loading" id="musicPlayerLoading" role="status" aria-live="polite">
                Loading playback...
            </div>

            <div class="music-player-tracks" id="musicPlayerTracks" hidden></div>

            <div class="music-player-actions">
                <a class="music-player-link" id="musicPlayerDetail" href="{{ route('home') }}">Open details</a>
                <a class="music-player-link music-player-primary" id="musicPlayerCta" href="{{ route('store') }}" hidden>Continue</a>
            </div>
        </div>
    </div>
</section>
