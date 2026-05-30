@php
    $musicVideos = [
        ['id' => 'Ue8orNrHw9s', 'title' => 'I Swear', 'subtitle' => 'Official Music Video', 'label' => 'Reny Renteria - I Swear (Official Music Video)'],
        ['id' => 'mfaOU7LFheE', 'title' => 'Aguita de Coco', 'subtitle' => 'Video Oficial', 'label' => 'Reny Renteria - Aguita de Coco (Video Oficial)'],
        ['id' => 'GUISQgRCY44', 'title' => 'Want a man', 'subtitle' => 'Official Music Video', 'label' => 'Reny Renteria - Want a man (Official Music Video)'],
        ['id' => '1nuXWDZn_Qw', 'title' => '10th Floor', 'subtitle' => 'Official Music Video', 'label' => 'Reny Renteria - 10th Floor (Official Music Video)'],
        ['id' => 'FBw7qjIngms', 'title' => 'Nada de mi', 'subtitle' => 'Official Music Video', 'label' => 'Reny Renteria - Nada de mi (Official Music Video)'],
        ['id' => 'M5rPAEwICrA', 'title' => 'Lay on my shoulder', 'subtitle' => 'Music Video', 'label' => 'Reny Renteria - Lay on my shoulder (Music Video)'],
    ];

    $playlists = [
        [
            'title' => 'Raspao a Dolar',
            'copy' => 'Conversaciones, cultura pop y temas de actualidad con formato de episodio.',
            'url' => 'https://www.youtube.com/watch?v=Ij8QJWR1LP0',
            'thumb' => 'https://i.ytimg.com/vi/Ij8QJWR1LP0/hqdefault.jpg',
        ],
        [
            'title' => 'Studio Sessions',
            'copy' => 'Recording sessions, studio clips, rehearsals, and in-progress music moments.',
            'url' => 'https://www.youtube.com/watch?v=USfTD9rZ3o8',
            'thumb' => 'https://i.ytimg.com/vi/USfTD9rZ3o8/hqdefault.jpg',
        ],
    ];

    $performanceVideos = [
        ['id' => '6PSCI5m43wk', 'title' => 'Places', 'subtitle' => 'Live en Tu Manana', 'label' => 'Reny Renteria - Places (Live en Tu Manana)'],
        ['id' => 'PrOrIS-6NvE', 'title' => 'Aguita de Coco', 'subtitle' => 'En vivo en Tu Manana', 'label' => 'Reny Renteria - Aguita de Coco (En vivo en Tu Manana)'],
        ['id' => 'q_pDpnIijWY', 'title' => 'Stamina', 'subtitle' => 'Live at Miss Universe Panama 2024', 'label' => 'Reny Renteria - Stamina Live at Miss Universe Panama 2024'],
        ['id' => 'sfOvmqQjpu0', 'title' => 'You Better Run', 'subtitle' => 'Live at Festival de La Rosa Dorada', 'label' => 'Reny Renteria - You Better Run (Live at Festival de La Rosa Dorada 2024)'],
        ['id' => 'Sb0IcEiAPbA', 'title' => 'Touch it', 'subtitle' => 'Live performance on Tu Manana', 'label' => 'Reny Renteria - Touch it (Live performance on Tu Manana)'],
        ['id' => '0Xvjl-s4PwI', 'title' => 'In Your Heart', 'subtitle' => 'Live Performance Music Video', 'label' => 'Reny Renteria - In Your Heart (Live Performance Music Video)'],
    ];

    $behindTheScenes = [
        ['id' => 'USfTD9rZ3o8', 'title' => 'Wave', 'subtitle' => 'Studio Recording Session', 'label' => 'Reny Renteria - Wave (Studio Recording Session)'],
        ['id' => 'pBmyvcI8vtQ', 'title' => 'Places', 'subtitle' => 'Studio Clip', 'label' => 'Reny Renteria - Places (Studio Clip)'],
        ['id' => 'cXo8awFAt4s', 'title' => 'Places', 'subtitle' => 'Dance Rehearsal with Ching', 'label' => 'Reny Renteria - Places Dance Rehearsal with Ching'],
        ['id' => '7ujK5dYKF7Q', 'title' => 'Make It Louder', 'subtitle' => 'Studio Recording Session', 'label' => 'Reny Renteria - Make It Louder (Studio Recording Session)'],
    ];

    $vlogs = [
        ['id' => 'fA7CVtk0uNw', 'title' => 'Visitando Mas23', 'subtitle' => 'Panama vlog short', 'label' => 'Reny Renteria visitando Mas23'],
        ['id' => 'yz6mBW-BshM', 'title' => 'IA o arte real?', 'subtitle' => 'Craftmanship by Christian Javier', 'label' => 'IA o arte real? Craftmanship by Christian Javier'],
        ['id' => 'fcGJ7aZ39Hw', 'title' => 'New merch out now', 'subtitle' => '5D Stage update', 'label' => 'New merch out now on 5D Stage'],
    ];

    $videoSections = [
        ['id' => 'performances-title', 'title' => 'Performances videos', 'items' => $performanceVideos],
        ['id' => 'behind-title', 'title' => 'Behind the scenes', 'items' => $behindTheScenes],
        ['id' => 'vlogs-title', 'title' => 'Vlogs', 'items' => $vlogs],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Videos | {{ config('app.name', 'Reny Renteria') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="videos-shell">
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="/" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <nav class="tabs" aria-label="Main menu">
                        <a class="tab" href="/">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab is-active" href="{{ route('videos') }}" aria-current="page">
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
                        <a class="brand-link" href="/" aria-label="Reny Renteria home">
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
                            <article class="video-card">
                                <div class="video-thumb">
                                    <iframe
                                        src="https://www.youtube.com/embed/{{ $video['id'] }}"
                                        title="{{ $video['label'] }}"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen>
                                    </iframe>
                                </div>
                                <h4>{{ $video['title'] }}</h4>
                                <p>{{ $video['subtitle'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="series-title">
                    <div class="section-head">
                        <h3 id="series-title">Series (Playlists)</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="video-grid series-grid">
                        @foreach ($playlists as $playlist)
                            <article class="playlist-card">
                                <div class="playlist-stack" style="--thumb-url: url('{{ $playlist['thumb'] }}');" aria-hidden="true"></div>
                                <div class="playlist-copy">
                                    <div>
                                        <span>Playlist</span>
                                        <h4>{{ $playlist['title'] }}</h4>
                                        <p>{{ $playlist['copy'] }}</p>
                                    </div>
                                    <a class="playlist-link" href="{{ $playlist['url'] }}" target="_blank" rel="noreferrer">Start watching</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                @foreach ($videoSections as $section)
                    <section class="content-section" aria-labelledby="{{ $section['id'] }}">
                        <div class="section-head">
                            <h3 id="{{ $section['id'] }}">{{ $section['title'] }}</h3>
                            <button class="view-all" type="button">VIEW ALL</button>
                        </div>

                        <div class="video-grid">
                            @foreach ($section['items'] as $video)
                                <article class="video-card">
                                    <div class="video-thumb">
                                        <iframe
                                            src="https://www.youtube.com/embed/{{ $video['id'] }}"
                                            title="{{ $video['label'] }}"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                            allowfullscreen>
                                        </iframe>
                                    </div>
                                    <h4>{{ $video['title'] }}</h4>
                                    <p>{{ $video['subtitle'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="/">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a class="is-active" href="{{ route('videos') }}" aria-current="page">
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
