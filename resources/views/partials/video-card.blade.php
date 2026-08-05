@php
    $youtubeId = $video['id'] ?? null;
    $title = $video['title'] ?? 'Untitled video';
    $meta = $video['meta'] ?? 'Video';
    $plainTitle = strip_tags((string) $title);
    $externalUrl = $video['external_url'] ?? ($youtubeId ? "https://www.youtube.com/watch?v={$youtubeId}" : null);
    $playState = $video['play_state'] ?? ($youtubeId ? 'ready' : 'unavailable');
@endphp

<article class="video-card" data-video-state="{{ $playState }}">
    <div class="video-thumb">
        <button
            class="video-load-button"
            type="button"
            data-video-player
            @if ($youtubeId) data-youtube-id="{{ $youtubeId }}" @endif
            @if ($externalUrl) data-youtube-url="{{ $externalUrl }}" @endif
            @if (! empty($video['url'])) data-detail-url="{{ $video['url'] }}" @endif
            data-youtube-title="Reny Renteria - {{ $plainTitle }}"
            data-video-state="{{ $playState }}"
            data-analytics-type="video"
            data-analytics-label="{{ $plainTitle }}"
            aria-label="{{ $youtubeId ? 'Play' : 'Open unavailable video state for' }} Reny Renteria - {{ $plainTitle }}"
        >
            @if ($youtubeId)
                <img
                    src="https://i.ytimg.com/vi/{{ $youtubeId }}/hqdefault.jpg"
                    alt=""
                    loading="lazy"
                    decoding="async"
                >
            @else
                <span class="video-unavailable-thumb">Video unavailable</span>
            @endif
            <span class="video-play-icon" aria-hidden="true"></span>
        </button>
        <noscript>
            @if ($externalUrl)
                <a href="{{ $externalUrl }}" target="_blank" rel="noreferrer">Watch on YouTube</a>
            @elseif (! empty($video['url']))
                <a href="{{ $video['url'] }}">Open details</a>
            @endif
        </noscript>
    </div>
    <h4>{{ $title }}</h4>
    <p>{{ $meta }}</p>
    @if ($externalUrl)
        <a
            class="video-card-external"
            href="{{ $externalUrl }}"
            target="_blank"
            rel="noreferrer"
            data-analytics-type="video"
            data-analytics-label="{{ $plainTitle }}"
        >YouTube</a>
    @elseif ($playState !== 'ready')
        <span class="video-card-state">Unavailable</span>
    @endif
</article>
