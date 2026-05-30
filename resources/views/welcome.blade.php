<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Reny Renteria') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
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
                        <a class="tab is-active" href="#music" aria-current="page">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path d="M9 18V5l10-2v13"></path>
                                <circle cx="7" cy="18" r="3"></circle>
                                <circle cx="17" cy="16" r="3"></circle>
                            </svg>
                            <span>MUSIC</span>
                        </a>
                        <a class="tab" href="#videos">
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

            <main class="main-content" id="music">
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

                <section class="hero" aria-label="Featured album">
                    <div class="hero-content">
                        @if ($hero->eyebrow)
                            <p class="eyebrow">{!! nl2br(e($hero->eyebrow)) !!}</p>
                        @endif
                        <h1>{!! nl2br(e($hero->title)) !!}</h1>
                        @if ($hero->subtitle)
                            <h2>{{ $hero->subtitle }}</h2>
                        @endif
                        @if ($hero->body)
                            <p class="hero-copy">{{ $hero->body }}</p>
                        @endif
                        @if ($hero->link_text)
                            <p class="hero-link">{!! nl2br(e($hero->link_text)) !!}</p>
                        @endif
                    </div>

                    @php($heroImage = $hero->image_path ? \Illuminate\Support\Facades\Storage::url($hero->image_path) : null)
                    <div
                        class="artist-card {{ $heroImage ? 'has-image' : '' }}"
                        @if ($heroImage) style="background-image: url('{{ $heroImage }}')" @endif
                        aria-label="{{ $hero->title }} artwork"
                    >
                        <div class="disc-badge">RR</div>
                        <div class="barcode"></div>
                        @if ($hero->badge_text)
                            <span class="artist-card-label">{{ $hero->badge_text }}</span>
                        @endif
                    </div>
                </section>

                <section class="content-section" aria-labelledby="albums-title">
                    <div class="section-head">
                        <h3 id="albums-title">Albums</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="albums">
                        @foreach ($albums as $index => $album)
                            @php($albumImage = $album->image_path ? \Illuminate\Support\Facades\Storage::url($album->image_path) : null)
                            <article class="album">
                                <div
                                    class="cover {{ $albumImage ? 'has-image' : 'cover-'.chr(97 + ($index % 4)) }}"
                                    data-title="{{ $album->cover_label ?: $album->title }}"
                                    @if ($albumImage) style="background-image: url('{{ $albumImage }}')" @endif
                                >
                                    <button class="play-button" type="button" aria-label="Play {{ $album->title }}"><span></span></button>
                                </div>
                                <h4>{{ $album->title }}</h4>
                                <p>{{ $album->track_count }} {{ \Illuminate\Support\Str::plural('track', $album->track_count) }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="content-section" aria-labelledby="singles-title">
                    <div class="section-head">
                        <h3 id="singles-title">Singles</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="singles">
                        @foreach ($singles as $single)
                            @php($singleImage = $single->image_path ? \Illuminate\Support\Facades\Storage::url($single->image_path) : null)
                            @php($singleAudio = $single->audio_path ? \Illuminate\Support\Facades\Storage::url($single->audio_path) : $single->audio_url)
                            <article class="single">
                                <div
                                    class="single-art {{ $singleImage ? 'has-image' : '' }}"
                                    @if ($singleImage) style="background-image: url('{{ $singleImage }}')" @endif
                                    aria-hidden="true"
                                ></div>
                                <div>
                                    <strong>{{ $single->title }}</strong>
                                    <span>{{ $single->artist ?: 'Reny Renteria' }}</span>
                                </div>
                                @if ($singleAudio)
                                    <a class="mini-play" href="{{ $singleAudio }}" aria-label="Play {{ $single->title }}"><span></span></a>
                                @else
                                    <button class="mini-play" type="button" aria-label="Play {{ $single->title }}"><span></span></button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>

                <nav class="mobile-bottom-nav" aria-label="Mobile menu">
                    <a class="is-active" href="#music" aria-current="page">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M9 18V5l10-2v13"></path>
                            <circle cx="7" cy="18" r="3"></circle>
                            <circle cx="17" cy="16" r="3"></circle>
                        </svg>
                        <span class="sr-only">MUSIC</span>
                    </a>
                    <a href="#videos">
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
