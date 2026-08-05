@php
    $items = $publicCms['items'] ?? [];
    $isAlbums = $section === 'albums';
    $isPlaylists = $section === 'playlists';
    $title = $isAlbums ? 'Albums' : ($isPlaylists ? 'Playlists' : 'Singles');
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
    <body class="home-page music-page" data-analytics-screen="music_{{ $section }}">
        <div class="music-shell home-shell music-stage-shell" data-public-page-root>
            @include('partials.stage-lights')

            <aside class="sidebar" aria-label="Primary navigation">
                <div>
                    <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                        <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
                    </a>

                    <x-public-navigation active="music" />
                </div>

                <x-member-card />
            </aside>

            <main class="main-content music-stage-main music-collection-main" id="music-{{ $section }}">
                <header class="mobile-header home-mobile-header music-mobile-header">
                    <div class="mobile-brand">
                        <a class="brand-link" href="{{ route('home') }}" aria-label="Reny Renteria home">
                            <img class="brand-logo" src="{{ asset('images/reny-renteria-logo-white.png') }}" alt="Reny Renteria">
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
                                            @if (! empty($album['image_url'])) style="background-image: url('{{ $album['image_url'] }}'); background-size: cover; background-position: center;" @endif
                                        >
                                            <button
                                                class="cover-play-area"
                                                type="button"
                                                @include('partials.music-play-trigger-attributes', ['item' => $album, 'type' => 'album', 'label' => "Play {$album['title']} cover"])
                                            ></button>
                                            @include('partials.music-play-button', ['item' => $album, 'class' => 'play-button', 'type' => 'album'])
                                        </div>
                                        @if (($album['access_state'] ?? 'ready') !== 'ready')
                                            <span class="music-state-badge">{{ $album['access_label'] }}</span>
                                        @endif
                                        <h4><a href="{{ $album['detail_url'] }}">{{ $album['title'] }}</a></h4>
                                        <p>{{ $album['meta'] }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @elseif ($isPlaylists)
                            <div class="music-playlists music-list-grid">
                                @foreach ($items as $playlist)
                                    <article class="playlist-card music-item" data-access-state="{{ $playlist['access_state'] ?? 'ready' }}">
                                        <div
                                            class="playlist-stack"
                                            @if (! empty($playlist['image_url'])) style="--thumb-url: url('{{ $playlist['image_url'] }}');" @endif
                                        >
                                            @include('partials.music-play-button', ['item' => $playlist, 'class' => 'playlist-play-button', 'type' => 'playlist'])
                                        </div>
                                        <div class="playlist-copy">
                                            <div>
                                                <span>{{ $playlist['meta'] }}</span>
                                                <h4><a href="{{ $playlist['detail_url'] }}">{{ $playlist['title'] }}</a></h4>
                                                <p>{{ collect($playlist['tracks'] ?? [])->take(5)->implode(' / ') }}</p>
                                            </div>
                                            @if (($playlist['access_state'] ?? 'ready') !== 'ready')
                                                <em class="music-inline-state">{{ $playlist['access_label'] }}</em>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="singles music-list-grid">
                                @foreach ($items as $single)
                                    @include('partials.music-single-card', ['single' => $single])
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

                <x-public-navigation active="music" mobile />
            </main>
        </div>

        @include('partials.store-checkout-modals')
        @include('partials.music-player-modal')
    </body>
</html>
