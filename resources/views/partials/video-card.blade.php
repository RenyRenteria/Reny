<article class="video-card">
    <div class="video-thumb">
        <button
            class="video-load-button"
            type="button"
            data-youtube-id="{{ $video['id'] }}"
            data-youtube-title="Reny Renteria - {{ e(strip_tags(html_entity_decode($video['title']))) }}"
            aria-label="Play Reny Renteria - {{ e(strip_tags(html_entity_decode($video['title']))) }}"
        >
            <img
                src="https://i.ytimg.com/vi/{{ $video['id'] }}/hqdefault.jpg"
                alt=""
                loading="lazy"
                decoding="async"
            >
            <span class="video-play-icon" aria-hidden="true"></span>
        </button>
        <noscript>
            <a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noreferrer">Watch on YouTube</a>
        </noscript>
    </div>
    <h4>{!! $video['title'] !!}</h4>
    <p>{!! $video['meta'] !!}</p>
</article>
