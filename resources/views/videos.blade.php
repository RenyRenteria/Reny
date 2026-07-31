@php
    $videoSections = [
        'music_videos' => [
            'title' => 'Music videos',
            'heading_id' => 'music-videos-title',
            'empty' => 'No music videos published yet.',
        ],
        'series' => [
            'title' => 'Series (Playlists)',
            'heading_id' => 'series-title',
            'empty' => 'No video series published yet.',
        ],
        'performances' => [
            'title' => 'Performances videos',
            'heading_id' => 'performances-title',
            'empty' => 'No performance videos published yet.',
        ],
        'behind_the_scenes' => [
            'title' => 'Behind the scenes',
            'heading_id' => 'behind-title',
            'empty' => 'No behind the scenes videos published yet.',
        ],
        'vlogs' => [
            'title' => 'Vlogs',
            'heading_id' => 'vlogs-title',
            'empty' => 'No vlogs published yet.',
        ],
    ];

    $staticVideoGroups = [
        'music_videos' => [
            ['id' => 'Ue8orNrHw9s', 'title' => 'I Swear', 'meta' => 'Official Music Video'],
            ['id' => 'mfaOU7LFheE', 'title' => 'Ag&uuml;ita de Coco', 'meta' => 'Video Oficial'],
            ['id' => 'GUISQgRCY44', 'title' => 'Want a man', 'meta' => 'Official Music Video'],
            ['id' => 'w-W-Szxuk_A', 'title' => 'Crossroads', 'meta' => 'Official Music Video'],
            ['id' => 'FBw7qjIngms', 'title' => 'Nada de m&iacute;', 'meta' => 'Official Music Video'],
            ['id' => 'M5rPAEwICrA', 'title' => 'Lay on my shoulder', 'meta' => 'Music Video'],
        ],
        'series' => [
            ['id' => 'Ij8QJWR1LP0', 'title' => 'Raspao a Dolar', 'meta' => 'Conversaciones, cultura pop y temas de actualidad con formato de episodio.'],
            ['id' => 'USfTD9rZ3o8', 'title' => 'Studio Sessions', 'meta' => 'Recording sessions, studio clips, rehearsals, and in-progress music moments.'],
        ],
        'performances' => [
            ['id' => '6PSCI5m43wk', 'title' => 'Places', 'meta' => 'Live en Tu Ma&ntilde;ana'],
            ['id' => 'PrOrIS-6NvE', 'title' => 'Ag&uuml;ita de Coco', 'meta' => 'En vivo en Tu Ma&ntilde;ana'],
            ['id' => 'q_pDpnIijWY', 'title' => 'Stamina', 'meta' => 'Live at Miss Universe Panama 2024'],
            ['id' => 'sfOvmqQjpu0', 'title' => 'You Better Run', 'meta' => 'Live at Festival de La Rosa Dorada'],
            ['id' => 'Sb0IcEiAPbA', 'title' => 'Touch it', 'meta' => 'Live performance on Tu Ma&ntilde;ana'],
            ['id' => '0Xvjl-s4PwI', 'title' => 'In Your Heart', 'meta' => 'Live Performance Music Video'],
        ],
        'behind_the_scenes' => [
            ['id' => 'USfTD9rZ3o8', 'title' => 'Wave', 'meta' => 'Studio Recording Session'],
            ['id' => 'pBmyvcI8vtQ', 'title' => 'Places', 'meta' => 'Studio Clip'],
            ['id' => 'cXo8awFAt4s', 'title' => 'Places', 'meta' => 'Dance Rehearsal with Ching'],
            ['id' => '7ujK5dYKF7Q', 'title' => 'Make It Louder', 'meta' => 'Studio Recording Session'],
        ],
        'vlogs' => [
            ['id' => 'fA7CVtk0uNw', 'title' => 'Visitando Mas23', 'meta' => 'Panama vlog short'],
            ['id' => 'yz6mBW-BshM', 'title' => 'IA o arte real?', 'meta' => 'Craftmanship by Christian Javier'],
            ['id' => 'fcGJ7aZ39Hw', 'title' => 'New merch out now', 'meta' => '5D Stage update'],
        ],
    ];

    $featuredVideo = [
        'id' => 'UWDLtZCoTag',
        'title' => 'Reny Renteria - Take a bite (Official Music Video)',
        'meta' => 'Featured YouTube premiere',
    ];

    if (! empty($publicCms['featured_video'] ?? null)) {
        $featuredVideo = $publicCms['featured_video'];
    }

    $featuredVideo['external_url'] = $featuredVideo['external_url']
        ?? (! empty($featuredVideo['id']) ? "https://www.youtube.com/watch?v={$featuredVideo['id']}" : null);

    $cmsVideoCount = array_sum(array_map(
        fn (string $group): int => count($publicCms[$group] ?? []),
        array_keys($videoSections),
    ));
    $usesCmsPayload = in_array($publicCms['_cms_source'] ?? 'static', ['cms', 'cache'], true) && $cmsVideoCount > 0;
    $normalizeVideo = static function (array $video, string $group) use ($videoSections): array {
        $youtubeId = $video['id'] ?? null;

        return [
            ...$video,
            'id' => $youtubeId,
            'title' => $video['title'] ?? 'Untitled video',
            'meta' => $video['meta'] ?? $videoSections[$group]['title'],
            'group' => $group,
            'external_url' => $video['external_url'] ?? ($youtubeId ? "https://www.youtube.com/watch?v={$youtubeId}" : null),
            'play_state' => $video['play_state'] ?? ($youtubeId ? 'ready' : 'unavailable'),
        ];
    };

    $videoGroups = [];

    foreach ($videoSections as $group => $section) {
        $sourceVideos = $usesCmsPayload ? ($publicCms[$group] ?? []) : ($staticVideoGroups[$group] ?? []);
        $videoGroups[$group] = array_values(array_map(
            fn (array $video): array => $normalizeVideo($video, $group),
            $sourceVideos,
        ));
    }

    $selectedCategory = array_key_exists($selectedVideoCategory ?? '', $videoSections)
        ? $selectedVideoCategory
        : null;
    $visibleVideoSections = $selectedCategory
        ? [$selectedCategory => $videoSections[$selectedCategory]]
        : $videoSections;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Videos | Reny Renteria</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="videos">
        <div class="videos-shell" data-public-page-root>
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
                        <a class="tab" href="{{ route('music') }}">
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
                                src="https://www.youtube.com/embed/{{ $featuredVideo['id'] }}"
                                title="{{ $featuredVideo['title'] }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen>
                            </iframe>
                        </div>
                        <div class="featured-meta">
                            <div>
                                <strong>{{ $featuredVideo['title'] }}</strong>
                                <span>{{ $featuredVideo['meta'] }}</span>
                            </div>
                            @if (! empty($featuredVideo['external_url']))
                                <a
                                    class="youtube-pill"
                                    href="{{ $featuredVideo['external_url'] }}"
                                    target="_blank"
                                    rel="noreferrer"
                                    data-analytics-type="video"
                                    data-analytics-label="{{ $featuredVideo['title'] }}"
                                >Open YouTube</a>
                            @endif
                        </div>
                    </div>
                </section>

                @foreach ($visibleVideoSections as $group => $section)
                    <section class="content-section video-list-section" aria-labelledby="{{ $section['heading_id'] }}">
                        <div class="section-head">
                            <div>
                                @if ($selectedCategory)
                                    <a class="video-back-link" href="{{ route('videos') }}">All videos</a>
                                @endif
                                <h3 id="{{ $section['heading_id'] }}">{{ $section['title'] }}</h3>
                            </div>
                            @if ($selectedCategory)
                                <span class="video-count">{{ count($videoGroups[$group]) }} {{ count($videoGroups[$group]) === 1 ? 'video' : 'videos' }}</span>
                            @else
                                <a class="view-all" href="{{ route('videos', ['category' => $group]) }}" data-video-category="{{ $group }}">VIEW ALL</a>
                            @endif
                        </div>

                        @if ($videoGroups[$group])
                            @if ($group === 'series')
                                <div class="video-grid series-grid">
                                    @foreach ($videoGroups[$group] as $playlist)
                                        @php
                                            $playlistTitle = e(strip_tags(html_entity_decode($playlist['title'])));
                                            $playlistUrl = $playlist['external_url'] ?? null;
                                        @endphp
                                        <article class="playlist-card" data-video-state="{{ $playlist['play_state'] }}">
                                            <div
                                                class="playlist-stack"
                                                @if (! empty($playlist['id'])) style="--thumb-url: url('https://i.ytimg.com/vi/{{ $playlist['id'] }}/hqdefault.jpg');" @endif
                                                aria-hidden="true"
                                            ></div>
                                            <div class="playlist-copy">
                                                <div>
                                                    <span>Playlist</span>
                                                    <h4>{!! $playlist['title'] !!}</h4>
                                                    <p>{!! $playlist['meta'] !!}</p>
                                                </div>
                                                @if ($playlistUrl)
                                                    <a
                                                        class="playlist-link"
                                                        href="{{ $playlistUrl }}"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        data-analytics-type="playlist"
                                                        data-analytics-label="{{ $playlistTitle }}"
                                                    >Start watching</a>
                                                @else
                                                    <button
                                                        class="playlist-link playlist-link-button"
                                                        type="button"
                                                        data-video-player
                                                        data-youtube-title="{{ $playlistTitle }}"
                                                        data-video-state="unavailable"
                                                        data-analytics-type="playlist"
                                                        data-analytics-label="{{ $playlistTitle }}"
                                                    >Unavailable</button>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <div class="video-grid">
                                    @foreach ($videoGroups[$group] as $video)
                                        @include('partials.video-card', ['video' => $video])
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div class="video-empty-state" data-video-empty-state="{{ $group }}">
                                <span>Empty</span>
                                <strong>{{ $section['empty'] }}</strong>
                            </div>
                        @endif
                    </section>
                @endforeach

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a href="{{ route('music') }}">
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

        @include('partials.video-player-modal')
        @include('partials.music-player-modal')
    </body>
</html>
