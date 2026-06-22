@php
    $items = $publicCms['items'] ?? [];
    $isAlbums = $section === 'albums';
    $title = $isAlbums ? 'Albums' : 'Singles';
    $deluxeUrl = route('store.checkout', ['product' => 'deluxe']);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} | Reny Renteria</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="music_{{ $section }}">
        <div class="music-shell">
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

            <main class="main-content" id="music-{{ $section }}">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo.png') }}" alt="Reny Renteria">
                        </a>
                    </div>
                </header>

                <section class="content-section music-list-section" aria-labelledby="music-list-title">
                    <div class="section-head">
                        <div>
                            <a class="music-back-link" href="{{ route('music') }}">Music</a>
                            <h1 id="music-list-title">{{ $title }}</h1>
                        </div>
                    </div>

                    @if ($items)
                        @if ($isAlbums)
                            <div class="albums music-list-grid">
                                @foreach ($items as $album)
                                    <article class="album music-item" data-access-state="{{ $album['access_state'] ?? 'ready' }}">
                                        <div
                                            class="cover {{ $album['cover_class'] ?? 'cover-a' }}"
                                            data-title="{{ $album['title'] }}"
                                            @if (! empty($album['image_url'])) style="background-image: url('{{ $album['image_url'] }}'); background-size: cover; background-position: center;" @endif
                                        >
                                            @include('partials.music-play-button', ['item' => $album, 'class' => 'play-button', 'type' => 'album'])
                                        </div>
                                        @if (($album['access_state'] ?? 'ready') !== 'ready')
                                            <span class="music-state-badge">{{ $album['access_label'] }}</span>
                                        @endif
                                        <h4><a href="{{ $album['detail_url'] }}">{{ $album['title'] }}</a></h4>
                                        <p>{{ $album['meta'] }}</p>
                                        <button
                                            class="album-deluxe-button"
                                            type="button"
                                            data-buy="deluxe"
                                            data-buy-name="Deluxe Digital Album"
                                            data-buy-type="Album"
                                            data-buy-summary="Unlock {{ $album['title'] }} and the deluxe music package."
                                            data-buy-image="{{ $album['image_url'] ?? asset('images/store/work-in-progress.png') }}"
                                            data-buy-url="{{ $deluxeUrl }}"
                                            aria-label="Buy Deluxe - {{ $album['title'] }}"
                                        >Buy Deluxe</button>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="singles music-list-grid">
                                @foreach ($items as $single)
                                    <article class="single music-item" data-access-state="{{ $single['access_state'] ?? 'ready' }}">
                                        <div
                                            class="single-art"
                                            aria-hidden="true"
                                            @if (! empty($single['image_url'])) style="background-image: url('{{ $single['image_url'] }}'); background-size: cover; background-position: center;" @endif
                                        ></div>
                                        <div>
                                            <strong><a href="{{ $single['detail_url'] }}">{{ $single['title'] }}</a></strong>
                                            <span>{{ $single['artist'] }}</span>
                                            @if (($single['access_state'] ?? 'ready') !== 'ready')
                                                <em class="music-inline-state">{{ $single['access_label'] }}</em>
                                            @endif
                                        </div>
                                        @include('partials.music-play-button', ['item' => $single, 'class' => 'mini-play', 'type' => 'single'])
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="music-empty-state">
                            <span>Empty</span>
                            <strong>No published {{ strtolower($title) }} yet.</strong>
                        </div>
                    @endif
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
                            <path d="m21 15-5-5L5 21"></path>
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
