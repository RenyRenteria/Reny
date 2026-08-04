@php
    $tracks = $album['track_items'] ?? [];
    $albumTitle = $album['title'] ?? 'Album';
    $albumImage = $album['image_url'] ?? asset('images/store/work-in-progress.png');
    $albumMeta = $album['meta'] ?? count($tracks).' tracks';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $albumTitle }} | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="album_detail">
        <div class="music-shell album-detail-shell" data-public-page-root>
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                    </a>

                    <x-public-navigation active="music" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content album-detail-main" id="album-detail">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>

                <section class="album-detail-view" aria-labelledby="album-detail-title">
                    <a class="music-back-link" href="{{ route('music.albums') }}">Albums</a>

                    <div class="album-detail-layout">
                        <button
                            class="album-detail-cover-button"
                            type="button"
                            @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$albumTitle} cover"])
                        >
                            <img src="{{ $albumImage }}" alt="{{ $album['image_alt'] ?? $albumTitle }}" loading="eager" decoding="async">
                        </button>

                        <div class="album-detail-content">
                            <div class="album-detail-heading">
                                <h1 id="album-detail-title">{{ $albumTitle }}</h1>
                                <p>Album &bull; {{ $albumMeta }}</p>
                            </div>

                            <div class="album-detail-actions">
                                <button
                                    class="album-detail-pill"
                                    type="button"
                                    @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$albumTitle}"])
                                >Play</button>
                            </div>

                            <div class="album-track-list" aria-label="{{ $albumTitle }} songs">
                                @forelse ($tracks as $track)
                                    <article class="album-track-row music-item" data-access-state="{{ $track['access_state'] ?? 'ready' }}">
                                        <img src="{{ $track['image_url'] ?: $albumImage }}" alt="" loading="lazy" decoding="async">
                                        <div class="album-track-copy">
                                            <span>{{ str_pad((string) ($track['number'] ?? $loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                                            <strong>{{ $track['title'] }}</strong>
                                        </div>
                                        @include('partials.music-play-button', ['item' => $track, 'class' => 'album-track-play-button', 'type' => 'track'])
                                    </article>
                                @empty
                                    <div class="music-empty-state album-track-empty">
                                        <span>Empty</span>
                                        <strong>No published tracks yet.</strong>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>

                <x-public-navigation active="music" mobile />
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
