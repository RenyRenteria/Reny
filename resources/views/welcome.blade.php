@php
    $cmsBanner = $publicCms['banner'] ?? [
        'eyebrow_line_1' => 'First',
        'eyebrow_line_2' => 'Album',
        'title_line_1' => 'Biggest',
        'title_line_2' => 'Launch',
        'subtitle' => 'Comeback Album!',
        'description' => 'A cinematic release package for Reny Renteria, built around a lead album, featured tracks, fan updates, and premium music drops.',
        'footer_line_1' => 'Visit us today at',
        'footer_line_2' => 'renyrenteria.com',
        'destination_url' => 'https://renyrenteria.com',
        'sticker_line_1' => 'THE FIRST ALBUM',
        'sticker_line_2' => 'BANO #1',
        'image_url' => null,
        'image_alt' => 'Reny Renteria music banner artwork',
    ];
    $cmsAlbums = $publicCms['albums'] ?? [];
    $cmsSingles = $publicCms['singles'] ?? [];
    $fallbackPlayState = [
        'access_state' => 'playback_error',
        'access_label' => 'Audio unavailable',
        'access_message' => 'This demo music card is not connected to a CMS audio source yet.',
        'cta_label' => 'Open music',
        'cta_url' => route('music'),
        'detail_url' => route('music'),
    ];
    $fallbackAlbums = [
        [...$fallbackPlayState, 'id' => 'reny-sessions', 'kind' => 'album', 'title' => 'Reny Sessions', 'meta' => '12 tracks', 'cover_class' => 'cover-a'],
        [...$fallbackPlayState, 'id' => 'bano-1', 'kind' => 'album', 'title' => 'Bano #1', 'meta' => '10 tracks', 'cover_class' => 'cover-b'],
        [...$fallbackPlayState, 'id' => 'first-album', 'kind' => 'album', 'title' => 'First Album', 'meta' => '8 tracks', 'cover_class' => 'cover-c'],
        [...$fallbackPlayState, 'id' => 'live-cuts', 'kind' => 'album', 'title' => 'Live Cuts', 'meta' => '6 tracks', 'cover_class' => 'cover-d'],
    ];
    $fallbackSingles = [
        [...$fallbackPlayState, 'id' => 'biggest-launch', 'kind' => 'single', 'title' => 'Biggest Launch', 'artist' => 'Reny Renteria'],
        [...$fallbackPlayState, 'id' => 'comeback-album', 'kind' => 'single', 'title' => 'Comeback Album', 'artist' => 'Reny Renteria'],
        [...$fallbackPlayState, 'id' => 'first-drop', 'kind' => 'single', 'title' => 'First Drop', 'artist' => 'Reny Renteria'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Reny Renteria') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body data-analytics-screen="music">
        <div class="music-shell">
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

            <main class="main-content" id="music-page">
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

                <section class="tab-panel is-active" id="music" data-tab-panel="music">
                    <section class="hero" aria-label="Featured album">
                        <div class="hero-content">
                            <p class="eyebrow">
                                <span>{{ $cmsBanner['eyebrow_line_1'] }}</span>
                                @if (filled($cmsBanner['eyebrow_line_2'] ?? null))
                                    <br><span>{{ $cmsBanner['eyebrow_line_2'] }}</span>
                                @endif
                            </p>
                            <h1>
                                <span>{{ $cmsBanner['title_line_1'] }}</span>
                                @if (filled($cmsBanner['title_line_2'] ?? null))
                                    <br><span>{{ $cmsBanner['title_line_2'] }}</span>
                                @endif
                            </h1>
                            <h2>{{ $cmsBanner['subtitle'] }}</h2>
                            <p class="hero-copy">{{ $cmsBanner['description'] }}</p>
                            <a class="hero-link" href="{{ $cmsBanner['destination_url'] }}" target="_blank" rel="noreferrer">
                                <span>{{ $cmsBanner['footer_line_1'] }}</span>
                                @if (filled($cmsBanner['footer_line_2'] ?? null))
                                    <br><span>{{ $cmsBanner['footer_line_2'] }}</span>
                                @endif
                            </a>
                        </div>

                        <div @class(['artist-card', 'has-uploaded-art' => filled($cmsBanner['image_url'] ?? null)]) aria-hidden="true">
                            @if (filled($cmsBanner['image_url'] ?? null))
                                <img class="artist-card-image" src="{{ $cmsBanner['image_url'] }}" alt="">
                            @endif
                            <div class="barcode"></div>
                            @if (filled($cmsBanner['sticker_line_1'] ?? null) || filled($cmsBanner['sticker_line_2'] ?? null))
                                <div class="artist-sticker">
                                    @if (filled($cmsBanner['sticker_line_1'] ?? null))
                                        <span>{{ $cmsBanner['sticker_line_1'] }}</span>
                                    @endif
                                    @if (filled($cmsBanner['sticker_line_2'] ?? null))
                                        <span>{{ $cmsBanner['sticker_line_2'] }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>

                    <section class="content-section" aria-labelledby="albums-title">
                        <div class="section-head">
                            <h3 id="albums-title">Albums</h3>
                            <a class="view-all" href="{{ route('music.albums') }}">VIEW ALL</a>
                        </div>

                        <div class="albums">
                            @if ($cmsAlbums)
                                @foreach ($cmsAlbums as $album)
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
                                    </article>
                                @endforeach
                            @else
                                @foreach ($fallbackAlbums as $album)
                                    <article class="album music-item" data-access-state="{{ $album['access_state'] }}">
                                        <div class="cover {{ $album['cover_class'] }}" data-title="{{ $album['title'] }}">
                                            @include('partials.music-play-button', ['item' => $album, 'class' => 'play-button', 'type' => 'album'])
                                        </div>
                                        <span class="music-state-badge">{{ $album['access_label'] }}</span>
                                        <h4>{{ $album['title'] }}</h4>
                                        <p>{{ $album['meta'] }}</p>
                                    </article>
                                @endforeach
                            @endif
                        </div>
                    </section>

                    <section class="content-section" aria-labelledby="singles-title">
                        <div class="section-head">
                            <h3 id="singles-title">Singles</h3>
                            <a class="view-all" href="{{ route('music.singles') }}">VIEW ALL</a>
                        </div>

                        <div class="singles">
                            @if ($cmsSingles)
                                @foreach ($cmsSingles as $single)
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
                            @else
                                @foreach ($fallbackSingles as $single)
                                    <article class="single music-item" data-access-state="{{ $single['access_state'] }}">
                                        <div class="single-art" aria-hidden="true"></div>
                                        <div>
                                            <strong>{{ $single['title'] }}</strong>
                                            <span>{{ $single['artist'] }}</span>
                                            <em class="music-inline-state">{{ $single['access_label'] }}</em>
                                        </div>
                                        @include('partials.music-play-button', ['item' => $single, 'class' => 'mini-play', 'type' => 'single'])
                                    </article>
                                @endforeach
                                <x-access-gate
                                    section="music"
                                    title="VIP Mix"
                                    preview="Open users can see the drop; full playback requires Royal Pass."
                                >
                                    <article class="single">
                                        <div class="single-art" aria-hidden="true"></div>
                                        <div>
                                            <strong>VIP Mix</strong>
                                            <span>Royal-only audio stream</span>
                                        </div>
                                        @include('partials.music-play-button', [
                                            'item' => [
                                                ...$fallbackPlayState,
                                                'id' => 'vip-mix',
                                                'kind' => 'single',
                                                'title' => 'VIP Mix',
                                            ],
                                            'class' => 'mini-play',
                                            'type' => 'single',
                                        ])
                                    </article>
                                </x-access-gate>
                            @endif
                        </div>
                    </section>
                </section>

                <section class="tab-panel" id="community" data-tab-panel="community" hidden>
                    <div class="community-grid">
                        <section class="feed-column" aria-label="Official community feed">
                            <div class="feed-heading">
                                <div>
                                    <p class="community-eyebrow">Fan-safe &amp; moderated</p>
                                    <h1>Official Feed</h1>
                                </div>
                                <div class="direct-posts">
                                    <span>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 3l2.7 5.4 6 .9-4.4 4.2 1 6-5.3-2.8-5.3 2.8 1-6-4.4-4.2 6-.9L12 3Z"></path>
                                        </svg>
                                    </span>
                                    Reny Direct Posts
                                </div>
                            </div>

                            <article class="post-card">
                                <div class="post-head">
                                    <div class="post-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 2v20"></path>
                                            <path d="M8 7v5a4 4 0 0 0 8 0V7"></path>
                                            <path d="M6 12a6 6 0 0 0 12 0"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2>Studio note from Reny</h2>
                                        <div class="post-time">Today</div>
                                    </div>
                                </div>

                                <p class="post-copy">
                                    Finishing the next release window with final vocal edits,
                                    choreography notes, and visuals for the fan club first.
                                </p>

                                <div class="media-frame">
                                    <img src="https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1400&q=80" alt="Warm recording studio with microphone and instruments">
                                    <button class="media-cta" type="button">
                                        View Reny note
                                        <span aria-hidden="true">→</span>
                                    </button>
                                </div>

                                <div class="post-actions">
                                    <div class="post-metrics">
                                        <span class="metric heart">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path>
                                            </svg>
                                            284
                                        </span>
                                        <span class="metric">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                                            </svg>
                                            38 replies
                                        </span>
                                    </div>
                                    <span class="share">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <path d="m8.6 13.5 6.8 4"></path>
                                            <path d="m15.4 6.5-6.8 4"></path>
                                        </svg>
                                    </span>
                                </div>
                            </article>

                            <section class="vote-card" aria-labelledby="fan-votes-title">
                                <div class="section-kicker">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                        <path d="M8 9h8"></path>
                                        <path d="M8 13h5"></path>
                                        <path d="M8 17h8"></path>
                                    </svg>
                                    <span id="fan-votes-title">Fan Votes</span>
                                </div>

                                <h3>Which drop should go first?</h3>

                                <div class="poll">
                                    <div class="poll-row">
                                        <div class="poll-label"><span>Studio photos</span><span>42%</span></div>
                                        <div class="poll-track"><span class="poll-fill" style="width: 42%"></span></div>
                                    </div>
                                    <div class="poll-row">
                                        <div class="poll-label"><span>Performance stills</span><span>34%</span></div>
                                        <div class="poll-track"><span class="poll-fill" style="width: 34%"></span></div>
                                    </div>
                                    <div class="poll-row">
                                        <div class="poll-label"><span>Travel archive</span><span>24%</span></div>
                                        <div class="poll-track"><span class="poll-fill" style="width: 24%"></span></div>
                                    </div>
                                </div>

                                <div class="vote-footer">
                                    <span>1248 total votes</span>
                                    <x-access-gate
                                        section="community"
                                        title="Voting requires Royal Pass"
                                        preview="Poll results stay visible in Open mode."
                                    >
                                        <button class="soft-button" type="button">Vote</button>
                                    </x-access-gate>
                                </div>
                            </section>

                            <article class="post-card photo-post">
                                <div class="post-head">
                                    <div class="post-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="m4 16 16-4"></path>
                                            <path d="m4 12 6-1.5"></path>
                                            <path d="m7 7 3 7 4-11 3 7"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2>Capri photo drop</h2>
                                        <div class="post-time">This week</div>
                                    </div>
                                </div>

                                <p class="post-copy">
                                    A few frames from the travel archive are moving into the Photos tab.
                                    More country-specific drops coming next.
                                </p>

                                <div class="media-frame">
                                    <img src="https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=1400&q=80" alt="Capri coastline and turquoise sea">
                                </div>

                                <div class="post-actions">
                                    <div class="post-metrics">
                                        <span class="metric heart">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"></path>
                                            </svg>
                                            319
                                        </span>
                                        <span class="metric">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path>
                                            </svg>
                                            51 replies
                                        </span>
                                    </div>
                                    <span class="share">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="18" cy="5" r="3"></circle>
                                            <circle cx="6" cy="12" r="3"></circle>
                                            <circle cx="18" cy="19" r="3"></circle>
                                            <path d="m8.6 13.5 6.8 4"></path>
                                            <path d="m15.4 6.5-6.8 4"></path>
                                        </svg>
                                    </span>
                                </div>
                            </article>
                        </section>

                        <aside class="side-column" aria-label="Community sidebar">
                            <div class="side-head">
                                <h2>Country Clubs</h2>
                                <a href="#clubs">View all</a>
                            </div>

                            <div class="club-list" id="clubs">
                                <article class="club-card">
                                    <div class="flag" aria-hidden="true">🇩🇴</div>
                                    <div>
                                        <strong>Dominican Republic</strong>
                                        <span>8.4K members · Planning Santo Domingo party</span>
                                    </div>
                                </article>
                                <article class="club-card">
                                    <div class="flag" aria-hidden="true">🇵🇦</div>
                                    <div>
                                        <strong>Panama</strong>
                                        <span>6.9K members · Sharing radio clips</span>
                                    </div>
                                </article>
                                <article class="club-card">
                                    <div class="flag" aria-hidden="true">🇨🇴</div>
                                    <div>
                                        <strong>Colombia</strong>
                                        <span>4.2K members · Building the Bogota map</span>
                                    </div>
                                </article>
                            </div>

                            <x-access-gate
                                section="community"
                                title="Groups are Royal actions"
                                preview="Club previews stay public; creating or joining requires Royal Pass."
                            >
                                <div class="club-actions">
                                    <button class="outline-button" type="button">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5v14"></path>
                                            <path d="M5 12h14"></path>
                                        </svg>
                                        Create group
                                    </button>
                                    <button class="join-button" type="button">Join group</button>
                                </div>
                            </x-access-gate>

                            <div class="chat-wrap">
                                <x-access-gate
                                    section="community"
                                    title="Clubhouse Chat"
                                    preview="Chat previews are visible in Open mode; sending messages requires Royal Pass."
                                >
                                    <section class="chat-card" aria-labelledby="chat-title">
                                    <div class="chat-head">
                                        <div>
                                            <h2 id="chat-title">Clubhouse Chat</h2>
                                            <span class="reply-chip">Kind replies only</span>
                                        </div>
                                        <span class="status-dot" aria-label="Live chat"></span>
                                    </div>

                                    <div class="chat-thread">
                                        <div class="message">
                                            <div class="avatar mia" aria-hidden="true"></div>
                                            <div class="bubble-wrap">
                                                <div class="bubble-meta"><strong>Mia</strong> · 3:42 PM</div>
                                                <div class="bubble">Who is going to the first meetup?</div>
                                            </div>
                                        </div>
                                        <div class="message">
                                            <div class="avatar luis" aria-hidden="true"></div>
                                            <div class="bubble-wrap">
                                                <div class="bubble-meta"><strong>Luis</strong> · 3:45 PM</div>
                                                <div class="bubble">We should pin a date after the next Reny post.</div>
                                            </div>
                                        </div>
                                        <div class="message self">
                                            <div class="bubble-wrap">
                                                <div class="bubble-meta">Just now · <strong>Alex</strong></div>
                                                <div class="bubble">I'm definitely down! Let's do it.</div>
                                            </div>
                                            <div class="avatar alex" aria-hidden="true"></div>
                                        </div>
                                    </div>

                                    <form class="chat-input">
                                        <label class="sr-only" for="message">Type a message</label>
                                        <input id="message" type="text" placeholder="Type a message...">
                                        <button class="send-button" type="button" aria-label="Send message">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="m3 11 18-8-8 18-2-8-8-2Z"></path>
                                                <path d="m11 13 10-10"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    </section>

                                    <button class="compose-button" type="button" aria-label="Compose post">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                    </button>
                                </x-access-gate>
                            </div>
                        </aside>
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

        @include('partials.music-player-modal')
    </body>
</html>
