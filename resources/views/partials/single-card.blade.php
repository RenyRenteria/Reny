<article class="single">
    <div
        class="single-art"
        @if (! empty($single['art_url'])) style="background-image: url('{{ $single['art_url'] }}')" @endif
        aria-hidden="true"
    ></div>
    <div>
        <strong>{{ $single['title'] }}</strong>
        <span>{{ $single['summary'] ?? $single['artist'] ?? 'Reny Renteria' }}</span>
    </div>
    <button class="mini-play" type="button" aria-label="Play {{ $single['title'] }}"><span></span></button>
</article>
