<article class="video-card">
    <div class="video-thumb">
        <iframe
            src="https://www.youtube.com/embed/{{ $video['id'] }}"
            title="Reny Renteria - {!! strip_tags($video['title']) !!}"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen>
        </iframe>
    </div>
    <h4>{!! $video['title'] !!}</h4>
    <p>{!! $video['meta'] !!}</p>
</article>
