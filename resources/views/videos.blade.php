@php
    $musicVideos = [
        ['id' => 'Ue8orNrHw9s', 'title' => 'I Swear', 'meta' => 'Official Music Video'],
        ['id' => 'mfaOU7LFheE', 'title' => 'Ag&uuml;ita de Coco', 'meta' => 'Video Oficial'],
        ['id' => 'GUISQgRCY44', 'title' => 'Want a man', 'meta' => 'Official Music Video'],
        ['id' => 'w-W-Szxuk_A', 'title' => 'Crossroads', 'meta' => 'Official Music Video'],
        ['id' => 'FBw7qjIngms', 'title' => 'Nada de m&iacute;', 'meta' => 'Official Music Video'],
        ['id' => 'M5rPAEwICrA', 'title' => 'Lay on my shoulder', 'meta' => 'Music Video'],
    ];

    $series = [
        ['id' => 'Ij8QJWR1LP0', 'title' => 'Raspao a Dolar', 'meta' => 'Conversaciones, cultura pop y temas de actualidad con formato de episodio.'],
        ['id' => 'USfTD9rZ3o8', 'title' => 'Studio Sessions', 'meta' => 'Recording sessions, studio clips, rehearsals, and in-progress music moments.'],
    ];

    $performances = [
        ['id' => '6PSCI5m43wk', 'title' => 'Places', 'meta' => 'Live en Tu Ma&ntilde;ana'],
        ['id' => 'PrOrIS-6NvE', 'title' => 'Ag&uuml;ita de Coco', 'meta' => 'En vivo en Tu Ma&ntilde;ana'],
        ['id' => 'q_pDpnIijWY', 'title' => 'Stamina', 'meta' => 'Live at Miss Universe Panama 2024'],
        ['id' => 'sfOvmqQjpu0', 'title' => 'You Better Run', 'meta' => 'Live at Festival de La Rosa Dorada'],
        ['id' => 'Sb0IcEiAPbA', 'title' => 'Touch it', 'meta' => 'Live performance on Tu Ma&ntilde;ana'],
        ['id' => '0Xvjl-s4PwI', 'title' => 'In Your Heart', 'meta' => 'Live Performance Music Video'],
    ];

    $behindTheScenes = [
        ['id' => 'USfTD9rZ3o8', 'title' => 'Wave', 'meta' => 'Studio Recording Session'],
        ['id' => 'pBmyvcI8vtQ', 'title' => 'Places', 'meta' => 'Studio Clip'],
        ['id' => 'cXo8awFAt4s', 'title' => 'Places', 'meta' => 'Dance Rehearsal with Ching'],
        ['id' => '7ujK5dYKF7Q', 'title' => 'Make It Louder', 'meta' => 'Studio Recording Session'],
    ];

    $vlogs = [
        ['id' => 'fA7CVtk0uNw', 'title' => 'Visitando Mas23', 'meta' => 'Panama vlog short'],
        ['id' => 'yz6mBW-BshM', 'title' => 'IA o arte real?', 'meta' => 'Craftmanship by Christian Javier'],
        ['id' => 'fcGJ7aZ39Hw', 'title' => 'New merch out now', 'meta' => '5D Stage update'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Videos | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="videos-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="{{ url('/') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab is-active" href="{{ url('/videos') }}" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="m22 8-6 4 6 4V8Z"></path>
                                <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                            </svg>
                            <span>VIDEOS</span>
                        </a>
                        <a class="tab" href="#photos">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <path d="m21 15-5-5L5 21"></path>
                            </svg>
                            <span>PHOTOS</span>
                        </a>
                        <a class="tab" href="#community">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                            </svg>
                            <span>COMMUNITY</span>
                        </a>
                        <a class="tab" href="#store">
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

                <div class="member-card">
                    <div class="member-avatar" aria-hidden="true"></div>
                    <div>
                        <strong>Alex Carter</strong>
                        <span>VIP MEMBER</span>
                    </div>
                </div>
            </aside>

            <main class="main-content" id="videos">
                <header class="mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <section class="video-hero" aria-label="Featured video">
                    <div class="hero-content">
                        <p class="eyebrow">Video<br>Premiere</p>
                        <h1>Watch<br>Now</h1>
                        <h2>Official Music Videos</h2>
                        <p class="hero-copy">
                            A YouTube-first video hub for Reny Renteria with official releases,
                            live performances, playlists, behind the scenes clips, and vlogs in one place.
                        </p>
                        <p class="hero-link">Streaming from<br>youtube.com/renyrenteriam</p>
                    </div>

                    <div class="featured-video">
                        <div class="video-frame">
                            <iframe
                                src="https://www.youtube.com/embed/Ue8orNrHw9s"
                                title="Reny Renteria - I Swear (Official Music Video)"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen>
                            </iframe>
                        </div>
                        <div class="featured-meta">
                            <div>
                                <strong>Reny Renteria - I Swear (Official Music Video)</strong>
                                <span>Featured YouTube premiere</span>
                            </div>
                            <a class="youtube-pill" href="https://www.youtube.com/watch?v=Ue8orNrHw9s" target="_blank" rel="noreferrer">Open YouTube</a>
                        </div>
                    </div>
                </section>

                <section class="content-section" aria-labelledby="music-videos-title">
                    <div class="section-head">
                        <h3 id="music-videos-title">Music videos</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid">
                        @foreach ($musicVideos as $video)
                            @include('partials.video-card', ['video' => $video])
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="series-title">
                    <div class="section-head">
                        <h3 id="series-title">Series (Playlists)</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid series-grid">
                        @foreach ($series as $playlist)
                            <article class="playlist-card">
                                <div class="playlist-stack" style="--thumb-url: url('https://i.ytimg.com/vi/{{ $playlist['id'] }}/hqdefault.jpg');" aria-hidden="true"></div>
                                <div class="playlist-copy">
                                    <div>
                                        <span>Playlist</span>
                                        <h4>{!! $playlist['title'] !!}</h4>
                                        <p>{!! $playlist['meta'] !!}</p>
                                    </div>
                                    <a class="playlist-link" href="https://www.youtube.com/watch?v={{ $playlist['id'] }}" target="_blank" rel="noreferrer">Start watching</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="performances-title">
                    <div class="section-head">
                        <h3 id="performances-title">Performances videos</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid">
                        @foreach ($performances as $video)
                            @include('partials.video-card', ['video' => $video])
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="behind-title">
                    <div class="section-head">
                        <h3 id="behind-title">Behind the scenes</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid">
                        @foreach ($behindTheScenes as $video)
                            @include('partials.video-card', ['video' => $video])
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="vlogs-title">
                    <div class="section-head">
                        <h3 id="vlogs-title">Vlogs</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid">
                        @foreach ($vlogs as $video)
                            @include('partials.video-card', ['video' => $video])
                        @endforeach
                    </div>
                </section>

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ url('/') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a class="is-active" href="{{ url('/videos') }}" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="m22 8-6 4 6 4V8Z"></path>
                            <rect x="2" y="6" width="14" height="12" rx="2"></rect>
                        </svg>
                        <span class="sr-only">VIDEOS</span>
                    </a>
                    <a href="#photos">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                        </svg>
                        <span class="sr-only">PHOTOS</span>
                    </a>
                    <a href="#community">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                        </svg>
                        <span class="sr-only">COMMUNITY</span>
                    </a>
                    <a href="#store">
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
    </body>
</html>
