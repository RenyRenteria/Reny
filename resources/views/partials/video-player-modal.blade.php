<section class="video-player-layer" id="videoPlayerLayer" hidden inert>
    <div class="video-player-dialog" role="dialog" aria-modal="true" aria-labelledby="videoPlayerTitle">
        <div class="video-player-head">
            <div>
                <span id="videoPlayerState">Loading</span>
                <h2 id="videoPlayerTitle">Preparing video</h2>
            </div>
            <button class="video-player-close" type="button" data-video-player-close aria-label="Close player">Close</button>
        </div>

        <div class="video-player-body">
            <p id="videoPlayerMessage">Loading the selected video.</p>
            <div class="video-player-frame" id="videoPlayerFrame" hidden></div>
            <div class="video-player-error" id="videoPlayerError" role="status" aria-live="polite" hidden>
                Video is not available right now.
            </div>

            <div class="video-player-actions">
                <a class="video-player-link video-player-primary" id="videoPlayerExternal" href="{{ route('videos') }}" target="_blank" rel="noreferrer" hidden>Open YouTube</a>
                <a class="video-player-link" id="videoPlayerDetail" href="{{ route('videos') }}" hidden>Open details</a>
            </div>
        </div>
    </div>
</section>
