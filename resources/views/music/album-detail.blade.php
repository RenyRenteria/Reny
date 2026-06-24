@php
    $tracks = $album['track_items'] ?? [];
    $albumTitle = $album['title'] ?? 'Album';
    $albumImage = $album['image_url'] ?? asset('images/store/work-in-progress.png');
    $albumMeta = $album['meta'] ?? count($tracks).' tracks';
    $albumProductKey = $album['product_key'] ?? 'deluxe';
    $deluxeUrl = route('store.checkout', ['product' => $albumProductKey]);
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

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab is-active" href="{{ route('music') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="{{ url('/videos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="{{ url('/photos') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab" href="{{ url('/community') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab" href="{{ url('/store') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.5-5h11L19 10"></path>
                                <path d="M6 10v9h12v-9"></path>
                                <path d="M9 19v-5h6v5"></path>
                            </svg>
                            <span>STORE</span>
                        </a>
                    </nav>
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
                                <button
                                    class="album-detail-pill"
                                    type="button"
                                    data-buy="{{ $albumProductKey }}"
                                    data-buy-name="{{ $albumTitle }}"
                                    data-buy-type="Album"
                                    data-buy-summary="{{ $album['summary'] ?? 'Deluxe album checkout' }}"
                                    data-buy-image="{{ $albumImage }}"
                                    data-buy-url="{{ $deluxeUrl }}"
                                >Get Deluxe</button>
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

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a class="is-active" href="{{ route('music') }}" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="{{ url('/videos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="m22 8-6 4 6 4V8Z"></path>
                            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                        </svg>
                        <span class="sr-only">VIDEOS</span>
                    </a>
                    <a href="{{ url('/photos') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="M21 15l-5-5L5 21"></path>
                        </svg>
                        <span class="sr-only">PHOTOS</span>
                    </a>
                    <a href="{{ url('/community') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                        </svg>
                        <span class="sr-only">COMMUNITY</span>
                    </a>
                    <a href="{{ url('/store') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M4 10h16"></path>
                            <path d="M5 10l1.5-5h11L19 10"></path>
                            <path d="M6 10v9h12v-9"></path>
                            <path d="M9 19v-5h6v5"></path>
                        </svg>
                        <span class="sr-only">STORE</span>
                    </a>
                </nav>
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
