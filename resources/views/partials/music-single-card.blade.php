@php
    $single = $single ?? $item ?? [];
    $title = $single['title'] ?? 'Single';
    $detailUrl = $single['detail_url'] ?? null;
    $accessState = $single['access_state'] ?? 'ready';
@endphp

<article class="single music-item" data-access-state="{{ $accessState }}">
    <div
        class="single-art"
        aria-hidden="true"
        @if (! empty($single['image_url'])) style="background-image: url('{{ $single['image_url'] }}'); background-size: cover; background-position: center;" @endif
    ></div>
    <div>
        <strong>
            @if (filled($detailUrl))
                <a href="{{ $detailUrl }}">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </strong>
        @if ($accessState !== 'ready' && filled($single['access_label'] ?? null))
            <em class="music-inline-state">{{ $single['access_label'] }}</em>
        @endif
    </div>
    @include('partials.music-play-button', ['item' => $single, 'class' => 'mini-play', 'type' => 'single'])
</article>
