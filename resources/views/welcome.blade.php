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
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M4 10h16"></path>
                                <path d="M5 10l1.2-5.2A1 1 0 0 1 7.2 4h9.6a1 1 0 0 1 1 .8L19 10"></path>
                                <path d="M5 10v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"></path>
                                <path d="M8 10a2 2 0 0 0 4 0"></path>
                                <path d="M12 10a2 2 0 0 0 4 0"></path>
                                <path d="M9 20v-5h6v5"></path>
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
                        <button class="menu-button" type="button" aria-label="Open menu">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                    </div>

                    <nav class="mobile-tabs" aria-label="Main menu">
                        <a class="is-active" href="#music" aria-current="page">MUSIC</a>
                        <a href="#videos">VIDEOS</a>
                        <a href="#photos">PHOTOS</a>
                        <a href="#community">COMMUNITY</a>
                        <a href="#store">STORE</a>
                    </nav>
                </header>

                <section class="hero" aria-label="Featured album">
                    <div class="hero-content">
                        <p class="eyebrow">First<br>Album</p>
                        <h1>Biggest<br>Launch</h1>
                        <h2>Comeback Album!</h2>
                        <p class="hero-copy">
                            A cinematic release package for Reny Renteria, built around a lead album,
                            featured tracks, fan updates, and premium music drops.
                        </p>
                        <p class="hero-link">Visit us today at<br>renyrenteria.com</p>
                    </div>

                    <div class="artist-card" aria-hidden="true">
                        <div class="disc-badge">RR</div>
                        <div class="barcode"></div>
                    </div>
                </section>

                <section class="content-section" aria-labelledby="albums-title">
                    <div class="section-head">
                        <h3 id="albums-title">Albums</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="albums">
                        <article class="album">
                            <div class="cover cover-a" data-title="Reny">
                                <button class="play-button" type="button" aria-label="Play Reny Sessions"><span></span></button>
                            </div>
                            <h4>Reny Sessions</h4>
                            <p>12 tracks</p>
                        </article>
                        <article class="album">
                            <div class="cover cover-b" data-title="Bano">
                                <button class="play-button" type="button" aria-label="Play Bano #1"><span></span></button>
                            </div>
                            <h4>Bano #1</h4>
                            <p>10 tracks</p>
                        </article>
                        <article class="album">
                            <div class="cover cover-c" data-title="First">
                                <button class="play-button" type="button" aria-label="Play First Album"><span></span></button>
                            </div>
                            <h4>First Album</h4>
                            <p>8 tracks</p>
                        </article>
                        <article class="album">
                            <div class="cover cover-d" data-title="Live">
                                <button class="play-button" type="button" aria-label="Play Live Cuts"><span></span></button>
                            </div>
                            <h4>Live Cuts</h4>
                            <p>6 tracks</p>
                        </article>
                    </div>
                </section>

                <section class="content-section" aria-labelledby="singles-title">
                    <div class="section-head">
                        <h3 id="singles-title">Singles</h3>
                        <button class="view-all" type="button">VIEW ALL</button>
                    </div>

                    <div class="singles">
                        <article class="single">
                            <div class="single-art" aria-hidden="true"></div>
                            <div>
                                <strong>Biggest Launch</strong>
                                <span>Reny Renteria</span>
                            </div>
                            <button class="mini-play" type="button" aria-label="Play Biggest Launch"><span></span></button>
                        </article>
                        <article class="single">
                            <div class="single-art" aria-hidden="true"></div>
                            <div>
                                <strong>Comeback Album</strong>
                                <span>Reny Renteria</span>
                            </div>
                            <button class="mini-play" type="button" aria-label="Play Comeback Album"><span></span></button>
                        </article>
                        <article class="single">
                            <div class="single-art" aria-hidden="true"></div>
                            <div>
                                <strong>First Drop</strong>
                                <span>Reny Renteria</span>
                            </div>
                            <button class="mini-play" type="button" aria-label="Play First Drop"><span></span></button>
                        </article>
                        <article class="single">
                            <div class="single-art" aria-hidden="true"></div>
                            <div>
                                <strong>VIP Mix</strong>
                                <span>Exclusive</span>
                            </div>
                            <button class="mini-play" type="button" aria-label="Play VIP Mix"><span></span></button>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
