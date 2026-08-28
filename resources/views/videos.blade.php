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

    $staticVideoGroups = collect(config('reny_videos.groups', []))
        ->map(fn (array $videos): array => collect($videos)
            ->map(fn (array $video): array => [
                'id' => $video['youtube_id'],
                'title' => $video['title'],
                'meta' => $video['meta'],
            ])
            ->all())
        ->all();

    $pageSettings = $publicCms['page'] ?? [];
    $configuredFeaturedVideo = config('reny_videos.featured', []);
    $defaultFeaturedVideo = [
        'id' => $configuredFeaturedVideo['youtube_id'] ?? null,
        'title' => $configuredFeaturedVideo['title'] ?? 'Featured video',
        'meta' => $configuredFeaturedVideo['meta'] ?? 'Featured YouTube premiere',
    ];

    $cmsVideoCount = array_sum(array_map(
        fn (string $group): int => count($publicCms[$group] ?? []),
        array_keys($videoSections),
    ));
    $hasCmsPayload = in_array($publicCms['_cms_source'] ?? 'static', ['cms', 'cache', 'preview'], true);
    $usesCmsPayload = $hasCmsPayload && $cmsVideoCount > 0;
    $featuredVideo = $publicCms['featured_video'] ?? ($usesCmsPayload ? null : $defaultFeaturedVideo);

    if (is_array($featuredVideo)) {
        $featuredVideo['external_url'] = $featuredVideo['external_url']
            ?? (! empty($featuredVideo['id']) ? "https://www.youtube.com/watch?v={$featuredVideo['id']}" : null);
    }
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
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.public-seo', ['seo' => $pageSettings, 'fallbackTitle' => 'Videos | Reny Renteria'])

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="videos-page" data-analytics-screen="videos">
        <div class="videos-shell home-shell videos-stage-shell" data-public-page-root>
            <div class="stage-lights" aria-hidden="true">
                <span class="stage-light stage-light--one"></span>
                <span class="stage-light stage-light--two"></span>
                <span class="stage-light stage-light--three"></span>
                <span class="stage-light-fixture stage-light-fixture--one"></span>
                <span class="stage-light-fixture stage-light-fixture--two"></span>
                <span class="stage-light-fixture stage-light-fixture--three"></span>
            </div>

            @include('partials.cms-preview-banner')
            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                        <img
                            class="brand-logo"
                            src="{{ asset('images/reny-renteria-logo-white.png') }}"
                            alt="Reny Renteria"
                        >
                    </a>

                    <x-public-navigation active="videos" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content videos-content" id="videos">
                <header class="mobile-header videos-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ url('/') }}" aria-label="Reny Renteria home">
                            <img
                                class="brand-logo"
                                src="{{ asset('images/reny-renteria-logo-white.png') }}"
                                alt="Reny Renteria"
                            >
                        </a>
                    </div>
                </header>

                <x-royal-pass-banner :show-images="false" />

                <section class="video-hero" aria-label="Featured video">
                    <div class="hero-content">
                        <p class="eyebrow">{!! nl2br(e($pageSettings['eyebrow'] ?? 'Video Premiere')) !!}</p>
                        <h1>{!! nl2br(e($pageSettings['title'] ?? 'Watch Now')) !!}</h1>
                        <h2>{{ $pageSettings['subtitle'] ?? 'Official Music Videos' }}</h2>
                        <p class="hero-copy">{{ $pageSettings['description'] ?? '' }}</p>
                        @if (filled($pageSettings['cover_url'] ?? null))
                            <img class="public-page-cover" src="{{ $pageSettings['cover_url'] }}" alt="{{ $pageSettings['cover_alt'] ?? '' }}">
                        @endif
                        <p class="hero-link">Streaming from<br>youtube.com/renyrenteriam</p>
                    </div>

                    <div class="featured-video">
                        @if ($featuredVideo && ! empty($featuredVideo['id']))
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
                        @else
                            <div class="video-frame video-frame-empty"><span>No featured video published yet.</span></div>
                        @endif
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

                <x-public-navigation active="videos" mobile />
            </main>
        </div>

        @include('partials.video-player-modal')
        @include('partials.music-player-modal')
    </body>
</html>
